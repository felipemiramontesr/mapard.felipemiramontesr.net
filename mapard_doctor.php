<?php
// MAPARD DOCTOR - LIVE DB DIAGNOSTICS & FATAL ERROR CATCHER
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

try {
    // 1. Check PHP Environment
    $diagnostics = [
        'php_version' => PHP_VERSION,
        'sqlite_loaded' => extension_loaded('pdo_sqlite'),
        'curl_loaded' => extension_loaded('curl'),
        'memory_limit' => ini_get('memory_limit')
    ];

    $dbPath = __DIR__ . '/api/mapard_v2.sqlite';
    if (!file_exists($dbPath)) {
        throw new \Exception("DB file not found at $dbPath");
    }

    $diagnostics['db_writable'] = is_writable($dbPath);
    $diagnostics['dir_writable'] = is_writable(__DIR__);

    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $diagnostics['sqlite_version'] = $pdo->query('select sqlite_version()')->fetchColumn();

    // 2. Check Schemas
    $tables = ['users', 'scans', 'user_security_config', 'analysis_snapshots'];
    $diagnostics['tables'] = [];
    foreach ($tables as $t) {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'");
        $diagnostics['tables'][$t] = $stmt->fetch() ? 'Exists' : 'Missing';

        if ($diagnostics['tables'][$t] === 'Exists') {
            $cols = $pdo->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC);
            $diagnostics['schemas'][$t] = array_map(function ($c) {
                return $c['name'] . ' (' . $c['type'] . ')';
            }, $cols);
        }
    }

    // 3. Simulate The Scan Write Cycle
    $email = $_GET['email'] ?? 'test@mapard.com';
    $diagnostics['test_target'] = $email;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email_target = ?");
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn() ?: null;
    $diagnostics['fetched_user_id'] = $userId;

    $jobId = uniqid('doctor_', true);

    $pdo->beginTransaction();

    try {
        if ($userId) {
            $stmt = $pdo->prepare("INSERT INTO scans (job_id, user_id, email, domain, status, logs) VALUES (?, ?, ?, ?, 'TEST', '[]')");
            $stmt->execute([$jobId, $userId, $email, 'mapard.com']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO scans (job_id, email, domain, status, logs) VALUES (?, ?, ?, 'TEST', '[]')");
            $stmt->execute([$jobId, $email, 'mapard.com']);
        }
        $diagnostics['insert_scan'] = 'SUCCESS';

        if ($userId) {
            $configSql = "INSERT INTO user_security_config (user_id, is_first_analysis_complete, updated_at) 
                          VALUES (?, 1, CURRENT_TIMESTAMP) 
                          ON CONFLICT(user_id) DO UPDATE SET 
                          is_first_analysis_complete=1, updated_at=CURRENT_TIMESTAMP";
            $pdo->prepare($configSql)->execute([$userId]);
            $diagnostics['insert_config'] = 'SUCCESS';

            $snapshotSql = "INSERT INTO analysis_snapshots (user_id, job_id, checksum, raw_data_json) VALUES (?, ?, ?, ?)";
            $pdo->prepare($snapshotSql)->execute([$userId, $jobId, 'test_checksum', '{}']);
            $diagnostics['insert_snapshot'] = 'SUCCESS';
        }

        $pdo->rollBack(); // Keep DB clean
        $diagnostics['transaction'] = 'ROLLED_BACK_CLEANLY_NO_ERRORS';
    } catch (\Throwable $e) {
        $pdo->rollBack();
        $diagnostics['transaction_error'] = "DB_WRITE_ERROR: " . $e->getMessage();
        $diagnostics['transaction_trace'] = $e->getTraceAsString();
    }

    // 4. Capture latest hostinger error logs
    $errorLogFile = __DIR__ . '/../error_log';
    if (file_exists($errorLogFile)) {
        $lines = file($errorLogFile);
        $diagnostics['hostinger_error_log'] = array_slice($lines, -10);
    } else {
        $diagnostics['hostinger_error_log'] = "Not found at $errorLogFile";
    }

    echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "FATAL_ERROR" => $e->getMessage(),
        "FILE" => $e->getFile(),
        "LINE" => $e->getLine(),
        "TRACE" => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>