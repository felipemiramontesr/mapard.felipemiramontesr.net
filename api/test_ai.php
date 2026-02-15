<?php
// api/test_ai.php
// DIAGNOSTIC TOOL: Test Gemini 2.5 Pro with a single input.

header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/services/GeminiService.php';

echo "--- TESTING GEMINI 2.5 PRO ---\n";
echo "Timestamp: " . date('c') . "\n\n";

// 1. Mock Data
$testBreach = [
    [
        'name' => 'Test Breach Corp',
        'domain' => 'test.com',
        'breach_date' => '2025-01-01',
        'description' => 'A sample leak of 100 emails.'
    ]
];

echo "[1] INPUT DATA:\n";
print_r($testBreach);
echo "\n";

// 2. Initialize Service
try {
    $gemini = new GeminiService();
    echo "[2] SERVICE INITIALIZED.\n";
} catch (Exception $e) {
    die("[FATAL] Could not init service: " . $e->getMessage());
}

// 3. Make Request (We will use reflection or modified service to debug if needed, 
// but standard call should work if we just dump the result).
echo "[3] SENDING REQUEST (Wait ~10s)...\n";
$start = microtime(true);

$result = $gemini->analyzeBreach($testBreach);

$duration = microtime(true) - $start;
echo "[4] REQUEST COMPLETED in " . number_format($duration, 2) . "s.\n\n";

// 4. Analyze Result
echo "--- RAW RESULT ANALYSIS ---\n";
if (isset($result['executive_summary']) && strpos($result['executive_summary'], 'FALLO') !== false) {
    echo "[FAIL] The service returned a Fallback (Error).\n";
    echo "Reason: " . $result['executive_summary'] . "\n";

    // Attempt to read debug log if it exists
    if (file_exists(__DIR__ . '/gemini_cleaned.log')) {
        echo "\n[DEBUG LOG FOUND] gemini_cleaned.log content:\n";
        echo file_get_contents(__DIR__ . '/gemini_cleaned.log');
    } else {
        echo "\n[INFO] No gemini_cleaned.log found. The error might be earlier in the chain.\n";
    }

} else {
    echo "[SUCCESS] Valid JSON received!\n";
    print_r($result);
}
?>