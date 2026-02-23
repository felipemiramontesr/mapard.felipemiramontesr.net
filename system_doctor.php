<?php
/**
 * MAPARD GREAT SYSTEM DOCTOR V1.0
 * Comprehensive Diagnostic Suite for 500 Errors
 */

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['auth']) || $_GET['auth'] !== 'zero_day_wipe') {
    die("DENEGADO.");
}

// Ensure errors are visibly caught if the script crashes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "================================================================\n";
echo "🏥 MAPARD GREAT SYSTEM DOCTOR - CORE DIAGNOSTICS\n";
echo "================================================================\n\n";

// -------------------------------------------------------------
// 1. PHP ENVIRONMENT LIMITS
// -------------------------------------------------------------
echo "[1] LIMITES DUROS DEL SERVIDOR (PHP.ini)\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . " segundos\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Allow URL Fopen: " . (ini_get('allow_url_fopen') ? 'ON' : 'OFF') . "\n";
echo "Curl Module: " . (function_exists('curl_version') ? 'Installed' : 'Missing') . "\n";
echo "PDO SQLite: " . (extension_loaded('pdo_sqlite') ? 'Loaded' : 'Missing') . "\n";

// Test if set_time_limit works
$timeLimitSetting = @set_time_limit(300);
echo "Can override set_time_limit(300): " . ($timeLimitSetting !== false ? 'YES' : 'NO (Disabled by Hostinger)') . "\n\n";

// -------------------------------------------------------------
// 2. DATABASE INTEGRITY
// -------------------------------------------------------------
echo "[2] INTEGRIDAD DE BASE DE DATOS\n";
$dbPath = __DIR__ . '/api/mapard_v2.sqlite';
if (!file_exists($dbPath)) {
    echo "❌ ERROR: Base de datos sqlite no encontrada en $dbPath\n";
} else {
    echo "DB Existente: SÍ\n";
    echo "DB Permisos: " . substr(sprintf('%o', fileperms($dbPath)), -4) . "\n";
    echo "DB Writable: " . (is_writable($dbPath) ? 'YES' : 'NO') . "\n";

    try {
        $pdo = new PDO("sqlite:$dbPath");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "PDO Connection: OK\n";

        // Test required tables
        $tables = ['users', 'scans', 'user_security_config'];
        foreach ($tables as $t) {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'");
            if ($stmt->fetch()) {
                echo "Table '$t': OK\n";
            } else {
                echo "❌ Table '$t': MISSING\n";
            }
        }
    } catch (Exception $e) {
        echo "❌ PDO Exception: " . $e->getMessage() . "\n";
    }
}
echo "\n";

// -------------------------------------------------------------
// 3. SECRETS & CONFIG
// -------------------------------------------------------------
echo "[3] VERIFICACIÓN DE CONFIGURACIÓN\n";
$configFile = __DIR__ . '/api/config.php';
if (!file_exists($configFile)) {
    echo "❌ ERROR: api/config.php no encontrado.\n\n";
} else {
    require_once $configFile;
    echo "GEMINI API KEY: " . (defined('GEMINI_API_KEY') ? "Definida (Inicia con: " . substr(GEMINI_API_KEY, 0, 5) . ")" : "NO DEFINIDA") . "\n";
    echo "GEMINI MODEL: " . (defined('GEMINI_MODEL') ? GEMINI_MODEL : "NO DEFINIDO") . "\n\n";
}

// -------------------------------------------------------------
// 4. THE CORTEX/GEMINI FIRE TEST
// -------------------------------------------------------------
echo "[4] PRUEBA DE FUEGO CORTEX (GEMINI API)\n";
if (!defined('GEMINI_API_KEY')) {
    echo "Saltando prueba CORTEX por falta de API KEY.\n";
} else {
    $modelToTest = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-2.0-flash';
    // Fallback if the model is gemini-2.5-pro, let's test flash as well just in case

    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $modelToTest . ":generateContent?key=" . GEMINI_API_KEY;

    $payload = [
        "contents" => [
            ["role" => "user", "parts" => [["text" => "Responde únicamente con la palabra 'OPERATIVO'."]]]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
            "responseMimeType" => "application/json"
        ]
    ];

    echo "Endpoint: $url\n";
    echo "Ejecutando cURL hacia Gemini...\n";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // Bypass SSL for test
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $start = microtime(true);
    $result = curl_exec($ch);
    $end = microtime(true);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $timeTaken = round($end - $start, 2);
    echo "Tiempo de respuesta: $timeTaken segundos\n";
    echo "HTTP CODE: $httpCode\n";

    if ($curlError) {
        echo "❌ CURL ERROR: $curlError\n";
    }

    if ($result) {
        $json = json_decode($result, true);
        if ($json === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "❌ JSON DECODE ERROR: " . json_last_error_msg() . "\n";
            echo "RAW RESPONSE (First 500 chars): \n" . substr($result, 0, 500) . "...\n";
        } else {
            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                echo "✅ GEMINI RESPONDIÓ CORRECTAMENTE:\n";
                echo $json['candidates'][0]['content']['parts'][0]['text'] . "\n";
            } elseif (isset($json['error'])) {
                echo "❌ GEMINI API ERROR: " . $json['error']['message'] . " (Code: " . $json['error']['code'] . ")\n";
                if ($json['error']['code'] == 404) {
                    echo "   > El modelo especificado ('$modelToTest') parece no existir o no tienes acceso.\n";
                }
            } else {
                echo "⚠️ ESTRUCTURA INESPERADA:\n";
                print_r($json);
            }
        }
    } else {
        echo "❌ SIN RESPUESTA LÓGICA DE CURL.\n";
    }
}
echo "\n";

// -------------------------------------------------------------
// 5. ERROR LOGS DUMP (If available)
// -------------------------------------------------------------
echo "[5] REGISTROS DE ERRORES DEL SERVIDOR (Últimas 10 líneas)\n";
$errorLogFile = __DIR__ . '/error_log'; // Common in cPanel/Hostinger root
if (file_exists($errorLogFile)) {
    $lines = file($errorLogFile);
    if ($lines) {
        $lastLines = array_slice($lines, -10);
        foreach ($lastLines as $line) {
            echo trim($line) . "\n";
        }
    } else {
        echo "(Archivo vacío o ilegible)\n";
    }
} else {
    echo "No hay archivo 'error_log' visible en la raíz.\n";
}

echo "\n================================================================\n";
echo "✅ DIAGNÓSTICO FINALIZADO.\n";
