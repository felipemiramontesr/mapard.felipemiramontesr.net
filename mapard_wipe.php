<?php
/**
 * MAPARD WIPE DATABASE (Utility)
 * Clears all user accounts, scans, and snapshots from the SQLite database.
 * Useful for development and testing.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

echo "========================================================\n";
echo "               MAPARD DATABASE CLEANER\n";
echo "========================================================\n\n";

try {
    $dbPath = __DIR__ . '/api/mapard_v2.sqlite';
    echo "1. Locating Database: $dbPath\n";

    if (!file_exists($dbPath)) {
        echo "   [ERROR] Database not found. Nothing to wipe.\n";
        exit;
    }

    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "2. Connection Established.\n";

    $tablesToWipe = [
        'user_security_config',
        'analysis_snapshots',
        'neutralization_logs',
        'scans',
        'users' // Wiping users last due to potential foreign keys
    ];

    echo "3. Executing WIPE on tables...\n";
    foreach ($tablesToWipe as $table) {
        $stmt = $pdo->prepare("DELETE FROM $table");
        $stmt->execute();
        echo "   -> Deleted " . $stmt->rowCount() . " rows from '$table'\n";
    }

    echo "\n========================================================\n";
    echo "  [OK] DATABASE SUCCESSFULLY RESET TO FACTORY SETTINGS.\n";
    echo "========================================================\n";

} catch (\Throwable $e) {
    echo "\n[FATAL ERROR] " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>