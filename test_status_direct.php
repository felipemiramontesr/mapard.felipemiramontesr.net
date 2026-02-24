<?php
use MapaRD\Services\SecurityUtils;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');
echo "--- DIRECT STATUS TEST ---\n\n";

try {
    echo "1. Loading dependencies...\n";
    require_once __DIR__ . '/api/vendor/autoload.php';
    require_once __DIR__ . '/api/config.php';
    require_once __DIR__ . '/api/services/SecurityUtils.php';

    $email = 'felipe@ejemplo.com'; // Hardcoded for test
    echo "2. Connecting to DB...\n";
    if (!isset($pdo) || !$pdo) {
        $dbPath = __DIR__ . '/api/mapard_v2.sqlite';
        echo "   [i] Initializing PDO locally ($dbPath) ...\n";
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    echo "3. Querying scans for $email...\n";
    $stmt = $pdo->prepare("SELECT * FROM scans WHERE email = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($job) {
        echo "   [+] Job found: " . $job['job_id'] . "\n";

        echo "4. Decrypting findings/logs...\n";
        $findings = $job['is_encrypted'] ? SecurityUtils::decrypt($job['findings']) : $job['findings'];
        $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];

        echo "5. Querying security config...\n";
        $stmt = $pdo->prepare("SELECT is_first_analysis_complete FROM user_security_config WHERE user_id = (SELECT id FROM users WHERE email_target = ?)");
        $stmt->execute([$email]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "6. Assembling response...\n";
        $responseBody = [
            "has_scans" => true,
            "job_id" => $job['job_id'],
            "status" => $job['status'],
            "is_first_analysis_complete" => (bool) (($config && isset($config['is_first_analysis_complete'])) ? $config['is_first_analysis_complete'] : false),
            "logs" => is_string($logs) ? (json_decode($logs) ?: []) : [],
            "findings" => is_string($findings) ? (json_decode($findings) ?: []) : [],
            "result_url" => $job['result_path']
        ];

        echo "7. JSON Encoding...\n";
        $jsonOutput = json_encode($responseBody);

        if ($jsonOutput === false) {
            echo "   [-] JSON ENCODE FAILED: " . json_last_error_msg() . "\n";
            echo "   [-] Raw Findings type: " . gettype($findings) . "\n";
            echo "   [-] Raw Logs type: " . gettype($logs) . "\n";
        } else {
            echo "   [+] Output generated successfully. Length: " . strlen($jsonOutput) . " bytes\n";
            // Print beginning and end to sanity check
            echo "   [+] Preview: " . substr($jsonOutput, 0, 100) . " ... " . substr($jsonOutput, -100) . "\n";
        }
    } else {
        echo "   [-] No job found in database for $email\n";
    }

    echo "\n--- TEST COMPLETE ---\n";
} catch (\Throwable $e) {
    echo "\n\n*** FATAL EXCEPTION CAUGHT ***\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
?>