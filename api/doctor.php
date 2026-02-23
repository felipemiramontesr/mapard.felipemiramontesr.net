<?php
/**
 * MAPARD DOCTOR V1.0
 * Deep Environment Diagnostic Tool
 */

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['auth']) || $_GET['auth'] !== 'zero_day_wipe') {
    http_response_code(403);
    die("ACCESO DENEGADO. Autenticación Operativa Requerida.");
}

require_once __DIR__ . '/config.php';

echo "================================================================\n";
echo "🏥 MAPARD DOCTOR - REPORTE DE SALUD DEL SISTEMA\n";
echo "================================================================\n\n";

// 1. SYSTEM INFO
echo "[1] INFORMACIÓN DEL SISTEMA\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server OS: " . PHP_OS . "\n";
echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
echo "Interface: " . php_sapi_name() . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n\n";

// 2. CRITICAL EXTENSIONS
echo "[2] EXTENSIONES CRÍTICAS\n";
$extensions = ['curl', 'openssl', 'sqlite3', 'pdo_sqlite', 'mbstring', 'json'];
foreach ($extensions as $ext) {
    echo str_pad($ext . ":", 15) . (extension_loaded($ext) ? "✅ CARGADA" : "❌ NO ENCONTRADA") . "\n";
}
echo "\n";

// 3. NETWORK CONNECTIVITY & SSL
echo "[3] CONECTIVIDAD DE RED Y SSL\n";
$targets = [
    'Google API' => 'https://generativelanguage.googleapis.com',
    'HIBP API' => 'https://haveibeenpwned.com',
    'Vercel' => 'https://vercel.com'
];

foreach ($targets as $name => $url) {
    echo "--- Probando $name ($url) ---\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);

    // Test with default SSL
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);

    echo "HTTP CODE: $httpCode\n";
    if ($err)
        echo "ERROR: $err\n";

    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    echo "CURL VERBOSE LOG (Resumen):\n";
    // Get last few lines of verbose log to see TLS handshake
    $lines = explode("\n", $verboseLog);
    echo implode("\n", array_slice($lines, -15)) . "\n";

    curl_close($ch);
    echo "\n";
}

// 4. OPENSSL CONFIG
echo "[4] CONFIGURACIÓN OPENSSL\n";
if (extension_loaded('openssl')) {
    echo "OpenSSL Version: " . OPENSSL_VERSION_TEXT . "\n";
    $certLocations = openssl_get_cert_locations();
    echo "CA Bundle Path (Default): " . $certLocations['default_cert_file'] . "\n";
    echo "CA Bundle Exists: " . (file_exists($certLocations['default_cert_file']) ? "✅ SÍ" : "❌ NO") . "\n";
}
echo "\n";

// 5. FILE SYSTEM PERMISSIONS
echo "[5] PERMISOS DE ARCHIVOS\n";
$files = [
    'Database' => __DIR__ . '/mapard_v2.sqlite',
    'Config' => __DIR__ . '/config.php',
    'LogsDir' => __DIR__ . '/temp'
];

foreach ($files as $label => $path) {
    echo str_pad($label . ":", 12) . $path . "\n";
    echo "   Existencia: " . (file_exists($path) ? "✅" : "❌") . "\n";
    if (file_exists($path)) {
        echo "   Permisos: " . substr(sprintf('%o', fileperms($path)), -4) . "\n";
        echo "   Writability: " . (is_writable($path) ? "✅ ESCRIBIBLE" : "❌ SOLO LECTURA") . "\n";
    }
}
echo "\n";

echo "================================================================\n";
echo "FIN DEL REPORTE\n";
echo "================================================================\n";
