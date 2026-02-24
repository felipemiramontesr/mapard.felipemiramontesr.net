<?php
/**
 * MAPARD DEEP DOCTOR (V2 - Destructive Mode)
 * Bypasses all routing and executes atomic steps to isolate Hostinger/PHP Fatal Errors.
 */

// 1. FORCE MAXIMUM VISIBILITY
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Force text plain so the browser doesn't try to parse broken HTML
header('Content-Type: text/plain; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

echo "========================================================\n";
echo "           MAPARD DEEP DOCTOR (V2) - TACTICAL WIPE\n";
echo "========================================================\n\n";

$targetEmail = 'felipemiramontesr@gmail.com';

// ---------------------------------------------------------
// STEP 1: HOSTINGER PHP ENVIRONMENT CHECKS
// ---------------------------------------------------------
echo "[STEP 1] Environment Evaluation...\n";
try {
    echo "  -> PHP Version: " . phpversion() . "\n";
    echo "  -> Memory Limit: " . ini_get('memory_limit') . "\n";
    echo "  -> Max Execution Time: " . ini_get('max_execution_time') . "s\n";

    $requiredExtensions = ['pdo', 'pdo_sqlite', 'json', 'openssl'];
    $missingExts = [];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $missingExts[] = $ext;
        }
    }
    if (!empty($missingExts)) {
        echo "  [FATAL] Missing required PHP extensions: " . implode(', ', $missingExts) . "\n";
        exit("ABORTING: Cannot proceed without critical extensions.");
    } else {
        echo "  [OK] All critical PHP extensions loaded.\n";
    }
} catch (\Throwable $e) {
    echo "  [CRASH IN STEP 1]: " . $e->getMessage() . "\n";
    exit;
}
echo "--------------------------------------------------------\n\n";

// ---------------------------------------------------------
// STEP 2: RAW DATABASE CONNECTION & WIPE (DESTRUCTIVE)
// ---------------------------------------------------------
echo "[STEP 2] Database Connection & Tactical Wipe...\n";
try {
    $dbPath = __DIR__ . '/api/mapard_v2.sqlite';
    echo "  -> Target DB Path: $dbPath\n";

    if (!file_exists($dbPath)) {
        echo "  [FATAL] SQLite Database file not found at path.\n";
        exit;
    }
    echo "  -> DB Permissions: " . substr(sprintf('%o', fileperms($dbPath)), -4) . "\n";

    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "  [OK] PDO SQLite Connection Established.\n";

    // Wiping User Config Options
    echo "  -> Executing WIPE on 'user_security_config'...\n";
    $stmt1 = $pdo->prepare("DELETE FROM user_security_config");
    $stmt1->execute();
    echo "     Rows deleted: " . $stmt1->rowCount() . "\n";

    // Wiping Analysis Snapshots (Findings)
    echo "  -> Executing WIPE on 'analysis_snapshots'...\n";
    $stmt2 = $pdo->prepare("DELETE FROM analysis_snapshots");
    $stmt2->execute();
    echo "     Rows deleted: " . $stmt2->rowCount() . "\n";

    // Wiping Scans (Job Logs)
    echo "  -> Executing WIPE on 'scans'...\n";
    $stmt3 = $pdo->prepare("DELETE FROM scans");
    $stmt3->execute();
    echo "     Rows deleted: " . $stmt3->rowCount() . "\n";

    echo "  [OK] DATABASE WIPED SUCCESSFULLY.\n";

} catch (\Throwable $e) {
    echo "  [CRASH IN STEP 2]: " . $e->getMessage() . "\n";
    echo "  Trace: " . $e->getTraceAsString() . "\n";
    exit;
}
echo "--------------------------------------------------------\n\n";

// ---------------------------------------------------------
// STEP 3: MOCK DATA INJECTION FOR TARGET
// ---------------------------------------------------------
echo "[STEP 3] Injecting Mock Data for Target: $targetEmail ...\n";
try {
    require_once __DIR__ . '/api/vendor/autoload.php';
    require_once __DIR__ . '/api/config.php';
    require_once __DIR__ . '/api/services/SecurityUtils.php';

    // Must alias the class since we aren't in the namespace
    $securityUtilsClass = 'MapaRD\\Services\\SecurityUtils';

    // 1. Ensure Target User Exists
    echo "  -> Checking if user '$targetEmail' exists in 'users' table...\n";
    $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email_target = ?");
    $stmtUser->execute([$targetEmail]);
    $userId = $stmtUser->fetchColumn();

    if (!$userId) {
        echo "  [i] User not found. Creating mock user...\n";
        $stmtInsertUser = $pdo->prepare("INSERT INTO users (email_target, master_password_hash, operator_name) VALUES (?, ?, ?)");
        $stmtInsertUser->execute([$targetEmail, password_hash('TestPass123!', PASSWORD_ARGON2ID), 'DEEP_DOCTOR_ADMIN']);
        $userId = $pdo->lastInsertId();
        echo "  [OK] Mock User injected. ID: $userId\n";
    } else {
        echo "  [OK] User found. ID: $userId\n";
    }

    // 2. Inject a Mock Completed Scan
    $jobId = 'job_deep_doctor_' . time();
    $mockLogs = json_encode([
        ["id" => 1, "message" => "Deep Doctor Initialized", "type" => "info", "timestamp" => date('Y-m-d H:i:s')],
        ["id" => 2, "message" => "Mock Scan Completed", "type" => "success", "timestamp" => date('Y-m-d H:i:s')]
    ]);

    // Test Encrypted Payloads
    $encryptedLogs = $securityUtilsClass::encrypt($mockLogs);

    // JSON Findings WITH special characters designed to break bad parsers
    $mockFindings = json_encode([
        [
            "id" => "VEC-1",
            "severity" => "CRITICAL",
            "title" => "Doctor Test Vector (Ñ, Á, €)",
            "description" => "Test 'quotes' and \"double quotes\" and \n newlines.",
            "action" => "Ignore"
        ]
    ]);
    $encryptedFindings = $securityUtilsClass::encrypt($mockFindings);

    echo "  -> Injecting mock scan status...\n";
    $stmtScan = $pdo->prepare("INSERT INTO scans (job_id, user_id, email, status, logs, findings, result_path, is_encrypted, created_at) VALUES (?, ?, ?, 'COMPLETED', ?, ?, '/mock/path.pdf', 1, CURRENT_TIMESTAMP)");
    $stmtScan->execute([$jobId, $userId, $targetEmail, $encryptedLogs, $encryptedFindings]);
    echo "  [OK] Mock scan injected. Job ID: $jobId\n";

} catch (\Throwable $e) {
    echo "  [CRASH IN STEP 3]: " . $e->getMessage() . "\n";
    echo "  Trace: " . $e->getTraceAsString() . "\n";
    exit;
}
echo "--------------------------------------------------------\n\n";


// ---------------------------------------------------------
// STEP 4: SIMULATE API STATUS ENDPOINT DECRYPTION
// ---------------------------------------------------------
echo "[STEP 4] Simulating /api/user/status Decryption Phase...\n";
try {
    echo "  -> Querying scans for $targetEmail...\n";
    $stmtFetch = $pdo->prepare("SELECT * FROM scans WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    $stmtFetch->execute([$targetEmail]);
    $job = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        echo "  [FATAL] No job fetched immediately after insertion.\n";
        exit;
    }

    echo "  -> Job fetched from DB. Decrypting payloads...\n";

    $decryptedFindings = $job['is_encrypted'] ? $securityUtilsClass::decrypt($job['findings']) : $job['findings'];
    if ($decryptedFindings === false || $decryptedFindings === null) {
        echo "  [FATAL] DECRYPTION FAILED FOR FINDINGS.\n";
        exit;
    }
    echo "  [OK] Findings Decrypted: " . substr($decryptedFindings, 0, 50) . "...\n";

    $decryptedLogs = $job['is_encrypted'] ? $securityUtilsClass::decrypt($job['logs']) : $job['logs'];
    if ($decryptedLogs === false || $decryptedLogs === null) {
        echo "  [FATAL] DECRYPTION FAILED FOR LOGS.\n";
        exit;
    }
    echo "  [OK] Logs Decrypted: " . substr($decryptedLogs, 0, 50) . "...\n";


} catch (\Throwable $e) {
    echo "  [CRASH IN STEP 4]: " . $e->getMessage() . "\n";
    echo "  Trace: " . $e->getTraceAsString() . "\n";
    exit;
}
echo "--------------------------------------------------------\n\n";

// ---------------------------------------------------------
// STEP 5: FINAL JSON SERIALIZATION KAMIKAZE
// ---------------------------------------------------------
echo "[STEP 5] Assembling Payload & JSON Encoding (KAMIKAZE)...\n";
try {
    $responseBody = [
        "has_scans" => true,
        "job_id" => $job['job_id'],
        "status" => $job['status'],
        "is_first_analysis_complete" => false,
        "logs" => json_decode($decryptedLogs) ?: [],
        "findings" => json_decode($decryptedFindings) ?: [],
        "result_url" => $job['result_path']
    ];

    echo "  -> Attempting json_encode on complex array...\n";
    $jsonOutput = json_encode($responseBody);

    if ($jsonOutput === false) {
        echo "  [FATAL] json_encode() failed!\n";
        echo "  -> Error Msg: " . json_last_error_msg() . "\n";
        echo "  -> Error Code: " . json_last_error() . "\n";
        exit;
    }

    echo "  [OK] JSON ENCODE SUCCESSFUL.\n";
    echo "  -> Output Length: " . strlen($jsonOutput) . " bytes\n";

    echo "\n========================================================\n";
    echo "  ALL TESTS PASSED. NO SILENT FATAL ERRORS DETECTED.\n";
    echo "========================================================\n";

} catch (\Throwable $e) {
    echo "  [CRASH IN STEP 5]: " . $e->getMessage() . "\n";
    echo "  Trace: " . $e->getTraceAsString() . "\n";
    exit;
}

?>