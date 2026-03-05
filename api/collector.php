<?php

// api/collector.php
// Tactical RSS Collector for MAPARD V2.1 Stateful Feed
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php';
use MapaRD\Services\GeminiService;
// Ensure CLI or explicit Cron execution
if (php_sapi_name() !== 'cli' && !isset($_GET['trigger'])) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
echo "Initiating MAPARD Tactical RSS Collector...\n";
echo "Timestamp: " . date("Y-m-d H:i:s") . "\n\n";
// DB Setup
$dbPath = __DIR__ . '/mapard_v2.sqlite';
if (!file_exists($dbPath)) {
    exit("DATABASE MISSING at $dbPath\n");
}
$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure the table exists (Self-healing for Phase 3)
$pdo->exec("CREATE TABLE IF NOT EXISTS tactical_feed (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    gemini_summary TEXT,
    severity TEXT,
    source TEXT,
    url TEXT,
    status TEXT DEFAULT 'UNREAD',
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$gemini = new GeminiService();
$rssFeeds = [
    'CISA' => 'https://www.cisa.gov/cybersecurity-advisories/all.xml'
];
foreach ($rssFeeds as $source => $feedUrl) {
    echo "Processing Source: $source ($feedUrl)\n";
    // Setting a fake User-Agent to prevent 403 Forbidden from some RSS providers
    $options = [
        "http" => [
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) " .
                "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $rssContent = @file_get_contents($feedUrl, false, $context);
    if (!$rssContent) {
        echo " [ERROR] Failed to fetch $source. Skipping.\n";
        continue;
    }

    $rss = @simplexml_load_string($rssContent);
    if (!$rss) {
        echo " [ERROR] Failed to parse XML. Skipping.\n";
        continue;
    }

    $itemsProcessed = 0;
    foreach ($rss->channel->item as $item) {
        if ($itemsProcessed >= 3) {
            break;
        } // Limit to top 3 to prevent API exhaustion

        $title = (string) $item->title;
        $url = (string) $item->link;
        $description = strip_tags((string) $item->description);
        // Check if already exists in local db
        $stmt = $pdo->prepare("SELECT id FROM tactical_feed WHERE url = ?");
        $stmt->execute([$url]);
        if ($stmt->fetch()) {
            echo "   [SKIPPED] Already in feed: " . substr($title, 0, 50) . "...\n";
            continue;
        }

        echo "   [NEW] Found Vector: " . substr($title, 0, 50) . "...\n";
        echo "   [AI] Requesting Tactical Summary from Gemini...\n";
        $analysis = $gemini->summarizeIntelligence($title, $description);
        $stmt = $pdo->prepare("INSERT INTO tactical_feed " .
            "(title, gemini_summary, severity, source, url, status) " .
            "VALUES (?, ?, ?, ?, ?, 'UNREAD')");
        $stmt->execute([
            $title,
            $analysis['summary'],
            $analysis['severity'],
            $source,
            $url
        ]);
        echo "   [SAVED] Vector neutralized and saved to Inbox.\n\n";
        $itemsProcessed++;
        sleep(2);
        // Rate limiting for Gemini API
    }
}

echo "Collector cycle completed.\n";
