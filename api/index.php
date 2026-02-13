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

        if (empty($domain) && strpos($email, '@') !== false) {
            $parts = explode('@', $email);
            $domain = array_pop($parts);
        }

        $jobId = uniqid('job_', true);
        $initialLogs = json_encode([
            ["message" => "Initializing MAPA-RD Protocol...", "type" => "info", "timestamp" => date('c')]
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

            // Step 1: DNS Recon (Smart Filtered)
            $publicProviders = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com', 'protonmail.com', 'proton.me', 'live.com'];

            if ($domain && !in_array(strtolower($domain), $publicProviders)) {
                if (!function_exists('dns_get_record')) {
                    addLog($logs, "DNS functions disabled on this server.", "warning");
                } else {
                    addLog($logs, "Resolving DNS records for $domain...", "info");
                    $dns = @dns_get_record($domain, DNS_A + DNS_MX);
                    if ($dns) {
                        $count = count($dns);
                        addLog($logs, "Found $count DNS records.", "success");
                        // Only add count to finding to avoid noise in logs, detailed findings will be in PDF
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
            } else {
                addLog($logs, "Skipping DNS Recon for public provider ($domain).", "info");
            }

            // Step 2: HIBP Breach Check (Real API)
            $hibpApiKey = '011373b46b674891b6f19772c2205772';
            $targetEmail = $job['email'];
            addLog($logs, "Querying Intelligence Databases for $targetEmail...", "info");

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

            $breachData = [];

            if ($httpCode === 200) {
                $breaches = json_decode($response, true);
                $count = count($breaches);
                addLog($logs, "CRITICAL: Found $count compromised credentials.", "error");
                foreach ($breaches as $b) {
                    // Store detailed object for PDF
                    $breachData[] = [
                        'name' => $b['Name'],
                        'date' => $b['BreachDate'],
                        'classes' => $b['DataClasses'],
                        'description' => strip_tags($b['Description']) // Clean HTML
                    ];
                    $findings[] = "Breach: " . $b['Name']; // Simple string for logs/db
                }
            } elseif ($httpCode === 404) {
                addLog($logs, "No public breaches found for this email.", "success");
                $findings[] = "Breach Analysis: Clean (No public leaks found).";
            } elseif ($httpCode === 429) {
                addLog($logs, "Rate Limit Exceeded. Skipping breach check.", "warning");
                $findings[] = "Breach Analysis: Skipped (Rate Limit).";
            } elseif ($httpCode === 401) {
                addLog($logs, "API Key Invalid. Checking configuration.", "error");
                $findings[] = "Breach Analysis: Failed (Auth Error).";
            } else {
                addLog($logs, "API Error ($httpCode): " . ($curlError ?: 'Unknown'), "error");
                $findings[] = "Breach Analysis: Failed (API Error $httpCode).";
            }

            // Step 3: Complete & Generate PDF
            addLog($logs, "Generating Intelligence Report...", "info");

            // --- PROFESSIONAL STYLE GUIDE REPORT (v5) ---

            // Helpers
            function utf8_to_iso($str)
            {
                return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $str);
            }

            $glossary = [
                "Data Breach" => "Incidente de seguridad donde informacion confidencial y credenciales son exfiltradas y expuestas.",
                "Stealer Log" => "Archivos de datos extraidos ilicitamente desde dispositivos infectados con malware.",
                "Combo List" => "Bases de datos de usuario:contrasena recopiladas para ataques masivos de prueba de acceso.",
                "Plaintext" => "Almacenamiento inseguro de contrasenas sin cifrado, permitiendo su lectura directa."
            ];

            $mitigationProtocols = [
                "1. ROTACION DE CREDENCIALES" => "Proceda inmediatamente al cambio de contrasenas en todos los servicios afectados. No reutilice claves antiguas.",
                "2. AUTENTICACION ROBUSTA (2FA)" => "Implemente Segundo Factor de Autenticacion mediante aplicaciones generadoras de tokens o llaves fisicas de seguridad.",
                "3. GESTION DE IDENTIDAD" => "Utilice gestores de contrasenas cifrados para asegurar que cada acceso posea una credencial unica y compleja.",
                "4. VIGILANCIA ACTIVA" => "Mantenga un monitoreo continuo de sus activos digitales mediante servicios de alertas de identidad y dark web."
            ];

            // Risk Scoring
            $riskScore = 0;
            if (!empty($breachData)) {
                $riskScore += 20;
                if (count($breachData) > 5)
                    $riskScore += 50;
                else
                    $riskScore += 30;
            }
            $riskLevel = $riskScore > 60 ? "CRITICO" : ($riskScore > 20 ? "ALTO" : "BAJO");

            // PDF CLASS - STYLE GUIDE ALIGNED
            class PDF extends FPDF
            {
                function Header()
                {
                    // STYLE GUIDE: Header Background (Navy #0a0e27)
                    $this->SetFillColor(10, 14, 39);
                    $this->Rect(0, 0, 210, 40, 'F');

                    // STYLE GUIDE: Accent Line (Lavender #8a9fca) NOT CYAN
                    $this->SetDrawColor(138, 159, 202);
                    $this->SetlineWidth(0.5);
                    $this->Line(0, 39.5, 210, 39.5);

                    // Title
                    $this->SetTextColor(255, 255, 255);
                    $this->SetFont('Helvetica', 'B', 16);
                    $this->SetXY(12, 12);
                    // Corrected Title
                    $this->Cell(0, 10, 'MAPARD - DOSSIER DE INTELIGENCIA', 0, 1, 'L');

                    $this->SetFont('Helvetica', '', 8);
                    $this->SetXY(12, 20);
                    // STYLE GUIDE: Subtitle Accent (Lavender #8a9fca)
                    $this->SetTextColor(138, 159, 202);
                    $this->Cell(0, 5, 'MOTOR DE INTELIGENCIA NIVEL BLACK-OPS', 0, 1, 'L');
                }

                function Footer()
                {
                    $this->SetY(-15);
                    $this->SetFont('Helvetica', '', 7);
                    $this->SetTextColor(128, 128, 128);
                    $this->Cell(0, 10, 'CONFIDENCIAL // PAGINA ' . $this->PageNo(), 0, 0, 'C');
                }

                function CheckPageSpace($h)
                {
                    if ($this->GetY() + $h > $this->PageBreakTrigger) {
                        $this->AddPage($this->CurOrientation);
                        $this->SetY(50); // HARD RESET Y AFTER PAGE BREAK
                    }
                }

                function SectionTitle($title)
                {
                    $this->CheckPageSpace(25);
                    if ($this->GetY() < 50)
                        $this->SetY(50); // Safety Barrier

                    $this->Ln(5);
                    // STYLE GUIDE: H2 Uppercase, Medium Weight (Simulated)
                    $this->SetFont('Helvetica', 'B', 11);
                    // STYLE GUIDE: Navy Text (#1a1f3a)
                    $this->SetTextColor(26, 31, 58);
                    $this->Cell(0, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', strtoupper($title)), 0, 1, 'L');

                    // STYLE GUIDE: Separator Line (Border UI #4a5578)
                    $this->SetDrawColor(74, 85, 120);
                    $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 190, $this->GetY());
                    $this->Ln(8);
                }

                function BreachCard($name, $date, $classes, $description)
                {
                    $descHeight = ceil(strlen($description) / 100) * 4;
                    // Extra space for headers/padding
                    $cardHeight = 35 + $descHeight;

                    $this->CheckPageSpace($cardHeight);
                    if ($this->GetY() < 50)
                        $this->SetY(50); // Safety Barrier checks

                    // STYLE GUIDE: Card Background (Glassmorphism Simulation on Print)
                    // Very light blue/gray (#f9f9fc)
                    $this->SetFillColor(249, 249, 252);
                    $this->SetDrawColor(138, 159, 202); // Lavender Border
                    $this->SetLineWidth(0.1);

                    // Draw Card Box
                    $x = $this->GetX();
                    $y = $this->GetY();
                    $w = 190; // Page width - strict margins

                    // Draw Rect
                    $this->Rect($x, $y, $w, $cardHeight, 'DF');

                    // Inside Card Padding
                    $this->SetXY($x + 5, $y + 5);

                    // Header
                    $this->SetFont('Helvetica', 'B', 10);
                    $this->SetTextColor(26, 31, 58); // Navy
                    $this->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', "BRECHA: " . $name), 0, 1, 'L');

                    // Subheader (Date)
                    $this->SetX($x + 5);
                    $this->SetFont('Helvetica', '', 8);
                    $this->SetTextColor(107, 116, 144); // Text Tertiary (#6b7490)
                    $this->Cell(0, 5, "FECHA: " . $date, 0, 1);

                    // Data Classes highlight
                    $this->SetX($x + 5);
                    $this->SetTextColor(90, 111, 160); // Primary Button Color approx for emphasis
                    $classesStr = "DATOS: " . implode(", ", $classes);
                    $this->MultiCell(180, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $classesStr));

                    // Divider
                    $this->Ln(2);
                    $this->SetDrawColor(200, 200, 220);
                    $this->Line($x + 5, $this->GetY(), $x + 185, $this->GetY());
                    $this->Ln(3);

                    // Description
                    $this->SetX($x + 5);
                    $this->SetFont('Helvetica', '', 9);
                    $this->SetTextColor(26, 31, 58); // Primary Text
                    $this->MultiCell(180, 4, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', strip_tags($description)));

                    // Reset position after card
                    $this->SetY($y + $cardHeight + 5);
                }
            }

            $pdf = new PDF();
            $pdf->SetMargins(10, 50, 10); // INCREASED MARGIN TO 50mm (Header is 40mm)
            $pdf->SetAutoPageBreak(true, 20);

            $pdf->AddPage();

            // -- INFO TARGET --
            $pdf->SetY(50); // FORCE Y=50

            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(107, 116, 144); // Tertiary
            $pdf->Cell(25, 6, 'OBJETIVO:', 0, 0);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(26, 31, 58); // Primary
            $pdf->Cell(0, 6, $job['email'], 0, 1);

            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(107, 116, 144);
            $pdf->Cell(25, 6, 'ID REF:', 0, 0);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(26, 31, 58);
            $pdf->Cell(0, 6, $jobId, 0, 1);

            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(107, 116, 144);
            $pdf->Cell(25, 6, 'RIESGO:', 0, 0);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(200, 50, 50); // Warning/Error color
            $pdf->Cell(0, 6, $riskLevel, 0, 1);
            $pdf->SetTextColor(0);

            // -- 1. EVIDENCIA DE COMPROMISO --
            // STYLE GUIDE: H2 Uppercase
            $pdf->SectionTitle("1. EVIDENCIA DE COMPROMISO (INTELIGENCIA CRUDA)");

            if (empty($breaches)) {
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->Cell(0, 10, utf8_to_iso("No se encontraron brechas publicas asociadas a este objetivo."), 0, 1);
            } else {
                foreach ($breachData as $b) {
                    $pdf->BreachCard($b['name'], $b['date'], $b['classes'], $b['description']);
                }
            }

            // -- 2. GLOSARIO TACTICO --
            $pdf->CheckPageSpace(60);
            $pdf->SectionTitle("2. GLOSARIO TACTICO");
            foreach ($glossary as $term => $def) {
                $pdf->CheckPageSpace(20);
                $pdf->SetFont('Helvetica', 'B', 9);
                // STYLE GUIDE: Lavender Accent for Terms (#8a9fca)
                $pdf->SetTextColor(138, 159, 202);
                $pdf->Cell(40, 5, utf8_to_iso($term . ":"), 0, 0);
                $pdf->SetFont('Helvetica', '', 9);
                // STYLE GUIDE: Secondary Text (#c5cae0 approx on white -> #6b7490)
                $pdf->SetTextColor(107, 116, 144);
                $pdf->MultiCell(0, 5, utf8_to_iso($def));
                $pdf->Ln(2);
            }

            // -- 3. PROTOCOLO DE MITIGACION --
            // STYLE GUIDE: List components
            $pdf->CheckPageSpace(60);
            if (!empty($breaches)) {
                $pdf->SectionTitle("3. PROTOCOLO DE MITIGACION");

                foreach ($mitigationProtocols as $title => $step) {
                    $pdf->CheckPageSpace(25);

                    // Card style for steps
                    $thisY = $pdf->GetY();
                    $pdf->SetFillColor(249, 249, 252);
                    $pdf->SetDrawColor(138, 159, 202);
                    $pdf->Rect(10, $thisY, 190, 20, 'DF');

                    $pdf->SetXY(15, $thisY + 2);
                    $pdf->SetFont('Helvetica', 'B', 9);
                    $pdf->SetTextColor(26, 31, 58); // Navy
                    $pdf->Cell(0, 6, utf8_to_iso($title), 0, 1);

                    $pdf->SetXY(15, $thisY + 8);
                    $pdf->SetTextColor(107, 116, 144); // Tertiary
                    $pdf->SetFont('Helvetica', '', 8);
                    $pdf->MultiCell(180, 5, utf8_to_iso($step));

                    $pdf->SetY($thisY + 24);
                }
            }

            // -- DISCLAIMER --
            $pdf->Ln(10);
            $pdf->SetDrawColor(200);
            $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
            $pdf->Ln(5);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(150);
            $pdf->MultiCell(0, 3, utf8_to_iso("AVISO LEGAL:\nEste documento contiene inteligencia recolectada de fuentes de acceso publico (OSINT). Su proposito es estrictamente para auditoria de seguridad y concientizacion. La generacion de este reporte no implica intrusion activa ni acceso no autorizado a sistemas. El usuario final es responsable de la custodia de esta informacion."));

            $reportName = 'report_' . $jobId . '.pdf';
            $reportsDir = __DIR__ . '/reports';
            if (!is_dir($reportsDir)) {
                if (!mkdir($reportsDir, 0777, true)) {
                    throw new Exception("Failed to create reports directory at $reportsDir");
                }
            }

            $reportPath = $reportsDir . '/' . $reportName;
            $pdf->Output('F', $reportPath);

            $resultUrl = "/api/reports/$reportName";
            addLog($logs, "SCAN COMPLETE. Report generated.", "success");

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
