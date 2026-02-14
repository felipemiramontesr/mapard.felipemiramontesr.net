<?php
// api/debug_gemini.php
// Diagnostic Tool for Gemini Connection
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "--- MAPARD GEMINI DIAGNOSTIC ---\n";

// 1. Check Environment
echo "PHP Version: " . phpversion() . "\n";
$allowUrlFopen = ini_get('allow_url_fopen');
echo "allow_url_fopen: " . ($allowUrlFopen ? 'ON' : 'OFF') . "\n";

if (!$allowUrlFopen) {
    echo "CRITICAL FAULT: allow_url_fopen is OFF. Services cannot make external requests.\n";
}

// 2. Load Config
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
    echo "Config loaded.\n";
    $key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : 'NOT_DEFINED';
    $model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'NOT_DEFINED';

    echo "Model: $model\n";
    echo "Key: " . substr($key, 0, 5) . "..." . substr($key, -4) . " (Length: " . strlen($key) . ")\n";
} else {
    echo "CRITICAL: config.php not found.\n";
    exit;
}

// 3. Load Service
$servicePath = __DIR__ . '/services/GeminiService.php';
if (file_exists($servicePath)) {
    require_once $servicePath;
    echo "GeminiService.php found.\n";
} else {
    echo "CRITICAL: services/GeminiService.php not found at $servicePath\n";
    exit;
}

// 4. Test Connectivity (Raw Replication)
echo "\n--- TESTING GOOGLE CONNECTIVITY ---\n";
$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $key;
$payload = [
    'contents' => [
        ['parts' => [['text' => 'Hello, reply with "OPERATIONAL" if you receive this.']]]
    ]
];

$options = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($payload),
        'timeout' => 15,
        'ignore_errors' => true
    ]
];

echo "Sending Probe to: https://generativelanguage.googleapis.com/...\n";

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

echo "Response Received.\n";
echo "HTTP Status Headers:\n";
print_r($http_response_header);

echo "\nResponse Body:\n";
echo $response;

if (strpos($response, 'OPERATIONAL') !== false) {
    echo "\n\nSUCCESS: Gemini is responding correctly.";
} else {
    echo "\n\nFAILURE: Gemini did not respond as expected.";
}
?>