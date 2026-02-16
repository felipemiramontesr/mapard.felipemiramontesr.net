<?php
// api/check_models.php
header("Content-Type: application/json");
require_once __DIR__ . '/config.php';

// Check Key
if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) {
    die(json_encode(["error" => "API Key not defined in config.php"]));
}

$apiKey = GEMINI_API_KEY;
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=$apiKey";

echo "Querying: https://generativelanguage.googleapis.com/v1beta/models?key=MASKED\n\n";

// Use same fallback logic as service to test connection
$response = null;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err)
        echo "cURL Error: $err\n";
} else {
    $response = @file_get_contents($url);
}

if (!$response) {
    echo "FAILED to connect to Google API.\n";
    print_r(error_get_last());
} else {
    $json = json_decode($response, true);
    if (isset($json['models'])) {
        echo "AVAILABLE MODELS:\n";
        foreach ($json['models'] as $m) {
            echo "- " . $m['name'] . " (" . $m['displayName'] . ")\n";
        }
    } else {
        echo "RAW RESPONSE:\n" . $response;
    }
}
?>