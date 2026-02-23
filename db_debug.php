<?php
require 'api/config.php';
require 'api/services/SecurityUtils.php';

use MapaRD\Services\SecurityUtils;

$dbPath = 'api/mapard_v2.sqlite';
$pdo = new PDO("sqlite:$dbPath");

$email = 'felipemiramontesr@gmail.com';
$stmt = $pdo->prepare("SELECT * FROM scans WHERE email = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$email]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if ($job) {
    echo "Job ID: " . $job['job_id'] . "\n";
    echo "Status: " . $job['status'] . "\n";
    echo "Is Encrypted: " . $job['is_encrypted'] . "\n";

    $findings = $job['is_encrypted'] ? SecurityUtils::decrypt($job['findings']) : $job['findings'];
    $logs = $job['is_encrypted'] ? SecurityUtils::decrypt($job['logs']) : $job['logs'];

    echo "--- RAW LOGS ---\n" . substr($logs, 0, 500) . "...\n";
    echo "json_decode(logs) = " . (json_decode($logs) ? "OK" : "FAILED: " . json_last_error_msg()) . "\n";

    echo "--- RAW FINDINGS ---\n" . substr($findings, 0, 500) . "...\n";
    echo "json_decode(findings) = " . (json_decode($findings) ? "OK" : "FAILED: " . json_last_error_msg()) . "\n";

    $stmt2 = $pdo->prepare("SELECT is_first_analysis_complete FROM user_security_config WHERE user_id = (SELECT id FROM users WHERE email_target = ?)");
    $stmt2->execute([$email]);
    $config = $stmt2->fetch(PDO::FETCH_ASSOC);
    echo "Config: " . json_encode($config) . "\n";

} else {
    echo "Job not found for email.\n";
}
