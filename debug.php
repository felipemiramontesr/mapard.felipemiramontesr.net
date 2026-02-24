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

echo "\n3. Checking CRASH log file: $tempDir/background_crash.log\n";
$crashLog = $tempDir . '/background_crash.log';
if (file_exists($crashLog)) {
    echo "   [+] Crash log exists. Size: " . filesize($crashLog) . " bytes\n";
    echo "\n--- CRASH LOG CONTENT START ---\n\n";
    echo file_get_contents($crashLog);
    echo "\n\n--- CRASH LOG CONTENT END ---\n";
} else {
    echo "   [-] CRASH LOG NOT FOUND. No fatal errors caught yet.\n";
}

echo "\n4. Checking GEMINI log file: $tempDir/gemini_debug.log\n";
$geminiLog = $tempDir . '/gemini_debug.log';
if (file_exists($geminiLog)) {
    echo "   [+] Gemini log exists. Size: " . filesize($geminiLog) . " bytes\n";
    echo "\n--- GEMINI LOG CONTENT START ---\n\n";
    echo file_get_contents($geminiLog);
    echo "\n\n--- GEMINI LOG CONTENT END ---\n";
} else {
    echo "   [-] GEMINI LOG NOT FOUND. The AI process likely hasn't started or crashed before writing.\n";
}
?>