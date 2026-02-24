<?php
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/services/GeminiService.php';

header('Content-Type: text/plain');
echo "Testing Gemini API Key...\n";
echo "Key Snippet: " . substr(GEMINI_API_KEY, 0, 5) . "..." . substr(GEMINI_API_KEY, -5) . "\n";

$gemini = new \MapaRD\Services\GeminiService();
$testData = [
    [
        'name' => 'TestService',
        'classes' => ['Email addresses', 'Passwords']
    ]
];

echo "\nCalling analyzeBreach()...\n";
$result = $gemini->analyzeBreach($testData);

if ($result && isset($result['threat_level'])) {
    echo "SUCCESS! API Key is working.\n";
    echo "Threat Level: " . $result['threat_level'] . "\n";
    echo "Summary: " . $result['executive_summary'] . "\n";
} else {
    echo "FAILED. No valid Intel returned.\n";
    $crashLog = __DIR__ . '/api/temp/gemini_debug.log';
    if (file_exists($crashLog)) {
        echo "Check gemini_debug.log for details.\n";
        echo file_get_contents($crashLog);
    }
}
?>