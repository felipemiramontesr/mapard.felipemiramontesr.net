<?php
// api/check_syntax.php
// Tool to diagnose 500 Errors (Syntax or Missing Files)
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>MAPARD Syntax Diagnostic</h1>";

// 1. Check Config
echo "<h2>1. Checking config.php...</h2>";
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    echo "<span style='color:red'>FAIL: config.php not found. Did you upload it?</span>";
} else {
    try {
        include $configPath;
        echo "<span style='color:green'>OK: config.php loaded successfully.</span>";
        if (defined('GEMINI_API_KEY')) {
            echo " [Key Loaded]";
        } else {
            echo " <span style='color:orange'>WARNING: GEMINI_API_KEY constant not defined.</span>";
        }
    } catch (Throwable $e) {
        echo "<span style='color:red'>CRASH: Syntax Error in config.php: " . $e->getMessage() . "</span>";
    }
}

// 2. Check GeminiService
echo "<h2>2. Checking services/GeminiService.php...</h2>";
$servicePath = __DIR__ . '/services/GeminiService.php';
if (!file_exists($servicePath)) {
    echo "<span style='color:red'>FAIL: services/GeminiService.php not found. Did you upload it to the 'services' folder?</span>";
} else {
    try {
        require_once $servicePath;
        echo "<span style='color:green'>OK: GeminiService.php loaded successfully.</span>";
        if (class_exists('GeminiService')) {
            echo " [Class Exists]";
        }
    } catch (Throwable $e) {
        echo "<span style='color:red'>CRASH: Syntax Error in GeminiService.php: " . $e->getMessage() . "</span>";
    }
}

echo "<h2>3. PHP Version</h2>";
echo "PHP " . phpversion();
?>