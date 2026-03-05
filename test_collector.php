<?php
// test_collector.php
// Root-level secure trigger for the Phase 3 Intelligence Collector.
// Built to bypass api/.htaccess restrictions for easy manual testing during React development.
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

// Ensure ?trigger=1 is passed explicitly to prevent accidental hits by crawlers
if (!isset($_GET['trigger']) || $_GET['trigger'] !== '1') {
    http_response_code(403);
    exit("Forbidden. Missing manual trigger token.\n");
}

echo "--- MAPARD ROOT COLLECTOR DEPLOYED ---\n";
echo "Booting Intelligence Engine...\n\n";

// Require the actual logic safely from the protected API directory
$collectorPath = __DIR__ . '/api/collector.php';

if (file_exists($collectorPath)) {
    require_once $collectorPath;
} else {
    echo "ERROR: Protected api/collector.php not found at $collectorPath\n";
}
?>