<?php
// api/debug_hibp.php
// Diagnostic Tool for HIBP Connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

header('Content-Type: text/plain');

echo "--- MAPARD HIBP DIAGNOSTIC ---\n";
echo "PHP Version: " . phpversion() . "\n";

// 1. Check Key Format
$key = defined('HIBP_API_KEY') ? HIBP_API_KEY : 'NOT_DEFINED';
echo "Key Length: " . strlen($key) . "\n";
echo "Key First 4: " . substr($key, 0, 4) . "...\n";
echo "Key Last 4: ..." . substr($key, -4) . "\n";

if (preg_match('/\s/', $key)) {
    echo "WARNING: Key contains whitespace! Sanitizing recommended.\n";
}

// 2. Test Connection
$testEmail = 'test@example.com';
echo "\n--- TESTING CONNECTION ---\n";
echo "Target: $testEmail\n";

$ch = curl_init("https://haveibeenpwned.com/api/v3/breachedaccount/" . urlencode($testEmail) . "?truncateResponse=true");
$headers = [
    "hibp-api-key: " . trim($key),
    "user-agent: MAPARD-DIAGNOSTIC-TOOL-V1"
];

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // Capture headers
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_VERBOSE, false);

echo "Sending Request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headerStr = substr($response, 0, $headerSize);
$bodyStr = substr($response, $headerSize);

curl_close($ch);

echo "HTTP Code: $httpCode\n";

if ($curlError) {
    echo "CURL ERROR: $curlError\n";
}

echo "\n--- RESPONSE HEADERS ---\n";
echo $headerStr;

echo "\n--- RESPONSE BODY ---\n";
echo $bodyStr;

if ($httpCode === 200) {
    echo "\n\nSUCCESS! API Key is working.";
} elseif ($httpCode === 401) {
    echo "\n\nFAILURE: 401 Unauthorized. Check Key or Subscription Status.";
} elseif ($httpCode === 403) {
    echo "\n\nFAILURE: 403 Forbidden. User-Agent rejected or IP banned.";
}

?>