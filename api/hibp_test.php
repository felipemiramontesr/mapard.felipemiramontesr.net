<?php
require_once __DIR__ . '/config.php';

$email = 'felipemiramontesr@gmail.com'; // Testing target
if (isset($argv[1]))
    $email = $argv[1];

echo "Testing HIBP for: $email\n";
echo "Using API Key: " . (defined('HIBP_API_KEY') ? substr(HIBP_API_KEY, 0, 5) . '...' : 'NONE') . "\n";

$hibpUrl = "https://haveibeenpwned.com/api/v3/breachedaccount/" . urlencode($email);
$ch = curl_init($hibpUrl . "?truncateResponse=false");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "hibp-api-key: " . (defined('HIBP_API_KEY') ? HIBP_API_KEY : ''),
    "user-agent: MAPARD-OSINT-AGENT"
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error)
    echo "cURL Error: $error\n";
echo "Response: " . substr($response, 0, 200) . "...\n";
