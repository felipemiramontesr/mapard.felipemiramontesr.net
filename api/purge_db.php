<?php
// Script de Purga Quirúrgica MAPARD TACTICAL DB para Producción
// Autorización requerida para evitar purgas accidentales o maliciosas

// Secreto táctico directamente inyectado para la operación.
// EJECUCIÓN: https://mapa-rd.felipemiramontesr.net/api/purge_db.php?auth=zero_day_wipe
if (!isset($_GET['auth']) || $_GET['auth'] !== 'zero_day_wipe') {
    http_response_code(403);
    die("ACCESO DENEGADO. Faltan credenciales tácticas.");
}

$dbPath = __DIR__ . '/mapard_v2.sqlite';

if (!file_exists($dbPath)) {
    die("La base de datos ya está limpia o no existe en la ruta de producción: $dbPath\n");
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<pre>Iniciando Protocolo de Limpieza Zero-Day...\n";

    // Purgar tablas OSINT y residuales. El ID de los usuarios y contraseñas de MAPARD quedan intactos.
    $tables = [
        'scans',
        'analysis_snapshots',
        'neutralization_logs',
        'rate_limits',
        'user_security_config'
    ];

    foreach ($tables as $table) {
        $pdo->exec("DELETE FROM $table");
        echo "Tabla purgada: $table\n";
    }

    $pdo->exec("VACUUM");
    echo "Operación DB VACUUM completada.\n";
    echo "\n[EXITO] Operación Zero-Day Finalizada. Base de datos lista para interceptar nuevos reportes HIBP.</pre>";

} catch (PDOException $e) {
    http_response_code(500);
    echo "Falla Crítica de BD: " . $e->getMessage() . "\n";
}
