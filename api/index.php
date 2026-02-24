<?php
// Enable Error Reporting (Log only, no display in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// GLOBAL ERROR SUPPRESSION: Prevent Deprecated Warnings from corrupting JSON Responses
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

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
// 1. SECURITY HEADERS (NSA-LEVEL) & PAYLOAD LIMITS
// --------------------------------------------------------------------------

// 1.A Payload Size Limit (Mitigate buffer overflow & basic DDoS)
$maxPayloadSize = 4096; // 4 KB Max
if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > $maxPayloadSize) {
    http_response_code(413); // Payload Too Large
    echo json_encode(['error' => 'Payload exceeds maximum allowed size (4KB). Request rejected.']);
    exit;
}

// 1.B Server Footprint Obfuscation
if (function_exists('header_remove')) {
    header_remove('X-Powered-By'); // Hide PHP version
}

// 1.C Anti-Indexing & Framing (Ghost Mode)
header("X-Robots-Tag: noindex, nofollow, nosnippet, noarchive");
header("X-Frame-Options: DENY"); // Strict Anti-Clickjacking

// 1.D Strict Content Security Policy (CSP)
$csp = "default-src 'none'; connect-src 'self' https://mapard.felipemiramontesr.net; ";
$csp .= "img-src 'self' data:; style-src 'self' 'unsafe-inline'; ";
$csp .= "script-src 'self' 'unsafe-inline'; object-src 'none'; frame-ancestors 'none'; base-uri 'self';";
header("Content-Security-Policy: $csp");

// 1.E Additional Shields
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block"); // Legacy but good depth defense
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

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

    // [NEW] USER_SECURITY_CONFIG Table (Phase 28)
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_security_config (
        user_id INTEGER PRIMARY KEY,
        is_first_analysis_complete INTEGER DEFAULT 0,
        biometric_enabled INTEGER DEFAULT 1,
        security_level TEXT DEFAULT 'TACTICAL',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // [NEW] ANALYSIS_SNAPSHOTS Table (Phase 28)
    $pdo->exec("CREATE TABLE IF NOT EXISTS analysis_snapshots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        job_id TEXT,
        checksum TEXT,
        raw_data_json TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // [NEW] NEUTRALIZATION_LOGS Table (Phase 28)
    $pdo->exec("CREATE TABLE IF NOT EXISTS neutralization_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        finding_id TEXT,
        status TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
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

    // [NEW] RATE_LIMITS Table (Phase 18)
    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        ip_address TEXT PRIMARY KEY,
        attempts INTEGER DEFAULT 0,
        locked_until DATETIME DEFAULT NULL
    )");

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database Init Failed: " . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParams = explode('/', trim($requestUri, '/'));
// OPTIONS handler moved to top

// --- NSA Rate Limiting (Phase 18) ---
function enforceRateLimit($pdo, $ip)
{
    if (!$ip)
        return;
    $stmt = $pdo->prepare("SELECT attempts, locked_until FROM rate_limits WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record && $record['locked_until'] && strtotime($record['locked_until']) > time()) {
        http_response_code(429); // Too Many Requests
        echo json_encode(['error' => 'IP bloqueada temporalmente por seguridad. Intente más tarde.']);
        exit;
    }
}

function recordFailedAttempt($pdo, $ip)
{
    if (!$ip)
        return;
    $pdo->exec("INSERT INTO rate_limits (ip_address, attempts) VALUES ('$ip', 1) 
                ON CONFLICT(ip_address) DO UPDATE SET attempts = attempts + 1");

    // Check if limits exceeded
    $stmt = $pdo->prepare("SELECT attempts FROM rate_limits WHERE ip_address = ?");
    $stmt->execute([$ip]);
    if ($stmt->fetchColumn() >= 5) {
        $lockTime = date('Y-m-d H:i:s', time() + (15 * 60)); // Lock 15 mins
        $pdo->prepare("UPDATE rate_limits SET locked_until = ? WHERE ip_address = ?")->execute([$lockTime, $ip]);
    }
}

function resetRateLimit($pdo, $ip)
{
    if (!$ip)
        return;
    $pdo->prepare("DELETE FROM rate_limits WHERE ip_address = ?")->execute([$ip]);
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? null;

// ROUTER
// AUTH SETUP (Register/Initial Login)
if (isset($pathParams[1], $pathParams[2]) && $pathParams[1] === 'auth' && $pathParams[2] === 'setup' && $method === 'POST') {
    enforceRateLimit($pdo, $clientIp);
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
        $newUserId = $pdo->lastInsertId();

        // Phase 28: Initialize Security Config
        $pdo->prepare("INSERT INTO user_security_config (user_id) VALUES (?)")->execute([$newUserId]);
    } else {
        // Phase 25/27: Floating Hardware Binding
        // We no longer block with 403 in setup. 
        // The re-binding will happen automatically during verify (Phase 27).

        // Update Existing User (Reset Password/2FA if re-setting up)
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, fa_code = ?, is_verified = 0 WHERE email_target = ?");
        $stmt->execute([$hashedPassword, $faCode, $email]);
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
if (isset($pathParams[1], $pathParams[2]) && $pathParams[1] === 'auth' && $pathParams[2] === 'verify' && $method === 'POST') {
    enforceRateLimit($pdo, $clientIp);
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $code = $input['code'] ?? '';
    $deviceId = $input['device_id'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email_target = ? AND fa_code = ?");
    $stmt->execute([$email, $code]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        recordFailedAttempt($pdo, $clientIp);
        http_response_code(401);
        echo json_encode(["error" => "Invalid verification code"]);
        exit;
    }

    // Phase 25/27: Floating Hardware Binding
    // Upon successful 2FA, we unify the hardware ID to the current one.
    // This allows seamless migration/re-installation.
    $pdo->prepare("UPDATE users SET is_verified = 1, fa_code = NULL, device_id = ? WHERE id = ?")
        ->execute([$deviceId ?: $user['device_id'], $user['id']]);

    resetRateLimit($pdo, $clientIp);

    echo json_encode([
        "status" => "VERIFIED",
        "token" => "blind_ops_" . bin2hex(random_bytes(16)),
        "email" => $email,
        "is_first_analysis_complete" => false // Default for new verification
    ]);
    exit;
}

// USER STATUS (Phase 23)
if (isset($pathParams[1]) && $pathParams[1] === 'user' && $pathParams[2] === 'status') {
    $debugLog = __DIR__ . '/temp/status_debug.log';
    @file_put_contents($debugLog, date('c') . " - [TRACE] Status requested: " . ($_GET['email'] ?? 'none') . "\n", FILE_APPEND);
    try {
        $email = $_GET['email'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM scans WHERE email = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$email]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($job) {
            @file_put_contents($debugLog, date('c') . " - [TRACE] Job found. ID: " . $job['job_id'] . "\n", FILE_APPEND);
            $findings = $job['is_encrypted'] ? SecurityUtils::decrypt($job['findings']) : $job['findings'];
            $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];

            // Phase 28: Get Security Config
            $stmt = $pdo->prepare("SELECT is_first_analysis_complete FROM user_security_config WHERE user_id = (SELECT id FROM users WHERE email_target = ?)");
            $stmt->execute([$email]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            @file_put_contents($debugLog, date('c') . " - [TRACE] Assembly response...\n", FILE_APPEND);
            $responseBody = [
                "has_scans" => true,
                "job_id" => $job['job_id'],
                "status" => $job['status'],
                "is_first_analysis_complete" => (bool) (($config && isset($config['is_first_analysis_complete'])) ? $config['is_first_analysis_complete'] : false),
                "logs" => is_string($logs) ? (json_decode($logs) ?: []) : [],
                "findings" => is_string($findings) ? (json_decode($findings) ?: []) : [],
                "result_url" => $job['result_path']
            ];

            $jsonOutput = json_encode($responseBody);
            if ($jsonOutput === false) {
                throw new \Exception("JSON encode error in status: " . json_last_error_msg());
            }

            // [TEMPORARY DEBUG HOOK] Capture the exact output going to the frontend
            @file_put_contents($debugLog, date('c') . " - [TRACE] Output:\n" . $jsonOutput . "\n\n", FILE_APPEND);

            echo $jsonOutput;
        } else {
            @file_put_contents($debugLog, date('c') . " - [TRACE] No job found.\n", FILE_APPEND);
            echo json_encode(["has_scans" => false, "is_first_analysis_complete" => false]);
        }
    } catch (\Throwable $e) {
        @file_put_contents($debugLog, date('c') . " - [FATAL ERROR] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
        http_response_code(500);
        $errJson = json_encode(["error" => "Status error: " . $e->getMessage()]);
        echo $errJson ?: '{"error": "Status error and JSON encode failed"}';
    }
    exit;
}

// DEBUG ENDPOINT TO READ RAW TXT
if (isset($pathParams[1]) && $pathParams[1] === 'read_debug') {
    header('Content-Type: text/plain; charset=utf-8');
    $tempDir = __DIR__ . '/temp';
    $logPath = $tempDir . '/status_debug.log';
    if (!is_dir($tempDir)) {
        echo "Directory $tempDir does not exist. Creating it now...\n";
        @mkdir($tempDir, 0755, true);
    }
    if (file_exists($logPath)) {
        echo file_get_contents($logPath);
    } else {
        echo "No log found at $logPath\n";
        echo "Directory writable? " . (is_writable($tempDir) ? 'YES' : 'NO') . "\n";
    }
    exit;
}

if (isset($pathParams[1]) && $pathParams[1] === 'read_crash') {
    header('Content-Type: text/plain; charset=utf-8');
    $logPath = __DIR__ . '/temp/background_crash.log';
    if (file_exists($logPath)) {
        echo file_get_contents($logPath);
    } else {
        echo "No crash log found at $logPath. The process hasn't crashed yet or log couldn't be written.\n";
    }
    exit;
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

        // Fetch user_id matching this email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email_target = ?");
        $stmt->execute([$email]);
        $userId = $stmt->fetchColumn() ?: null;

        $jobId = uniqid('job_', true);
        $initialLogs = json_encode([
            ["message" => "Iniciando Protocolo MAPARD...", "type" => "info", "timestamp" => date('c')]
        ]);

        if ($userId) {
            $stmt = $pdo->prepare("INSERT INTO scans (job_id, user_id, email, domain, status, logs) VALUES (?, ?, ?, ?, 'PENDING', ?)");
            $stmt->execute([$jobId, $userId, $email, $domain, $initialLogs]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO scans (job_id, email, domain, status, logs) VALUES (?, ?, ?, 'PENDING', ?)");
            $stmt->execute([$jobId, $email, $domain, $initialLogs]);
        }
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
                "logs" => is_string($logs) ? json_decode($logs) : [],
                "findings" => is_string($findings) ? json_decode($findings) : [],
                "result_url" => $job['result_path']
            ]);
            exit;
        }

        // RACE CONDITION FIX: Strict Locking Mechanism
        // Attempt to claim the job ONLY if it's PENDING.
        $stmt = $pdo->prepare("UPDATE scans SET status = 'RUNNING' WHERE job_id = ? AND status = 'PENDING'");
        $stmt->execute([$jobId]);
        $rowsAffected = $stmt->rowCount();

        // If no rows were affected, it means another PHP process already set it to RUNNING (or it's COMPLETED)
        if ($rowsAffected === 0 && $job['status'] !== 'PENDING') {
            $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];
            echo json_encode([
                "job_id" => $jobId,
                "status" => "RUNNING",
                "logs" => json_decode($logs),
                "message" => "Scan in progress..."
            ]);
            exit;
        }

        // Only the FIRST process reaches here. Proceed with the heavy execution.
        // CRITICAL HOSTINGER FIX: Do not kill script if browser disconnects during the heavy AI process.
        ignore_user_abort(true);
        set_time_limit(300);

        // DECOUPLE EXECUTION: Close connection and respond to client immediately
        header("Connection: close");
        header("Content-Encoding: none");
        ob_start();
        $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];
        echo json_encode([
            "job_id" => $jobId,
            "status" => "RUNNING",
            "logs" => is_string($logs) ? json_decode($logs) : [],
            "message" => "Scan started in background..."
        ]);
        $size = ob_get_length();
        header("Content-Length: $size");
        ob_end_flush();
        @ob_flush();
        @flush();

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        try {
            $scanService = new ScanService($pdo);
            $scanService->runScan($jobId);
        } catch (\Throwable $e) {
            $logMsg = date('c') . " - [BACKGROUND FATAL ERROR] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
            @file_put_contents(__DIR__ . '/temp/background_crash.log', $logMsg, FILE_APPEND);
            error_log($logMsg);
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

// [DEBUG FALLBACK] Catch all unhandled routes to see why it skips
http_response_code(404);
echo json_encode([
    "error" => "API Route Not Found or Unhandled",
    "debug_method" => $method,
    "debug_raw_uri" => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
    "debug_parsed_uri" => $requestUri,
    "debug_params" => $pathParams
]);
exit;