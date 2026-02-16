<?php
// api/debug_gemini.php
// Diagnostic Tool: List Available Models
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain');

echo "--- MAPARD GEMINI MODEL DISCOVERY ---\n";

// Load Config
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
    $key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : 'NOT_DEFINED';
    echo "Key Loaded: " . substr($key, 0, 5) . "..." . substr($key, -4) . "\n";
} else {
    echo "CRITICAL: config.php not found.\n";
    exit;
}

// Configured Model
echo "Configured Model: " . (defined('GEMINI_MODEL') ? GEMINI_MODEL : 'None') . "\n";

// Query Available Models
echo "\n--- QUERYING GOOGLE MODELS LIST ---\n";
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $key;

$options = [
    'http' => [
        'method' => 'GET',
        'timeout' => 15,
        'ignore_errors' => true
    ]
];

echo "Requesting: $url\n";

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

echo "Response Received.\n";
echo "HTTP Status Headers:\n";
print_r($http_response_header);

echo "\n--- RAW RESPONSE BODY ---\n";
echo $response;

// Parse and List
$json = json_decode($response, true);
if (isset($json['models'])) {
    echo "\n\n--- AVAILABLE MODELS FOUND ---\n";
    foreach ($json['models'] as $m) {
        $name = str_replace('models/', '', $m['name']);
        $methods = isset($m['supportedGenerationMethods']) ? implode(', ', $m['supportedGenerationMethods']) : 'Unknown';
        echo "[$name] - Methods: $methods\n";
    }
} else {
    echo "\n\nFAILURE: Could not parse models list.";
}
?>