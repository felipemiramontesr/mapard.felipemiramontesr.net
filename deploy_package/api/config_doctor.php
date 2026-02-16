<?php
// api/config_doctor.php
// SAFETY MODE: Detects syntax errors without executing the bad code.
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>MAPARD Config Doctor</h1>";

$files = ['config.php', 'services/GeminiService.php'];

foreach ($files as $file) {
    echo "<h2>Checking $file (Static Analysis)...</h2>";
    $path = __DIR__ . '/' . $file;

    if (!file_exists($path)) {
        echo "<span style='color:red'>[FAIL] File not found: $file</span><br>";
        continue;
    }

    // Read content safely
    $content = file_get_contents($path);
    echo "File size: " . strlen($content) . " bytes.<br>";

    if (empty($content)) {
        echo "<span style='color:red'>[FAIL] File is empty!</span><br>";
        continue;
    }

    // Simulation: Lint with PHP CLI command if available
    $output = [];
    $returnVar = 0;
    // Try to run php -l on the file locally on the server
    exec("php -l " . escapeshellarg($path), $output, $returnVar);

    if ($returnVar === 0) {
        echo "<span style='color:green'>[PASS] Syntax Check OK (php -l).</span><br>";
    } else {
        echo "<span style='color:red'>[CRITICAL] Syntax Error Detected:</span><br>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    }

    // Manual Heuristics for common typos
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        $trim = trim($line);
        if (strpos($trim, 'define') === 0 && substr($trim, -1) !== ';' && substr($trim, -2) !== ');') {
            // Check if it's a multi-line definition (crude check)
            if (strpos($trim, '(') !== false && strpos($trim, ')') === false)
                continue;

            echo "<span style='color:orange'>[WARN] Line " . ($i + 1) . " might be missing a semicolon:</span> <code>" . htmlspecialchars($trim) . "</code><br>";
        }
    }
}
?>