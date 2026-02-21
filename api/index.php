<?php
// Enable Error Reporting (Log only, no display in production)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Load Composer Autoloader
require_once __DIR__ . '/vendor/autoload.php';
// Load Config
require_once __DIR__ . '/config.php';
use MapaRD\Services\GeminiService;
use MapaRD\Services\ReportService;
use MapaRD\Services\SecurityUtils;
use MapaRD\Services\MailService;
use MapaRD\Services\ScanService;
use function MapaRD\Services\text_sanitize;
use function MapaRD\Services\translate_data_class;
// api/index.php - Real OSINT Engine
// --------------------------------------------------------------------------
// 1. SECURITY HEADERS (MILD - RESTORED)
// --------------------------------------------------------------------------
// HSTS: Neutral (Commented out to avoid cache issues for now)
// header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// CSP: Mild (Allow connections, block objects/base)
$csp = "default-src 'self'; connect-src *; img-src * data:; style-src 'self' 'unsafe-inline'; ";
$csp .= "script-src 'self' 'unsafe-inline'; object-src 'none'; base-uri 'self';";
header("Content-Security-Policy: $csp");

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block"); // Legacy but good depth defense

// --------------------------------------------------------------------------
// 2. STRICT CORS (No Wildcards)
// --------------------------------------------------------------------------
$allowedOrigins = [
    'https://mapard.felipemiramontesr.net',
    'http://localhost:5173', // Dev
    'http://localhost:4173', // Preview
    'capacitor://localhost', // iOS App
    'http://localhost', // Android WebView
    'https://localhost' // Android Https
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// DEBUG: Log Origin to check what Android is actually sending
// file_put_contents(__DIR__ . '/temp/cors_debug.log', date('c') . " - Origin: " . $origin . "\n", FILE_APPEND);

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY");
    header("Access-Control-Allow-Credentials: true");
} else {
    // Fallback for non-browser clients or unauthorized origins
// header("HTTP/1.1 403 Forbidden");
// exit;
}

// Handle Preflight OPTIONS requests immediately (Before DB/Auth)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --------------------------------------------------------------------------
// 3. RATE LIMITING (RESTORED)
// --------------------------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'];
$limit = 100; // Requests per minute (Relaxed from 60)
$timeFrame = 60;
$limitFile = __DIR__ . '/temp/ratelimit_' . md5($ip) . '.json';

// Ensure temp directory exists
if (!is_dir(__DIR__ . '/temp')) {
    @mkdir(__DIR__ . '/temp', 0755, true);
}

// Read current data
$data = ['count' => 0, 'startTime' => time()];
if (file_exists($limitFile)) {
    $content = @file_get_contents($limitFile);
    if ($content) {
        $data = json_decode($content, true);
    }
}

// Reset if timeframe passed
if (time() - $data['startTime'] > $timeFrame) {
    $data = ['count' => 0, 'startTime' => time()];
}

// Increment
$data['count']++;

// Check Limit
if ($data['count'] > $limit) {
    http_response_code(429);
    echo json_encode(["error" => "Rate Limit Exceeded. Try again in 60 seconds."]);
    exit;
}

// Save
@file_put_contents($limitFile, json_encode($data));
// Register Shutdown Function to catch Fatal Errors
register_shutdown_function(function () {

    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(["error" => "Fatal PHP Error", "details" => $error]);
        exit;
    }
});
// Version 2 DB to ensure 'domain' column exists
$dbPath = __DIR__ . '/mapard_v2.sqlite';
try {
    if (!is_writable(__DIR__)) {
        throw new Exception("Directory " . __DIR__ . " is not writable. Cannot create DB.");
    }

    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // USERS Table (Phase 21 + Phase 25)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email_target TEXT UNIQUE,
        password_hash TEXT,
        is_verified INTEGER DEFAULT 0,
        fa_code TEXT,
        device_id TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Migration: Add device_id to users if it doesn't exist
    $userCols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('device_id', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN device_id TEXT");
    }

    // SCANS Table (Updated)
    $pdo->exec("CREATE TABLE IF NOT EXISTS scans (
job_id TEXT PRIMARY KEY,
user_id INTEGER,
email TEXT,
domain TEXT,
status TEXT,
result_path TEXT,
logs TEXT,
findings TEXT,
is_encrypted INTEGER DEFAULT 0,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

    // Migration: Add user_id and is_encrypted to scans if they don't exist
    $columns = $pdo->query("PRAGMA table_info(scans)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('user_id', $columns)) {
        $pdo->exec("ALTER TABLE scans ADD COLUMN user_id INTEGER");
    }
    if (!in_array('is_encrypted', $columns)) {
        $pdo->exec("ALTER TABLE scans ADD COLUMN is_encrypted INTEGER DEFAULT 0");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database Init Failed: " . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$pathParams = explode('/', trim($requestUri, '/'));
// OPTIONS handler moved to top

// ROUTER
if (isset($pathParams[1]) && $pathParams[1] === 'auth') {
    // AUTH SETUP (Register/Initial Login)
    if ($pathParams[2] === 'setup' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $deviceId = $input['device_id'] ?? '';

        if (empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(["error" => "Email and password required"]);
            exit;
        }

        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email_target = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $faCode = SecurityUtils::generate2FA();
        $hashedPassword = SecurityUtils::hashPassword($password);

        if (!$user) {
            // Create New User & Bind Device
            $stmt = $pdo->prepare("INSERT INTO users (email_target, password_hash, fa_code, device_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $hashedPassword, $faCode, $deviceId]);
        } else {
            // Phase 25 Strict: Enforce Hardware Binding
            if (!empty($user['device_id']) && !empty($deviceId) && $user['device_id'] !== $deviceId) {
                http_response_code(403);
                echo json_encode([
                    "error" => "HARDWARE_MISMATCH",
                    "message" => "Terminal locked to different hardware. Access Denied."
                ]);
                exit;
            }

            // Update Existing User (Reset Password/2FA if re-setting up)
            // Ensure device_id is set if it was NULL (Legacy users)
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, fa_code = ?, is_verified = 0, device_id = ? WHERE email_target = ?");
            $stmt->execute([$hashedPassword, $faCode, $deviceId ?: $user['device_id'], $email]);
        }

        // TACTICAL: Send real 2FA email
        try {
            MailService::send2FA($email, $faCode);
            $logMsg = "[2FA] CODE FOR $email: $faCode - SENT VIA SMTP\n";
            file_put_contents(__DIR__ . '/temp/2fa_tactical.log', $logMsg, FILE_APPEND);
        } catch (Exception $e) {
            $logError = "[2FA ERROR] For $email: " . $e->getMessage() . "\n";
            file_put_contents(__DIR__ . '/temp/2fa_tactical.log', $logError, FILE_APPEND);
        }

        echo json_encode(["status" => "2FA_SENT", "message" => "Tactical code sent to target email."]);
        exit;
    }

    // AUTH VERIFY (2FA)
    if ($pathParams[2] === 'verify' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $code = $input['code'] ?? '';
        $deviceId = $input['device_id'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email_target = ? AND fa_code = ?");
        $stmt->execute([$email, $code]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(401);
            echo json_encode(["error" => "Invalid verification code"]);
            exit;
        }

        // Phase 25 Strict: Enforce Hardware Binding
        if (!empty($user['device_id']) && !empty($deviceId) && $user['device_id'] !== $deviceId) {
            http_response_code(403);
            echo json_encode([
                "error" => "HARDWARE_MISMATCH",
                "message" => "Terminal locked to different hardware. Access Denied."
            ]);
            exit;
        }

        // Mark as verified
        $pdo->prepare("UPDATE users SET is_verified = 1, fa_code = NULL WHERE id = ?")->execute([$user['id']]);

        echo json_encode([
            "status" => "VERIFIED",
            "token" => "blind_ops_" . bin2hex(random_bytes(16)),
            "email" => $email
        ]);
        exit;
    }

    // USER STATUS (Phase 23)
    if (isset($pathParams[1]) && $pathParams[1] === 'user' && $pathParams[2] === 'status') {
        $email = $_GET['email'] ?? '';
        // In production, we would use the JWT token to identify the user.
// For now, we use the email as an identifier since it's already "locked" in the frontend.

        $stmt = $pdo->prepare("SELECT * FROM scans WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($job) {
            $findings = $job['is_encrypted'] ? SecurityUtils::decrypt($job['findings']) : $job['findings'];
            $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];

            echo json_encode([
                "has_scans" => true,
                "job_id" => $job['job_id'],
                "status" => $job['status'],
                "logs" => json_decode($logs),
                "findings" => json_decode($findings),
                "result_url" => $job['result_path']
            ]);
        } else {
            echo json_encode(["has_scans" => false]);
        }
        exit;
    }
}

// ROUTER - SCAN
if (isset($pathParams[1]) && $pathParams[1] === 'scan') {
    // START SCAN
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? 'unknown';
        $domain = $input['domain'] ?? '';
        if (empty($domain) && strpos($email, '@') !== false) {
            $parts = explode('@', $email);
            $domain = array_pop($parts);
        }

        $jobId = uniqid('job_', true);
        $initialLogs = json_encode([
            ["message" => "Iniciando Protocolo MAPARD...", "type" => "info", "timestamp" => date('c')]
        ]);
        $stmt = $pdo->prepare("INSERT INTO scans (job_id, email, domain, status, logs) VALUES (?, ?, ?, 'PENDING', ?)");
        $stmt->execute([$jobId, $email, $domain, $initialLogs]);
        echo json_encode(["job_id" => $jobId, "status" => "ACCEPTED"]);
        exit;
    }

    // CHECK STATUS & EXECUTE LOGIC
    if ($method === 'GET' && isset($pathParams[2])) {
        $jobId = $pathParams[2];
        $stmt = $pdo->prepare("SELECT * FROM scans WHERE job_id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            http_response_code(404);
            echo json_encode(["error" => "Job not found"]);
            exit;
        }

        if ($job['status'] === 'COMPLETED') {
            $findings = $job['is_encrypted'] ? SecurityUtils::decrypt($job['findings']) : $job['findings'];
            $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];

            echo json_encode([
                "job_id" => $jobId,
                "status" => "COMPLETED",
                "logs" => json_decode($logs),
                "findings" => json_decode($findings),
                "result_url" => $job['result_path']
            ]);
            exit;
        }

        // RACE CONDITION FIX: Prevent multiple executions
        if ($job['status'] === 'RUNNING') {
            $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];
            echo json_encode([
                "job_id" => $jobId,
                "status" => "RUNNING",
                "logs" => json_decode($logs),
                "message" => "Scan in progress..."
            ]);
            exit;
        }

        // Only Execute if PENDING or RUNNING (Retry)
        try {
            $scanService = new ScanService($pdo);
            $result = $scanService->runScan($jobId);
            echo json_encode($result);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
        exit;
    }

    // UPDATE FINDINGS (Phase 26: Persistence)
    if ($pathParams[2] === 'update-findings' && $method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $findings = $input['findings'] ?? [];

        if (empty($email)) {
            http_response_code(400);
            echo json_encode(["error" => "Email required"]);
            exit;
        }

        // We update findings in the LATEST completed scan for this user
        $stmt = $pdo->prepare("SELECT job_id, is_encrypted FROM scans WHERE email = ? AND status = 'COMPLETED' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $lastScan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastScan) {
            $jsonData = json_encode($findings);
            $finalData = $lastScan['is_encrypted'] ? SecurityUtils::encrypt($jsonData) : $jsonData;

            $update = $pdo->prepare("UPDATE scans SET findings = ? WHERE job_id = ?");
            $update->execute([$finalData, $lastScan['job_id']]);

            echo json_encode(["status" => "UPDATED", "job_id" => $lastScan['job_id']]);
        } else {
            http_response_code(404);
            echo json_encode(["error" => "No completed scans found to update."]);
        }
        exit;
    }
}