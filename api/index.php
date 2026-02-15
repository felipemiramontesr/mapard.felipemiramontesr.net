<?php
// Enable Error Reporting for Debugging (Return JSON, not HTML)
ini_set('display_errors', 0); // Reverting to 0 to prevent JSON breakage from Notice messages
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Robust Include
require_once __DIR__ . '/config.php';
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
            ["message" => "Iniciando Protocolo MAPARD...", "type" => "info", "timestamp" => date('c')]
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
            $publicProviders = [
                'gmail.com',
                'yahoo.com',
                'hotmail.com',
                'outlook.com',
                'icloud.com',
                'protonmail.com',
                'proton.me',
                'live.com'
            ];

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
// HIBP_API_KEY defined in config.php
            $targetEmail = $job['email'];
            addLog($logs, "Querying Intelligence Databases for $targetEmail...", "info");

            $ch = curl_init("https://haveibeenpwned.com/api/v3/breachedaccount/" . urlencode($targetEmail) .
                "?truncateResponse=false");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "hibp-api-key: " . trim(HIBP_API_KEY),
                "user-agent: MAPARD-OSINT-AGENT"
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
                    $breachData[] = [
                        'name' => $b['Name'],
                        'date' => $b['BreachDate'],
                        'classes' => $b['DataClasses'],
                        'description' => strip_tags($b['Description'])
                    ];
                    $findings[] = "Breach: " . $b['Name'];
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

            // Step 3: Gemini AI Intelligence (Project CORTEX)
            $aiIntel = null;
            if (!empty($breaches)) {
                require_once __DIR__ . '/services/GeminiService.php';
                addLog($logs, "Invoking CORTEX Neural Engine (Gemini 1.5 Pro)...", "info");
                $gemini = new GeminiService();
                $aiIntel = $gemini->analyzeBreach($breachData);

                if ($aiIntel) {
                    addLog($logs, "Threat Analysis Complete. Level: " . $aiIntel['threat_level'], "success");
                    // Override static risk level with AI assessment
                    $riskLevel = strtoupper($aiIntel['threat_level']);
                } else {
                    addLog($logs, "AI Unreachable. Fallback to static protocols.", "warning");
                }
            }

            // Step 4: Complete & Generate PDF
            addLog($logs, "Generating Intelligence Report...", "info");

            // --- PROFESSIONAL SPANISH (v7 - ZERO ENGLISH) ---

            function text_sanitize($str)
            {
                $str = html_entity_decode($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
                return iconv('UTF-8', 'windows-1252//TRANSLIT', $str);
            }

            $glossary = [
                "Brecha de Datos" => "Incidente de seguridad donde información confidencial y credenciales son exfiltradas y expuestas
públicamente.",
                "Stealer Log" => "Archivos de datos extraídos ilícitamente desde dispositivos infectados con malware (InfoStealers).",
                "Lista Combinada" => "Bases de datos de usuario:contraseña recopiladas para ataques masivos de prueba de acceso
(Credential Stuffing).",
                "Texto Plano" => "Almacenamiento inseguro de contraseñas sin cifrado, permitiendo su lectura directa por atacantes."
            ];

            $mitigationProtocols = [
                "1. Rotación de Credenciales" => "Proceda inmediatamente al cambio de contraseñas en todos los servicios afectados. No
reutilice claves antiguas.",
                "2. Autenticación Robusta (2FA)" => "Implemente Segundo Factor de Autenticación mediante aplicaciones generadoras de
tokens o llaves físicas de seguridad.",
                "3. Gestión de Identidad" => "Utilice gestores de contraseñas cifrados para asegurar que cada acceso posea una
credencial única y compleja.",
                "4. Vigilancia Activa" => "Mantenga un monitoreo continuo de sus activos digitales mediante servicios de alertas de
identidad y dark web."
            ];

            // EXTENDED DICTIONARY
            function translate_data_class($class)
            {
                $map = [
                    'Email addresses' => 'Correos electrónicos',
                    'Passwords' => 'Contraseñas',
                    'Usernames' => 'Nombres de usuario',
                    'IP addresses' => 'Direcciones IP',
                    'Names' => 'Nombres completos',
                    'Phone numbers' => 'Teléfonos',
                    'Physical addresses' => 'Direcciones físicas',
                    'Dates of birth' => 'Fechas de nacimiento',
                    'Geographic locations' => 'Ubicación geográfica',
                    'Social security numbers' => 'Números de Seguro Social',
                    'Credit card numbers' => 'Tarjetas de crédito',
                    'Bank account numbers' => 'Cuentas bancarias',
                    'Job titles' => 'Puestos laborales',
                    'Social media profiles' => 'Perfiles sociales',
                    'Genders' => 'Género',
                    'Password hints' => 'Pistas de contraseña',
                    'Spoken languages' => 'Idiomas',
                    'Time zones' => 'Zona horaria',
                    'Device information' => 'Información de dispositivo',
                    'Browser user agent details' => 'Detalles de navegador',
                    'Employers' => 'Empleadores'
                ];
                return $map[$class] ?? $class;
            }

            // TACTICAL TEMPLATE GENERATOR
            function generate_spanish_description($name, $date)
            {
                return "Se ha verificado un incidente de seguridad que afectó a la plataforma/servicio \"$name\". El evento fue
registrado originalmente el $date. Esta brecha resultó en la exfiltración y exposición pública de activos digitales
críticos asociados a los usuarios registrados.";
            }

            // Risk Scoring
            $riskScore = 0;
            if (!empty($breachData)) {
                $riskScore += 20;
                if (count($breachData) > 5)
                    $riskScore += 50;
                else
                    $riskScore += 30;
            }

            // Define colors based on risk
            if ($riskLevel === 'CRITICAL' || $riskLevel === 'CRÍTICO')
                $riskColor = [255, 0, 80]; // Neon Red
            elseif ($riskLevel === 'HIGH' || $riskLevel === 'ALTO')
                $riskColor = [255, 130, 0]; // Orange
            elseif ($riskLevel === 'MEDIUM' || $riskLevel === 'MEDIO')
                $riskColor = [255, 200, 0]; // Amber
            else
                $riskColor = [0, 243, 255]; // Cyan (Low)

            // PDF CLASS (BRANDED: MAPA-RD STYLE GUIDE) - DEEP INTEGRATION V3
            class PDF extends FPDF
            {
                function Header()
                {
                    $this->SetFillColor(10, 14, 39);
                    $this->Rect(0, 0, 210, 35, 'F');

                    $this->SetDrawColor(138, 159, 202);
                    $this->SetLineWidth(0.5);
                    $this->Line(0, 35, 210, 35);

                    $this->SetTextColor(255, 255, 255);
                    $this->SetFont('Helvetica', 'B', 16);
                    $this->SetXY(12, 10);
                    $this->Cell(0, 10, 'MAPARD - DOSSIER DE INTELIGENCIA', 0, 1, 'L');

                    $this->SetFont('Helvetica', '', 8);
                    $this->SetXY(12, 18);
                    $this->SetTextColor(138, 159, 202);
                    $this->Cell(0, 5, utf8_decode('CIBERINTELIGENCIA & ANÁLISIS FORENSE'), 0, 1, 'L');
                }

                function Footer()
                {
                    $this->SetY(-20);
                    $this->SetDrawColor(200, 200, 200);
                    $this->Line(10, $this->GetY(), 200, $this->GetY());

                    $this->SetY(-15);
                    $this->SetFont('Helvetica', '', 8);
                    $this->SetTextColor(107, 116, 144);

                    $this->SetX(10);
                    $this->Cell(0, 10, 'CONFIDENCIAL / USO INTERNO', 0, 0, 'L');

                    $this->SetX(-30);
                    $this->Cell(0, 10, text_sanitize('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
                }

                function CheckPageSpace($h)
                {
                    if ($this->GetY() + $h > $this->PageBreakTrigger) {
                        $this->AddPage($this->CurOrientation);
                        $this->SetY(45); // Reset below header
                    }
                }

                function SectionTitle($title)
                {
                    $this->CheckPageSpace(25);
                    if ($this->GetY() < 45)
                        $this->SetY(45);

                    $this->Ln(10);
                    $this->SetFont('Helvetica', 'B', 12);
                    $this->SetTextColor(26, 31, 58);
                    $this->Cell(0, 8, text_sanitize($title), 0, 1, 'L');

                    $this->SetDrawColor(138, 159, 202);
                    $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 190, $this->GetY());
                    $this->Ln(6);
                }

                // UNIFIED INTELLIGENCE CARD (Breach Data + AI Story + Actions)
                function RenderIntelCard($breach, $analysis, $riskColor)
                {
                    $source = $breach['name'];
                    $date = $breach['date'];

                    // Fallbacks with "Intelligence Needed" markers if API fails
                    $story = isset($analysis['incident_story']) && !empty($analysis['incident_story'])
                        ? $analysis['incident_story']
                        : "El reporte de inteligencia para $source no pudo recuperar el contexto histórico específico. Se trató de una
    vulneración de datos registrada en $date.";

                    $risk = isset($analysis['risk_explanation'])
                        ? $analysis['risk_explanation']
                        : "La exposición de sus datos en este servicio aumenta el riesgo de suplantación de identidad.";

                    // ACTIONS: Expecting Array, fallback to string if legacy
                    $rawActions = isset($analysis['specific_remediation']) ? $analysis['specific_remediation'] : ["Cambie su contraseña
    inmediatamente."
                    ];
                    if (is_string($rawActions)) {
                        $rawActions = [$rawActions];
                    }

                    // Data Classes
                    $classes = "Expuesto: " . implode(", ", array_map('translate_data_class', $breach['classes']));

                    // Text height estimation
                    $lineH = 4.5;
                    $storyLines = ceil(strlen($story) / 90);
                    $riskLines = ceil(strlen($risk) / 90);

                    // Calculate Action Box Height (List)
                    $actionsHeight = 0;
                    foreach ($rawActions as $act) {
                        $actionsHeight += (ceil(strlen($act) / 85) * $lineH) + 2;
                    }
                    $actionsHeight += 8; // Padding

                    $classLines = ceil(strlen($classes) / 90);

                    // Dynamic Height Calculation
                    $cardHeight = 45 + ($storyLines * $lineH) + ($riskLines * $lineH) + $actionsHeight + ($classLines * $lineH);

                    $this->CheckPageSpace($cardHeight);
                    if ($this->GetY() < 45)
                        $this->SetY(45);

                    $baseY = $this->GetY();

                    // Container Box
                    $this->SetFillColor(255, 255, 255);
                    $this->SetDrawColor(200, 209, 224);
                    $this->SetLineWidth(0.2);
                    $this->Rect(10, $baseY, 190, $cardHeight, 'DF');

                    // Left Status Bar
                    $this->SetFillColor($riskColor[0], $riskColor[1], $riskColor[2]);
                    $this->Rect(10, $baseY, 2, $cardHeight, 'F');

                    // Header Row: Source + Date
                    $this->SetXY(16, $baseY + 5);
                    $this->SetFont('Helvetica', 'B', 12);
                    $this->SetTextColor(26, 31, 58);
                    $this->Cell(120, 6, text_sanitize($source), 0, 0);

                    $this->SetFont('Helvetica', '', 9);
                    $this->SetTextColor(107, 116, 144);
                    $this->Cell(60, 6, text_sanitize("Incidente: $date"), 0, 1, 'R');

                    // Data Classes (Subtext)
                    $this->SetX(16);
                    $this->SetFont('Helvetica', '', 8);
                    $this->SetTextColor(138, 159, 202);
                    $this->MultiCell(180, $lineH, text_sanitize($classes));

                    $this->Ln(2);
                    $this->SetDrawColor(240, 240, 240);
                    $this->Line(16, $this->GetY(), 195, $this->GetY());
                    $this->Ln(4);

                    // 1. STORY (Contexto)
                    $this->SetX(16);
                    $this->SetFont('Helvetica', 'B', 9);
                    $this->SetTextColor(26, 31, 58);
                    $this->Cell(0, 5, utf8_decode("¿QUÉ PASÓ? (Contexto):"), 0, 1);

                    $this->SetX(16);
                    $this->SetFont('Helvetica', '', 9);
                    $this->SetTextColor(60, 70, 90);
                    $this->MultiCell(180, $lineH, text_sanitize($story));
                    $this->Ln(3);

                    // 2. RISK (Impacto Personal)
                    $this->SetX(16);
                    $this->SetFont('Helvetica', 'B', 9);
                    $this->SetTextColor(26, 31, 58);
                    $this->Cell(0, 5, utf8_decode("¿POR QUÉ ME AFECTA?:"), 0, 1);

                    $this->SetX(16);
                    $this->SetFont('Helvetica', '', 9);
                    $this->SetTextColor(60, 70, 90);
                    $this->MultiCell(180, $lineH, text_sanitize($risk));
                    $this->Ln(3);

                    // 3. ACTION (Remediation Box - Multi-step)
                    $remY = $this->GetY();
                    $this->SetFillColor(245, 250, 247); // Light Greenish
                    $this->SetDrawColor(200, 220, 210);
                    $this->Rect(15, $remY, 180, $actionsHeight, 'DF');

                    $this->SetXY(20, $remY + 3);
                    $this->SetFont('Helvetica', 'B', 9);
                    $this->SetTextColor(40, 120, 80); // Success Green
                    $this->Cell(0, 5, utf8_decode("PLAN DE ACCIÓN (3 PASOS):"), 0, 1);

                    $this->SetFont('Helvetica', '', 9);
                    $this->SetTextColor(50, 60, 70);

                    foreach ($rawActions as $i => $act) {
                        $this->SetX(20);
                        $this->Cell(5, $lineH, ($i + 1) . ".", 0, 0); // Numbering
                        $this->MultiCell(165, $lineH, text_sanitize($act));
                        $this->Ln(1); // Small spacer between items
                    }

                    $this->SetY($baseY + $cardHeight + 5);
                }

                function RenderExecutiveSummary($summary)
                {
                    $this->CheckPageSpace(40);
                    $this->SetFillColor(249, 250, 252);
                    $this->SetDrawColor(200, 209, 224);
                    $this->Rect(10, $this->GetY(), 190, 30, 'DF');

                    $this->SetXY(15, $this->GetY() + 5);
                    $this->SetFont('Helvetica', 'B', 10);
                    $this->SetTextColor(26, 31, 58);
                    $this->Cell(0, 6, utf8_decode("RESUMEN DE SEGURIDAD"), 0, 1);

                    $this->SetX(15);
                    $this->SetFont('Helvetica', '', 9);
                    $this->SetTextColor(71, 85, 105);
                    $this->MultiCell(180, 5, text_sanitize($summary));

                    $this->Ln(10);
                }
            }

            $pdf = new PDF();
            $pdf->AliasNbPages();
            $pdf->SetMargins(10, 40, 10);
            $pdf->SetAutoPageBreak(true, 20);

            $pdf->AddPage();

            // Metadata Block
            $pdf->SetY(40);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(107, 116, 144);
            $pdf->Cell(35, 6, text_sanitize('Objetivo:'), 0, 0);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(26, 31, 58);
            $pdf->Cell(0, 6, $job['email'], 0, 1);

            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(107, 116, 144);
            $pdf->Cell(35, 6, text_sanitize('Fecha de Emisión:'), 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(26, 31, 58);
            $pdf->Cell(0, 6, date("d/m/Y H:i:s T"), 0, 1);

            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetTextColor(107, 116, 144);
            $pdf->Cell(35, 6, text_sanitize('Nivel de Riesgo:'), 0, 0);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor($riskColor[0], $riskColor[1], $riskColor[2]);
            $pdf->Cell(0, 6, text_sanitize($riskLevel), 0, 1);
            $pdf->SetTextColor(0);
            $pdf->Ln(5);

            // 1. EXECUTIVE SUMMARY
            if ($aiIntel && isset($aiIntel['executive_summary'])) {
                $pdf->RenderExecutiveSummary($aiIntel['executive_summary']);
            }

            // 2. INCIDENT STORY CARDS
            $pdf->SectionTitle("1. Análisis Detallado de Incidentes");

            $aiAnalysisArray = $aiIntel['detailed_analysis'] ?? [];

            // SUPER DEBUG BLOCK
            $pdf->SetFont('Helvetica', '', 6);
            $pdf->SetTextColor(255, 0, 0);

            $debugInfo = "DEBUG DIAGNOSTIC:\n";
            $debugInfo .= "Breaches Input: " . count($breachData) . "\n";
            $debugInfo .= "AI Response Type: " . gettype($aiIntel) . "\n";
            $debugInfo .= "AI Response Keys: " . json_encode(is_array($aiIntel) ? array_keys($aiIntel) : []) . "\n";
            $debugInfo .= "Detailed Analysis Count: " . count($aiAnalysisArray) . "\n";

            // Check first item contents if exists
            if (!empty($aiAnalysisArray)) {
                $debugInfo .= "First Item Keys: " . json_encode(array_keys($aiAnalysisArray[0])) . "\n";
            } else {
                $debugInfo .= "AI Intel Raw Dump: " . substr(json_encode($aiIntel), 0, 300) . "...\n";
            }

            $pdf->MultiCell(0, 3, $debugInfo);
            $pdf->Ln(5);

            if (empty($breaches)) {
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->Cell(
                    0,
                    10,
                    text_sanitize("¡Buenas noticias! No encontramos incidentes públicos asociados a tu correo."),
                    0,
                    1
                );
            } else {

                foreach ($breachData as $index => $b) {
                    // ROBUST MATCHING LOGIC (V2 - Fuzzy)
                    $matchedAnalysis = [];

                    // 1. Try Precise/Fuzzy Name Match
                    foreach ($aiAnalysisArray as $analysis) {
                        if (!isset($analysis['source_name']))
                            continue;

                        $aiName = trim(strtolower($analysis['source_name']));
                        $breachName = trim(strtolower($b['name']));

                        // Exact match OR containment (e.g. "Adobe Systems" contains "adobe")
                        if ($aiName === $breachName || strpos($aiName, $breachName) !== false || strpos($breachName, $aiName) !== false) {
                            $matchedAnalysis = $analysis;
                            break;
                        }
                    }

                    // 2. Fallback to Index if Name match fails (and index exists)
                    if (empty($matchedAnalysis) && isset($aiAnalysisArray[$index])) {
                        $matchedAnalysis = $aiAnalysisArray[$index];
                    }

                    $pdf->RenderIntelCard($b, $matchedAnalysis, $riskColor);
                }
            }

            // 3. DYNAMIC GLOSSARY
            if ($aiIntel && isset($aiIntel['dynamic_glossary']) && !empty($aiIntel['dynamic_glossary'])) {
                $pdf->CheckPageSpace(60);
                $pdf->SectionTitle("2. Glosario de Términos (Explicado Simple)");

                foreach ($aiIntel['dynamic_glossary'] as $term => $def) {
                    $pdf->CheckPageSpace(20);
                    $pdf->SetFont('Helvetica', 'B', 9);
                    $pdf->SetTextColor(138, 159, 202); // Brand Accent
                    $pdf->Cell(50, 5, text_sanitize($term . ":"), 0, 0);

                    $pdf->SetFont('Helvetica', '', 9);
                    $pdf->SetTextColor(107, 116, 144);
                    $pdf->MultiCell(0, 5, text_sanitize($def));
                    $pdf->Ln(2);
                }
            }

            // Generated Footer
            $pdf->Ln(10);
            $pdf->SetDrawColor(200);
            $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
            $pdf->Ln(5);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(150);
            $pdf->MultiCell(0, 3, text_sanitize("Generado por MAPARD Neural Engine. Este reporte es para fines educativos y
        de concienciación."));
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(150);
            $pdf->MultiCell(0, 3, text_sanitize("AVISO LEGAL: Inteligencia de Fuentes Abiertas (OSINT). Generado por la
        Plataforma MAPARD."));

            // SAVE & EXIT logic remains same...
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
            echo json_encode([
                "error" => "OSINT Execution Failed",
                "details" => $e->getMessage(),
                "trace" =>
                    $e->getTraceAsString()
            ]);
            exit;
        }
    }
}

// Serve PDF Report
if (isset($pathParams[1]) && $pathParams[1] === 'reports' && isset($pathParams[2])) {
    $file = __DIR__ . '/reports/' . basename($pathParams[2]);
    if (file_exists($file)) {
        // Prevent Caching
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0");
        header("Pragma: no-cache");

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