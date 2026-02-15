<?php
// api/doctor.php
// SAFETY MODE: Plain text output, no file inclusion/execution.
header("Content-Type: text/plain");
ini_set('display_errors', 1);

echo "--- MAPARD HOSTINGER DIAGNOSTIC ---\n";
echo "Timestamp: " . date('c') . "\n\n";

// 1. Check Directory Structure
echo "[1] CHECKING API FOLDER:\n";
$files = scandir(__DIR__);
foreach ($files as $f) {
    if ($f === '.' || $f === '..')
        continue;
    $size = filesize(__DIR__ . '/' . $f);
    echo " - $f ($size bytes)\n";
}
echo "\n";

// 2. Check Services Folder
echo "[2] CHECKING SERVICES FOLDER:\n";
$serviceDir = __DIR__ . '/services';
if (is_dir($serviceDir)) {
    $sFiles = scandir($serviceDir);
    foreach ($sFiles as $f) {
        if ($f === '.' || $f === '..')
            continue;
        $path = $serviceDir . '/' . $f;
        $hash = md5_file($path);
        echo " - $f\n";
        echo "   Size: " . filesize($path) . " bytes\n";
        echo "   MD5:  $hash\n";
    }
} else {
    echo "ERROR: 'services' directory NOT FOUND.\n";
}
echo "\n";

// 3. Check Config Integrity (Static)
echo "[3] CHECKING CONFIG.PHP (Static):\n";
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    echo "Config exists.\n";
    $content = file_get_contents($configPath);
    echo "Length: " . strlen($content) . " bytes\n";
    // Check for common issues
    if (strpos($content, '<?php') === false)
        echo "WARNING: Missing <?php tag?\n";
    if (strpos($content, 'GEMINI_API_KEY') === false)
        echo "WARNING: GEMINI_API_KEY constant missing?\n";
} else {
    echo "ERROR: config.php NOT FOUND.\n";
}
echo "\n";

echo "--- END DIAGNOSTIC ---\n";
?>