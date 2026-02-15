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
    echo "[FAIL] config.php not found. Did you upload it?\n";
} else {
    try {
        include $configPath;
        echo "[OK] config.php loaded successfully.\n";
        if (defined('GEMINI_API_KEY')) {
            echo " [Key Loaded]\n";
        } else {
            echo " [WARNING] GEMINI_API_KEY constant not defined.\n";
        }
    } catch (Throwable $e) {
        echo "[CRASH] Syntax Error in config.php: " . $e->getMessage() . "\n";
    }
}

// 2. Check GeminiService
echo "<h2>2. Checking services/GeminiService.php...</h2>";
$servicePath = __DIR__ . '/services/GeminiService.php';
if (!file_exists($servicePath)) {
    echo "[FAIL] services/GeminiService.php not found. Did you upload it?\n";
} else {
    try {
        require_once $servicePath;
        echo "[OK] GeminiService.php loaded successfully.\n";
        if (class_exists('GeminiService')) {
            echo " [Class Exists]\n";
        }
    } catch (Throwable $e) {
        echo "[CRASH] Syntax Error in GeminiService.php: " . $e->getMessage() . "\n";
    }
}

echo "<h2>3. PHP Version</h2>";
echo "PHP " . phpversion();
?>