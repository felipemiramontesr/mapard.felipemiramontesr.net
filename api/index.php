<?php
// Enable Error Reporting for Debugging (Return JSON, not HTML)
ini_set('display_errors', 0); // Hide HTML errors
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Robust Include
$fpdfPath = __DIR__ . '/fpdf.php';
if (!file_exists($fpdfPath)) {
    http_response_code(500);
    echo json_encode(["error" => "Critical Dependency Missing: fpdf.php not found in " . __DIR__]);
    exit;
}

// FIX: Use realpath to resolve symlinks or relative path issues
$fontDir = __DIR__ . '/font';
if (is_dir($fontDir)) {
    define('FPDF_FONTPATH', realpath($fontDir) . '/');
} else {
    // Fallback or error
    define('FPDF_FONTPATH', __DIR__ . '/font/');
}

require($fpdfPath);

// api/index.php - Real OSINT Engine

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Register Shutdown Function to catch Fatal Errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(["error" => "Fatal PHP Error", "details" => $error]);
        exit;
    }
});

// Version 2 DB to ensure 'domain' column exists
$dbPath = __DIR__ . '/mapard_v2.sqlite';

try {
    // Check Writable
    if (!is_writable(__DIR__)) {
        throw new Exception("Directory " . __DIR__ . " is not writable. Cannot create DB.");
    }

    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE TABLE IF NOT EXISTS scans (
        job_id TEXT PRIMARY KEY,
        email TEXT,
        domain TEXT,
        status TEXT,
        result_path TEXT,
        logs TEXT, 
        findings TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database Init Failed: " . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];
$pathParams = explode('/', trim($requestUri, '/'));

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ROUTER
if (isset($pathParams[1]) && $pathParams[1] === 'scan') {

    // START SCAN
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? 'unknown';
        $domain = $input['domain'] ?? '';

        // Extract domain from email if not provided
        if (empty($domain) && strpos($email, '@') !== false) {
            $parts = explode('@', $email);
            $domain = array_pop($parts);
        }

        $jobId = uniqid('job_', true);
        $initialLogs = json_encode([
            ["message" => "Initializing Real-Time OSINT Protocol...", "type" => "info", "timestamp" => date('c')]
        ]);

        $stmt = $pdo->prepare("INSERT INTO scans (job_id, email, domain, status, logs) VALUES (?, ?, ?, 'RUNNING', ?)");
        $stmt->execute([$jobId, $email, $domain, $initialLogs]);

        echo json_encode(["job_id" => $jobId, "status" => "ACCEPTED"]);
        exit;
    }

    // CHECK STATUS & EXECUTE LOGIC
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

        if ($job['status'] === 'COMPLETED') {
            echo json_encode([
                "job_id" => $jobId,
                "status" => "COMPLETED",
                "logs" => json_decode($job['logs']),
                "result_url" => $job['result_path']
            ]);
            exit;
        }

        try {
            // --- REAL OSINT EXECUTION ---
            $logs = json_decode($job['logs'], true) ?: [];
            $findings = [];
            $domain = $job['domain'];

            // Helper to add log if unique
            function addLog(&$logs, $msg, $type = 'info')
            {
                foreach ($logs as $l) {
                    if ($l['message'] === $msg)
                        return;
                }
                $logs[] = ["message" => $msg, "type" => $type, "timestamp" => date('c')];
            }

            // Step 1: DNS Recon
            if ($domain) {
                // Check if dns_get_record function exists
                if (!function_exists('dns_get_record')) {
                    addLog($logs, "DNS functions disabled on this server.", "warning");
                } else {
                    addLog($logs, "Resolving DNS records for $domain...", "info");
                    // Suppress warnings for DNS
                    $dns = @dns_get_record($domain, DNS_A + DNS_MX);
                    if ($dns) {
                        $count = count($dns);
                        addLog($logs, "Found $count DNS records.", "success");
                        $findings[] = "DNS Records Found: $count";
                        foreach ($dns as $r) {
                            if (isset($r['ip']))
                                $findings[] = "A Record: " . $r['ip'];
                            if (isset($r['target']))
                                $findings[] = "MX Record: " . $r['target'];
                        }
                    } else {
                        addLog($logs, "No DNS records found for $domain.", "warning");
                    }
                }
            }

            // Step 2: HIBP Breach Check (Real API)
            $hibpApiKey = '011373b46b674891b6f19772c2205772';
            $targetEmail = $job['email'];
            addLog($logs, "Querying Have I Been Pwned for $targetEmail...", "info");

            $ch = curl_init("https://haveibeenpwned.com/api/v3/breachedaccount/" . urlencode($targetEmail) . "?truncateResponse=false");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "hibp-api-key: $hibpApiKey",
                "user-agent: MAPA-RD-OSINT-AGENT"
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200) {
                $breaches = json_decode($response, true);
                $count = count($breaches);
                addLog($logs, "CRITICAL: Found $count compromised credentials.", "error");
                foreach ($breaches as $b) {
                    $findings[] = "Breach: " . $b['Name'] . " (" . $b['BreachDate'] . ") - classes: " . implode(", ", array_slice($b['DataClasses'], 0, 3));
                }
            } elseif ($httpCode === 404) {
                addLog($logs, "No public breaches found for this email.", "success");
                $findings[] = "Breach Analysis: Clean (No public leaks found).";
            } elseif ($httpCode === 429) {
                addLog($logs, "HIBP Rate Limit Exceeded. Skipping breach check.", "warning");
                $findings[] = "Breach Analysis: Skipped (Rate Limit).";
            } elseif ($httpCode === 401) {
                addLog($logs, "HIBP API Key Invalid. Checking configuration.", "error");
                $findings[] = "Breach Analysis: Failed (Auth Error).";
            } else {
                addLog($logs, "HIBP API Error ($httpCode): " . ($curlError ?: 'Unknown'), "error");
                $findings[] = "Breach Analysis: Failed (API Error $httpCode).";
            }

            // Step 3: Complete & Generate PDF
            addLog($logs, "Generating Intelligence Report...", "info");

            // --- INTEL DOSSIER GENERATION LOGIC ---

            // 1. KNOWLEDGE BASE (STATIC)
            $glossary = [
                "Data Breach" => "Incidente de seguridad donde informacion confidencial es accedida sin autorizacion.",
                "Stealer Log" => "Registros extraidos por malware (InfoStealers) desde dispositivos infectados.",
                "Combo List" => "Listas de credenciales (email:pass) recopiladas de multiples brechas para ataques de relleno.",
                "Plaintext Password" => "Contrasenas almacenadas sin cifrado, legibles directamente por atacantes."
            ];

            $mitigationProtocols = [
                "1. CAMBIO INMEDIATO DE CREDENCIALES" => "Rote todas las contrasenias expuestas. Use frases de paso largas (+16 caracteres).",
                "2. AUTENTICACION MULTI-FACTOR (2FA)" => "Active 2FA en todos los servicios criticos. Evite SMS, prefiera Apps o Llaves U2F.",
                "3. GESTOR DE CONTRASENAS" => "Utilice Bitwarden o 1Password para generar y guardar claves unicas por sitio.",
                "4. MONITORIZACION ACTIVA" => "Mantenga alertas de identidad en servicios como HaveIBeenPwned o Google Dark Web Report."
            ];

            // 2. RISK MAPPING (DYNAMIC)
            $riskScore = 0;
            $riskAnalysis = [];

            if (!empty($findings)) {
                $riskScore += 10; // Base risk

                if (count($findings) > 5) {
                    $riskScore += 40;
                    $riskAnalysis[] = "ALTA EXPOSICION: El objetivo aparece en multiples filtraciones, indicando una huella digital comprometida a largo plazo.";
                } else {
                    $riskScore += 20;
                    $riskAnalysis[] = "EXPOSICION MODERADA: Credenciales comprometidas en incidentes aislados.";
                }

                $riskAnalysis[] = "VECTORES DETECTADOS: Email y Contrasenias potenciales. Esto facilita ataques de Credential Stuffing contra sistemas corporativos.";
            } else {
                $riskAnalysis[] = "BAJA EXPOSICION: No se detectaron brechas publicas mayores vinculadas directamente.";
            }

            $riskLevel = $riskScore > 40 ? "CRITICO" : ($riskScore > 10 ? "MEDIO" : "BAJO");


            // PDF GENERATION (STRICT HELVETICA ONLY)
            // Error Fix: courier and arial are missing, using helvetica only.
            $pdf = new FPDF();
            $pdf->AddPage();

            // -- HEADER --
            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->Cell(0, 10, 'CONFIDENTIAL // EYES ONLY', 0, 1, 'C');
            $pdf->SetLineWidth(0.5);
            $pdf->Line(10, 20, 200, 20);
            $pdf->Ln(5);

            $pdf->SetFont('Helvetica', 'B', 24);
            $pdf->Cell(0, 15, 'MAPA-RD INTEL DOSSIER', 0, 1, 'L');

            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(0, 5, 'TARGET: ' . strtoupper($job['email']), 0, 1);
            $pdf->Cell(0, 5, 'REF ID: ' . $jobId, 0, 1);
            $pdf->Cell(0, 5, 'DATE:   ' . date('Y-m-d H:i:s T'), 0, 1);
            $pdf->Cell(0, 5, 'RISK LEVEL: ' . $riskLevel, 0, 1);
            $pdf->Ln(10);

            // -- EXECUTIVE SUMMARY --
            $pdf->SetFillColor(0, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 8, ' 1. RESUMEN EJECUTIVO DE AMENAZA', 1, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Ln(2);
            foreach ($riskAnalysis as $analysis) {
                // Formatting UTF8 for FPDF (Latin1)
                $pdf->MultiCell(0, 5, "- " . iconv('UTF-8', 'windows-1252//TRANSLIT', $analysis));
                $pdf->Ln(1);
            }
            $pdf->Ln(5);

            // -- FINDINGS --
            $pdf->SetFillColor(0, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 8, ' 2. EVIDENCIA DE COMPROMISO (RAW INTEL)', 1, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Ln(2);
            foreach ($findings as $f) {
                $pdf->Cell(0, 5, '> ' . substr($f, 0, 90), 0, 1);
            }
            if (empty($findings)) {
                $pdf->Cell(0, 5, '> NO DATA FOUND.', 0, 1);
            }
            $pdf->Ln(8);

            // -- GLOSSARY --
            $pdf->SetFillColor(0, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 8, ' 3. GLOSARIO TACTICO OPERATIVO', 1, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Ln(2);
            foreach ($glossary as $term => $def) {
                $pdf->SetFont('Helvetica', 'B', 9);
                $pdf->Cell(40, 5, $term . ':', 0, 0);
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->MultiCell(0, 5, iconv('UTF-8', 'windows-1252//TRANSLIT', $def));
                $pdf->Ln(1);
            }
            $pdf->Ln(5);

            // -- MITIGATION --
            $pdf->SetFillColor(0, 0, 0);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->Cell(0, 8, ' 4. PROTOCOLO DE MITIGACION', 1, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Ln(2);
            foreach ($mitigationProtocols as $title => $step) {
                $pdf->SetFont('Helvetica', 'B', 9);
                $pdf->Cell(0, 5, iconv('UTF-8', 'windows-1252//TRANSLIT', $title), 0, 1);
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->MultiCell(0, 5, iconv('UTF-8', 'windows-1252//TRANSLIT', $step));
                $pdf->Ln(2);
            }


            $pdf->Ln(10);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->MultiCell(0, 4, "AVISO LEGAL:\nEste reporte es generado automaticamente para fines de auditoria de seguridad.\nEl usuario es responsable de custodiar esta informacion sensible.\nGenerado por MAPA-RD Engine v2.1 (Black Ops Level).");

            $reportName = 'report_' . $jobId . '.pdf';
            $reportsDir = __DIR__ . '/reports';
            if (!is_dir($reportsDir)) {
                if (!mkdir($reportsDir, 0777, true)) {
                    throw new Exception("Failed to create reports directory at $reportsDir");
                }
            }

            $reportPath = $reportsDir . '/' . $reportName;
            $pdf->Output('F', $reportPath);

            $resultUrl = "/api/reports/$reportName"; // Return relative path that API routes handle
            addLog($logs, "SCAN COMPLETE. Report generated.", "success");

            // Save Final State
            $stmt = $pdo->prepare("UPDATE scans SET status='COMPLETED', logs=?, result_path=?, findings=? WHERE job_id=?");
            $stmt->execute([json_encode($logs), $resultUrl, json_encode($findings), $jobId]);

            echo json_encode([
                "job_id" => $jobId,
                "status" => "COMPLETED",
                "logs" => $logs,
                "result_url" => $resultUrl
            ]);
            exit;

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["error" => "OSINT Execution Failed", "details" => $e->getMessage(), "trace" => $e->getTraceAsString()]);
            exit;
        }
    }
}

// Serve PDF Report
if (isset($pathParams[1]) && $pathParams[1] === 'reports' && isset($pathParams[2])) {
    $file = __DIR__ . '/reports/' . basename($pathParams[2]);
    if (file_exists($file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    }
    http_response_code(404);
    echo "Report not found";
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Not Found"]);
