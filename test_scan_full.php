<?php
/**
 * MAPARD - ISOLATED SCANSERVICE TESTER
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

// Authentication check removed for local CLI testing

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/services/ScanService.php';

use MapaRD\Services\ScanService;

echo "================================================================\n";
echo "🔍 ISOLATED SCANSERVICE PIPELINE TEST\n";
echo "================================================================\n\n";

$dbPath = __DIR__ . '/api/mapard_v2.sqlite';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ DB Connected.\n";

    // We need a PENDING or RUNNING job to test
    $stmt = $pdo->query("SELECT job_id, email, status FROM scans WHERE status IN ('PENDING', 'RUNNING') ORDER BY created_at DESC LIMIT 1");
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        die("❌ No PENDING/RUNNING jobs found in DB to test. Start a scan in the app first.\n");
    }

    $jobId = $job['job_id'];
    echo "Found Job: {$jobId} ({$job['email']}) [{$job['status']}]\n";

    echo "\nInitiating ScanService::runScan($jobId)...\n\n";

    $scanService = new ScanService($pdo);
    $start = microtime(true);

    $result = $scanService->runScan($jobId);

    $end = microtime(true);
    $timeTaken = round($end - $start, 2);

    echo "✅ SUCCESS: runScan completed in {$timeTaken}s\n\n";
    echo "RESULT PAYLOAD (First 500 chars):\n";
    echo substr(json_encode($result), 0, 500) . "...\n";

} catch (Throwable $e) {
    echo "\n❌ FATAL EXCEPTION/ERROR CAUGHT IN SCANSERVICE PIPELINE:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n================================================================\n";
echo "TEST COMPLETED.\n";
