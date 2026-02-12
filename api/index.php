<?php
// api/index.php - Native PHP Backend for MAPA-RD on Hostinger

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Define Database Path
$dbPath = __DIR__ . '/mapard.sqlite';

// Initialize Database
try {
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS scans (
        job_id TEXT PRIMARY KEY,
        email TEXT,
        status TEXT,
        result_path TEXT,
        logs TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database Connection Failed: " . $e->getMessage()]);
    exit;
}

// Helper: Get Request Method & Path
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$pathParams = explode('/', trim($requestUri, '/'));
// Assuming URL is /api/scan or /api/scan/{id}
// $pathParams[0] = 'api', $pathParams[1] = 'scan', $pathParams[2] = {id}

// Handle OPTIONS (CORS)
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ROUTER logic
if (isset($pathParams[1]) && $pathParams[1] === 'scan') {
    
    // POST /api/scan - Start New Scan
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? 'unknown@target.com';
        $jobId = uniqid('job_', true);
        
        // Initial Logs
        $initialLogs = json_encode([
            ["message" => "Initializing MAPA-RD Protocol (PHP Engine)...", "type" => "info", "timestamp" => date('c')]
        ]);

        $stmt = $pdo->prepare("INSERT INTO scans (job_id, email, status, logs) VALUES (?, ?, 'RUNNING', ?)");
        $stmt->execute([$jobId, $email, $initialLogs]);

        echo json_encode(["job_id" => $jobId, "status" => "ACCEPTED"]);
        exit;
    }

    // GET /api/scan/{id} - Check Status & Simulate Progress
    if ($method === 'GET' && isset($pathParams[2])) {
        $jobId = $pathParams[2];
        
        $stmt = $pdo->prepare("SELECT * FROM scans WHERE job_id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            http_response_code(404);
            echo json_encode(["error" => "Job not found"]);
            exit;
        }

        // SIMULATION ENGINE (The "Magic" part)
        // Since PHP dies after request, we simulate progress based on time passed.
        $startTime = strtotime($job['created_at']);
        $now = time();
        $elapsed = $now - $startTime;

        $logs = json_decode($job['logs'], true) ?: [];
        $status = $job['status'];
        $resultUrl = null;

        // Simulation Timeline
        $updates = [
            2 => ["msg" => "Connecting to SpiderFoot Engine...", "type" => "info"],
            4 => ["msg" => "Module: haveibeenpwned loaded.", "type" => "info"],
            6 => ["msg" => "Searching public breaches...", "type" => "warning"],
            8 => ["msg" => "Found 3 potential credential leaks.", "type" => "error"],
            10 => ["msg" => "Analyzing Dark Web marketplaces...", "type" => "info"],
            12 => ["msg" => "Generating PDF Report...", "type" => "info"],
            14 => ["msg" => "SCAN COMPLETE. Report ready.", "type" => "success", "finish" => true]
        ];

        // Append logs based on elapsed time that haven't been added yet
        // A simple way is to regenerate the log list based on elapsed time to ensure consistency
        $currentLogs = [];
        $currentLogs[] = ["message" => "Initializing MAPA-RD Protocol (PHP Engine)...", "type" => "info", "timestamp" => $job['created_at']];
        
        $isFinished = false;
        foreach ($updates as $time => $data) {
            if ($elapsed >= $time) {
                $currentLogs[] = [
                    "message" => $data['msg'],
                    "type" => $data['type'],
                    "timestamp" => date('c', $startTime + $time)
                ];
                if (isset($data['finish'])) {
                    $isFinished = true;
                }
            }
        }

        // Update DB state
        if ($isFinished && $status !== 'COMPLETED') {
            $status = 'COMPLETED';
            $resultUrl = '/reports/mock.pdf'; // Serving the static mocked file
            
            // Save final state
            $updateStmt = $pdo->prepare("UPDATE scans SET status = ?, logs = ?, result_path = ? WHERE job_id = ?");
            $updateStmt->execute(['COMPLETED', json_encode($currentLogs), $resultUrl, $jobId]);
        } else if ($status !== 'COMPLETED') {
            // Just update logs
            $updateStmt = $pdo->prepare("UPDATE scans SET logs = ? WHERE job_id = ?");
            $updateStmt->execute([json_encode($currentLogs), $jobId]);
        }

        // If completed already, verify result path
        if ($status === 'COMPLETED') {
             $resultUrl = '/reports/mock.pdf';
        }

        echo json_encode([
            "job_id" => $jobId,
            "status" => $status,
            "logs" => $currentLogs,
            "result_url" => $resultUrl
        ]);
        exit;
    }
}

// GET /api/reports/{file}
// Note: Hostinger might serve this statically if .htaccess allows, but let's handle it here just in case 
// or let the RewriteRule handle it.
// Actually, with the RewriteRule we set up (RewriteRule ^api/ api/index.php [L]), 
// requests to /api/reports/mock.pdf might hit this script if not careful.
// Let's explicitly serve it if requested via PHP.
if (isset($pathParams[1]) && $pathParams[1] === 'reports' && isset($pathParams[2])) {
    $file = __DIR__ . '/reports/' . basename($pathParams[2]);
    if (file_exists($file)) {
        header('Content-Type: application/pdf');
        readfile($file);
        exit;
    } else {
        http_response_code(404);
        echo "File not found";
        exit;
    }
}


http_response_code(404);
echo json_encode(["error" => "Endpoint not found"]);
