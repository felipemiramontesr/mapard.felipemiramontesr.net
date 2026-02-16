<?php
// api/test_gemini_cli.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "--- STARTING GEMINI COMPONENT TEST ---\n";

// 1. Load Dependencies
$servicePath = __DIR__ . '/services/GeminiService.php';
if (!file_exists($servicePath)) {
    die("CRITICAL: GeminiService.php not found at $servicePath\n");
}
require_once $servicePath;
echo "Service File Loaded.\n";

// 2. Instantiate Service
try {
    $gemini = new GeminiService();
    echo "Service Instantiated.\n";
} catch (Exception $e) {
    die("CRITICAL: Instantiation Failed: " . $e->getMessage() . "\n");
}

// 3. Prepare Dummy Data
$breaches = [
    [
        'name' => 'Adobe',
        'title' => 'Adobe Systems',
        'domain' => 'adobe.com',
        'date' => '2013-10-04',
        'description' => 'Adobe systems was hacked.',
        'data_classes' => ['Email addresses', 'Password hints', 'Passwords', 'Usernames']
    ],
    [
        'name' => 'Dropbox',
        'title' => 'Dropbox',
        'domain' => 'dropbox.com',
        'date' => '2012-07-01',
        'description' => 'Dropbox suffered a breach.',
        'data_classes' => ['Email addresses', 'Passwords']
    ]
];

echo "Invoking analyzeBreach with " . count($breaches) . " items...\n";
$startTime = microtime(true);

// 4. Call Method
try {
    $result = $gemini->analyzeBreach($breaches);
    $duration = microtime(true) - $startTime;

    echo "Call Completed in " . number_format($duration, 2) . "s\n";

    echo "\n--- RESULT DUMP ---\n";
    var_dump($result);

    if (is_array($result) && !empty($result['detailed_analysis'])) {
        echo "\nSUCCESS: Analysis returned " . count($result['detailed_analysis']) . " items.\n";
    } else {
        echo "\nFAILURE: Return value is invalid or empty.\n";
    }

} catch (Exception $e) {
    echo "\nEXCEPTION CAUGHT: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

echo "\n--- END TEST ---\n";
?>