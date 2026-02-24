<?php
// Bypassing caches
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: text/plain; charset=utf-8');

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "--- MAPARD DEBUGGER (V1) ---\n\n";

$tempDir = __DIR__ . '/api/temp';
$logPath = $tempDir . '/status_debug.log';

echo "1. Checking directory: $tempDir\n";
if (!is_dir($tempDir)) {
    echo "   [!] Directory DOES NOT exist. Trying to create...\n";
    if (@mkdir($tempDir, 0755, true)) {
        echo "   [+] Directory created.\n";
    } else {
        echo "   [-] Failed to create directory. Permission denied.\n";
    }
} else {
    echo "   [+] Directory exists.\n";
    echo "   [i] Directory writable? " . (is_writable($tempDir) ? "YES" : "NO") . "\n";
}

echo "\n2. Checking log file: $logPath\n";
if (file_exists($logPath)) {
    echo "   [+] Log exists. Size: " . filesize($logPath) . " bytes\n";
    echo "\n--- LOG CONTENT START ---\n\n";
    echo file_get_contents($logPath);
    echo "\n\n--- LOG CONTENT END ---\n";
} else {
    echo "   [-] LOG FILE NOT FOUND.\n";
    echo "       This means api/index.php hasn't been able to write the log.\n";
}
?>