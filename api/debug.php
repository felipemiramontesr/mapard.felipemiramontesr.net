<?php
// api/debug.php - System Diagnostics

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>MAPA-RD Backend Diagnostics</h1>";
echo "<pre>";

// 1. Check PHP Version
echo "<strong>PHP Version:</strong> " . phpversion() . "\n";

// 2. Check Extensions
$extensions = get_loaded_extensions();
echo "\n<strong>Loaded Extensions:</strong>\n";
$required = ['pdo', 'pdo_sqlite', 'sqlite3', 'json'];
foreach ($required as $req) {
    if (in_array($req, $extensions)) {
        echo "[v] $req is LOADED\n";
    } else {
        echo "[X] $req is MISSING <--- CRITICAL ERROR\n";
    }
}

// 3. Check Permissions
$dir = __DIR__;
echo "\n<strong>Current Directory:</strong> $dir\n";
if (is_writable($dir)) {
    echo "[v] Directory is WRITABLE (OK for SQLite)\n";
} else {
    echo "[X] Directory is NOT WRITABLE <--- CRITICAL ERROR (DB Creation will fail)\n";
    echo "    Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";
}

// 4. File Existence
echo "\n<strong>Files Check:</strong>\n";
$files = ['index.php', 'fpdf.php'];
foreach ($files as $f) {
    if (file_exists($dir . '/' . $f)) {
        echo "[v] $f exists\n";
    } else {
        echo "[X] $f is MISSING\n";
    }
}

// 5. Test DB Connection
echo "\n<strong>Database Test:</strong>\n";
try {
    $dbPath = $dir . '/test_debug.sqlite';
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER PRIMARY KEY, Val TEXT)");
    echo "[v] Successfully created/connected to SQLite DB at $dbPath\n";
} catch (Exception $e) {
    echo "[X] DB Connection FAILED: " . $e->getMessage() . "\n";
}

echo "</pre>";
