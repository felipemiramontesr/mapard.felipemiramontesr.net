<?php
/**
 * MAPARD - ISOLATED GEMINI TESTER
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

// Authentication check removed for local CLI testing

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/services/GeminiService.php';

use MapaRD\Services\GeminiService;

echo "================================================================\n";
echo "🔍 ISOLATED GEMINI PIPELINE TEST\n";
echo "================================================================\n\n";

echo "Configuration Check:\n";
echo "GEMINI_MODEL: " . GEMINI_MODEL . "\n";
echo "GEMINI_API_KEY: " . substr(GEMINI_API_KEY, 0, 5) . "...\n\n";

// Mock Data (3 Breaches)
$mockData = [
    [
        'name' => 'Canva',
        'date' => '2019-05-24',
        'classes' => ['Email addresses', 'Passwords', 'Names', 'Usernames'],
        'description' => 'In May 2019, Canva suffered a breach...'
    ],
    [
        'name' => 'Nitro',
        'date' => '2020-09-28',
        'classes' => ['Email addresses', 'Passwords', 'Names'],
        'description' => 'In September 2020, Nitro suffered a breach...'
    ],
    [
        'name' => 'Dropbox',
        'date' => '2012-07-01',
        'classes' => ['Email addresses', 'Passwords'],
        'description' => 'In mid-2012, Dropbox suffered a data breach...'
    ]
];

echo "Initiating GeminiService::analyzeBreach()...\n";
echo "Batch size expected: 1 (containing 3 items)\n\n";

try {
    $service = new GeminiService();
    $start = microtime(true);

    $result = $service->analyzeBreach($mockData);

    $end = microtime(true);
    $timeTaken = round($end - $start, 2);

    echo "✅ SUCCESS: analyzeBreach completed in {$timeTaken}s\n\n";
    echo "================================================================\n";
    echo "RESULT PAYLOAD:\n";
    echo "================================================================\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

} catch (Throwable $e) { // Catch both Exceptions and Errors
    echo "❌ FATAL EXCEPTION/ERROR CAUGHT:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n================================================================\n";
echo "TEST COMPLETED.\n";
