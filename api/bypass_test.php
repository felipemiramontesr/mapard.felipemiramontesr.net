<?php
require_once __DIR__ . '/config.php';

$email = isset($_GET['email']) ? $_GET['email'] : 'test@example.com';
$url = "https://haveibeenpwned.com/api/v3/breachedaccount/" . urlencode($email) . "?truncateResponse=false";
$key = defined('HIBP_API_KEY') ? HIBP_API_KEY : '';

ob_start();

echo "<pre>";
echo "<h2>🎯 PRUEBA DE PENETRACIÓN (BYPASS CLOUDFLARE)</h2>";
echo "TARGET URL: " . htmlspecialchars($url) . "\n\n";

// --- PERFIL 1: ACTUAL (MAPARD-OSINT-AGENT) ---
echo "========================================================\n";
echo "🛡️ PERFIL 1: Actual MAPARD Agent (Probable Bloqueado)\n";
$ch1 = curl_init($url);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch1, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch1, CURLOPT_TIMEOUT, 8);
curl_setopt($ch1, CURLOPT_HTTPHEADER, ["hibp-api-key: $key", "user-agent: MAPARD-OSINT-AGENT"]);
$res1 = curl_exec($ch1);
$code1 = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
$err1 = curl_error($ch1);
curl_close($ch1);
echo "HTTP CODE: $code1 \n";
echo "CURL ERROR: " . ($err1 ? $err1 : "N/A") . "\n";
echo "RESPUESTA: " . substr($res1, 0, 150) . "...\n\n";

// --- PERFIL 2: BROWSER EMULATION AGGRESSIVE ---
echo "========================================================\n";
echo "🥷 PERFIL 2: Chrome Browser Emulation + Accept-Encoding\n";
$ch2 = curl_init($url);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch2, CURLOPT_ENCODING, ''); // Auto-handle gzip/deflate
curl_setopt($ch2, CURLOPT_TIMEOUT, 8);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    "hibp-api-key: $key",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36",
    "Accept: application/json, text/plain, */*",
    "Accept-Language: es-MX,es;q=0.9,en-US;q=0.8,en;q=0.7",
    "Cache-Control: no-cache",
    "Connection: keep-alive"
]);
$res2 = curl_exec($ch2);
$code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$err2 = curl_error($ch2);
curl_close($ch2);
echo "HTTP CODE: $code2 \n";
echo "CURL ERROR: " . ($err2 ? $err2 : "N/A") . "\n";
echo "RESPUESTA: " . substr($res2, 0, 150) . "...\n\n";

// --- PERFIL 3: FORCED HTTP/1.1 & CIPHERS ---
echo "========================================================\n";
echo "🚀 PERFIL 3: Forced HTTP/1.1 + Strict Ciphers\n";
$ch3 = curl_init($url);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch3, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch3, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch3, CURLOPT_SSL_CIPHER_LIST, 'DEFAULT@SECLEVEL=1');
curl_setopt($ch3, CURLOPT_TIMEOUT, 8);
curl_setopt($ch3, CURLOPT_HTTPHEADER, [
    "hibp-api-key: $key",
    "user-agent: MAPARD-OSINT-AGENT"
]);
$res3 = curl_exec($ch3);
$code3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
$err3 = curl_error($ch3);
curl_close($ch3);
echo "HTTP CODE: $code3 \n";
echo "CURL ERROR: " . ($err3 ? $err3 : "N/A") . "\n";
echo "RESPUESTA: " . substr($res3, 0, 150) . "...\n\n";

echo "========================================================\n";
echo "</pre>";

$output = ob_get_clean();
file_put_contents(__DIR__ . '/diagnostic_result.txt', $output);
echo $output;
