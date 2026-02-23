<?php
/**
 * MAPARD - ISOLATED SCANSERVICE TESTER
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

// Authentication check removed for local CLI testing

require_once __DIR__ . '/api/vendor/autoload.php';
require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/services/ScanService.php';

use MapaRD\Services\ScanService;

echo "================================================================\n";
echo "🔍 ISOLATED SCANSERVICE PIPELINE TEST\n";
echo "================================================================\n\n";

$dbPath = __DIR__ . '/api/mapard_v2.sqlite';
try {
    // -------------------------------------------------------------
    // GEMINI AVAILABLE MODELS QUERY
    // -------------------------------------------------------------
    echo "🤖 CONSULTANDO MODELOS DISPONIBLES EN TU LLAVE GEMINI...\n";
    if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
        $modelsUrl = "https://generativelanguage.googleapis.com/v1beta/models?key=" . GEMINI_API_KEY;
        $ch = curl_init($modelsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $modelsResult = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($modelsResult && $httpCode === 200) {
            $jsonModels = json_decode($modelsResult, true);
            if (isset($jsonModels['models']) && is_array($jsonModels['models'])) {
                echo "✅ Modelos listados correctamente:\n";
                foreach ($jsonModels['models'] as $m) {
                    // Solo mostramos los modelos principales de texto/generación para no saturar
                    if (strpos($m['name'], 'gemini') !== false) {
                        echo "   - " . str_replace('models/', '', $m['name']) . "\n";
                    }
                }
            } else {
                echo "⚠️ No se pudo parsear la lista de modelos.\n";
            }
        } else {
            echo "❌ Error consultando modelos (HTTP $httpCode). La llave podría ser inválida o la red falló.\n";
            if ($modelsResult)
                echo "Detalle: $modelsResult\n";
        }
    } else {
        echo "❌ GEMINI_API_KEY no está definida en config.php\n";
    }
    echo "----------------------------------------------------------------\n\n";

    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ DB Connected.\n";

    // We need a PENDING or RUNNING job to test
    $stmt = $pdo->query("SELECT job_id, email, status FROM scans WHERE status IN ('PENDING', 'RUNNING') ORDER BY created_at DESC LIMIT 1");
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        die("❌ No PENDING/RUNNING jobs found in DB to test. Start a scan in the app first.\n");
    }

    $jobId = $job['job_id'];
    echo "Found Job: {$jobId} ({$job['email']}) [{$job['status']}]\n";

    echo "\nInitiating ScanService::runScan($jobId)...\n\n";

    $scanService = new ScanService($pdo);
    $start = microtime(true);

    $result = $scanService->runScan($jobId);

    $end = microtime(true);
    $timeTaken = round($end - $start, 2);

    echo "✅ SUCCESS: runScan completed in {$timeTaken}s\n\n";
    echo "RESULT PAYLOAD (First 500 chars):\n";
    echo substr(json_encode($result), 0, 500) . "...\n";

} catch (Throwable $e) {
    echo "\n❌ FATAL EXCEPTION/ERROR CAUGHT IN SCANSERVICE PIPELINE:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n================================================================\n";
echo "TEST COMPLETED.\n";
