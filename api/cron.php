<?php

/**
 * MAPARD: Automated Intelligence Engine (Background Cron)
 * Trigger this script via Task Scheduler every hour.
 */

// Load Autoloader
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';

use MapaRD\Services\ScanService;

// DB Initialization
$dbPath = __DIR__ . '/mapard_v2.sqlite';
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "[CRON] Starting Automated Intelligence Sequence...\n";

    $scanService = new ScanService($pdo);
    $scanService->runAutomatedScans();

    echo "[CRON] Sequence Finished.\n";

} catch (Exception $e) {
    error_log("[CRON FAILURE] " . $e->getMessage());
    echo "[CRON ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
