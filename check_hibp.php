<?php
/**
 * MAPARD SURGICAL TESTER V1.0
 * Live HIBP Connectivity & API Key Verification
 */

require_once __DIR__ . '/api/config.php';

$email = "felipemiramontesr@gmail.com";
$hibpUrl = "https://haveibeenpwned.com/api/v3/breachedaccount/" . urlencode($email) . "?truncateResponse=false";

echo "================================================================\n";
echo "🔍 MAPARD SURGICAL TESTER - HIBP LIVE FEED\n";
echo "================================================================\n\n";

echo "TARGET EMAIL: $email\n";
echo "HIBP API KEY: " . substr(HIBP_API_KEY, 0, 5) . "..." . substr(HIBP_API_KEY, -5) . "\n\n";

$ch = curl_init($hibpUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "hibp-api-key: " . HIBP_API_KEY
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP CODE: $httpCode\n";
if ($err)
    echo "CURL ERROR: $err\n";

echo "\nRAW RESPONSE:\n";
echo $response ? $response : "--- NO RESPONSE DATA ---";
echo "\n\n";

if ($httpCode === 401)
    echo "❌ ERROR: HIBP API Key Invalid.\n";
if ($httpCode === 404)
    echo "✅ SUCCESS: No breaches found (Legit 404).\n";
if ($httpCode === 200)
    echo "🔥 CRITICAL: Breaches found! System should be displaying them.\n";
if ($httpCode === 403)
    echo "🛡️ BLOCKED: Cloudflare still blocking even with Camouflage UA.\n";

echo "\n================================================================\n";
