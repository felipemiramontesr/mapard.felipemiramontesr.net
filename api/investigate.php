<?php
if (!isset($_GET['auth']) || $_GET['auth'] !== 'zero_day_wipe') {
    http_response_code(403);
    die("ACCESO DENEGADO.");
}

$dbPath = __DIR__ . '/mapard_v2.sqlite';
if (!file_exists($dbPath)) {
    die("DB not found at $dbPath");
}

try {
    $pdo = new PDO("sqlite:$dbPath");
    $stmt = $pdo->query("SELECT * FROM scans ORDER BY created_at DESC LIMIT 5");
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    if (empty($scans)) {
        echo "NO SCANS FOUND IN DB.";
    } else {
        foreach ($scans as $row) {
            echo "=====================================\n";
            echo "JOB ID: " . $row['job_id'] . "\n";
            echo "STATUS: " . $row['status'] . "\n";
            echo "ENCRYPTED: " . $row['is_encrypted'] . "\n";
            echo "DATE: " . $row['created_at'] . "\n";

            if ($row['is_encrypted']) {
                require_once __DIR__ . '/utils/SecurityUtils.php';
                $logs = SecurityUtils::decrypt($row['logs']);
                $findings = SecurityUtils::decrypt($row['findings']);
            } else {
                $logs = $row['logs'];
                $findings = $row['findings'];
            }

            echo "--- LOGS ---\n";
            print_r(json_decode($logs, true));
            echo "\n--- FINDINGS ---\n";
            print_r(json_decode($findings, true));
            echo "\n";
        }
    }
    echo "</pre>";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
