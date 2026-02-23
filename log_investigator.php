<?php
/**
 * MAPARD LOG INVESTIGATOR V1.0
 * Decrypts and displays the latest scan logs.
 */

header('Content-Type: text/plain; charset=utf-8');

if (!isset($_GET['auth']) || $_GET['auth'] !== 'zero_day_wipe') {
    die("DENEGADO.");
}

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/services/SecurityUtils.php';

use MapaRD\Services\SecurityUtils;

try {
    $dbPath = __DIR__ . '/api/mapard_v2.sqlite';
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "--- ÚLTIMOS 5 ESCANEOS ---\n\n";
    $stmt = $pdo->query("SELECT job_id, email, status, logs, findings FROM scans ORDER BY created_at DESC LIMIT 5");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "JOB: " . $row['job_id'] . "\n";
        echo "TARGET: " . $row['email'] . "\n";
        echo "STATUS: " . $row['status'] . "\n";

        echo "LOGS DE OPERACIÓN:\n";
        $decryptedLogs = SecurityUtils::decrypt($row['logs']);
        $logsArray = json_decode($decryptedLogs, true);
        if ($logsArray) {
            foreach ($logsArray as $l) {
                echo "  [" . ($l['timestamp'] ?? '?') . "] " . $l['message'] . " (" . $l['type'] . ")\n";
            }
        } else {
            echo "  (No se pudieron desencriptar o están vacíos)\n";
        }

        echo "VECTORES ENCONTRADOS:\n";
        $decryptedFindings = SecurityUtils::decrypt($row['findings']);
        echo "  " . ($decryptedFindings ?: "VACÍO") . "\n";

        echo "--------------------------------------------------\n\n";
    }

} catch (Exception $e) {
    echo "ERROR DE INVESTIGACIÓN: " . $e->getMessage();
}
