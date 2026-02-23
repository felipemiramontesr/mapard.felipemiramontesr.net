<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/api/vendor/autoload.php';
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/services/SecurityUtils.php';

use MapaRD\Services\SecurityUtils;

echo "================================================================\n";
echo "🔍 ISOLATED STATUS ENDPOINT TESTER\n";
echo "================================================================\n\n";

try {
    $dbPath = __DIR__ . '/api/mapard_v2.sqlite';
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $email = 'felipemiramontesr@gmail.com';
    $stmt = $pdo->prepare("SELECT * FROM scans WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($job) {
        echo "Found Last Job: " . $job['job_id'] . "\n";
        echo "Status: " . $job['status'] . "\n";
        echo "Is Encrypted: " . $job['is_encrypted'] . "\n\n";

        echo "Attempting Decryption...\n";
        $findings = $job['is_encrypted'] ? SecurityUtils::decrypt($job['findings']) : $job['findings'];
        $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];
        echo "Decryption OK.\n\n";

        echo "Attempting Config Fetch...\n";
        $stmt = $pdo->prepare("SELECT is_first_analysis_complete FROM user_security_config WHERE user_id = (SELECT id FROM users WHERE email_target = ?)");
        $stmt->execute([$email]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Config Fetch OK.\n\n";

        echo "Attempting JSON Encode...\n";
        $data = [
            "has_scans" => true,
            "job_id" => $job['job_id'],
            "status" => $job['status'],
            "is_first_analysis_complete" => (bool) ($config['is_first_analysis_complete'] ?? false),
            "logs" => json_decode($logs),
            "findings" => json_decode($findings),
            "result_url" => $job['result_path']
        ];
        $json = json_encode($data);
        if ($json === false) {
            echo "JSON Encode FAILED: " . json_last_error_msg() . "\n";
        } else {
            echo "JSON Encode OK. Length: " . strlen($json) . " bytes\n";
        }

        echo "\nChecking api/scan/ COMPLETED handler...\n";
        $data2 = [
            "job_id" => $job['job_id'],
            "status" => "COMPLETED",
            "logs" => json_decode($logs),
            "findings" => json_decode($findings),
            "result_url" => $job['result_path']
        ];
        $json2 = json_encode($data2);
        if ($json2 === false) {
            echo "JSON Encode 2 FAILED: " . json_last_error_msg() . "\n";
        } else {
            echo "JSON Encode 2 OK. Length: " . strlen($json2) . " bytes\n";
        }

    } else {
        echo "No jobs found for $email.\n";
    }

} catch (Throwable $e) {
    echo "\n❌ FATAL EXCEPTION/ERROR CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n================================================================\n";
echo "TEST COMPLETED.\n";
