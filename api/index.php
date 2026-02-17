<?php

// Enable Error Reporting for Debugging (Return JSON, not HTML)
ini_set('display_errors', 0);
// Hide HTML errors to prevent JSON breakage
ini_set('log_errors', 1);
error_reporting(E_ALL);
// Load Composer Autoloader
require_once __DIR__ . '/vendor/autoload.php';
// Load Config
require_once __DIR__ . '/config.php';
use MapaRD\Services\GeminiService;
use MapaRD\Services\ReportService;
use function MapaRD\Services\text_sanitize;
use function MapaRD\Services\translate_data_class;
// api/index.php - Real OSINT Engine
// --------------------------------------------------------------------------
// 1. SECURITY HEADERS (NSA Level Defense)
// --------------------------------------------------------------------------
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'self';");
header("Content-Type: application/json");

// --------------------------------------------------------------------------
// 2. STRICT CORS (No Wildcards)
// --------------------------------------------------------------------------
$allowedOrigins = [
    'https://mapa-rd.felipemiramontesr.net',
    'http://localhost:5173', // Dev
    'http://localhost:4173'  // Preview
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
} else {
    // If not in allowed list, do not send CORS headers (Browser will block)
    // Optional: Return 403 immediately if strict
}

// --------------------------------------------------------------------------
// 3. RATE LIMITING (Token Bucket - File Based)
// --------------------------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$limitDir = __DIR__ . '/temp/ratelimit';
if (!is_dir($limitDir))
    @mkdir($limitDir, 0755, true);

$limitFile = $limitDir . '/' . md5($ip) . '.lock';
$now = time();
$window = 60; // 1 Minute
$limit = 10;  // Requests per minute

$data = @file_exists($limitFile) ? json_decode(file_get_contents($limitFile), true) : ['start' => $now, 'count' => 0];

if ($data['start'] < ($now - $window)) {
    $data = ['start' => $now, 'count' => 1]; // Reset window
} else {
    $data['count']++;
}

if ($data['count'] > $limit) {
    http_response_code(429);
    echo json_encode(["error" => "Rate Limit Exceeded. Try again in 60 seconds."]);
    exit;
}
@file_put_contents($limitFile, json_encode($data));
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
        $stmt = $pdo->prepare("INSERT INTO scans (job_id, email, domain, status, logs) VALUES (?, ?, ?, 'PENDING', ?)");
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

        // RACE CONDITION FIX: Prevent multiple executions
        if ($job['status'] === 'RUNNING') {
            // If it's been running for too long (> 2 mins), maybe reset?
            // For now, just return specific status so client keeps polling
            echo json_encode([
                "job_id" => $jobId,
                "status" => "RUNNING",
                "logs" => json_decode($job['logs']),
                "message" => "Scan in progress..."
            ]);
            exit;
        }

        // Only Execute if PENDING
        if ($job['status'] === 'PENDING') {
            // Lock it immediately
            $pdo->prepare("UPDATE scans SET status='RUNNING' WHERE job_id=?")->execute([$jobId]);
            $job['status'] = 'RUNNING';
            // Local update
        } else {
            // Should not happen if filtered correctly
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
                    if ($l['message'] === $msg) {
                        return;
                    }
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
                        $findings[] = "DNS Records Found: $count";
                        foreach ($dns as $r) {
                            if (isset($r['ip'])) {
                                $findings[] = "A Record: " . $r['ip'];
                            }
                            if (isset($r['target'])) {
                                $findings[] = "MX Record: " . $r['target'];
                            }
                        }
                    } else {
                        addLog($logs, "No DNS records found for $domain.", "warning");
                    }
                }
            } else {
                addLog($logs, "Skipping DNS Recon for public provider ($domain).", "info");
            }

            // Step 2: HIBP Breach Check (Real API)
            $targetEmail = $job['email'];
            addLog($logs, "Querying Intelligence Databases for $targetEmail...", "info");
            $ch = curl_init("https://haveibeenpwned.com/api/v3/breachedaccount/" . urlencode($targetEmail) . "?truncateResponse=false");
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
            if (!empty($breachData)) {
                // Autoloaded via Composer now
                addLog($logs, "Invoking CORTEX Neural Engine (Gemini 1.5 Pro)...", "info");
                $gemini = new GeminiService();
                $aiIntel = $gemini->analyzeBreach($breachData);
                if ($aiIntel) {
                    addLog($logs, "Threat Analysis Complete. Level: " . $aiIntel['threat_level'], "success");
                    $riskLevel = strtoupper($aiIntel['threat_level']);
                    // --- FORCE FULL COUNT ---
                    if (!isset($aiIntel['detailed_analysis']) || !is_array($aiIntel['detailed_analysis'])) {
                        $aiIntel['detailed_analysis'] = [];
                    }

                    $analyzedCount = count($aiIntel['detailed_analysis']);
                    $inputCount = count($breachData);
                    if ($analyzedCount < $inputCount) {
                        addLog($logs, "AI returned partial list ($analyzedCount/$inputCount). Filling gaps...", "warning");
                        $analyzedNames = [];
                        foreach ($aiIntel['detailed_analysis'] as $a) {
                            if (isset($a['source_name'])) {
                                $analyzedNames[strtolower($a['source_name'])] = true;
                            }
                        }

                        foreach ($breachData as $b) {
                            if (!isset($analyzedNames[strtolower($b['name'])])) {
                                $aiIntel['detailed_analysis'][] = [
                                    'source_name' => $b['name'],
                                    'incident_story' => "Analizado automáticamente (IA Saturada). Datos del incidente: " . $b['description'],
                                    'risk_explanation' => "Exposición de: " . implode(", ", $b['classes']),
                                    'specific_remediation' => ["Cambiar contraseña en " . $b['name'], "Activar 2FA si está disponible", "Verificar reutilización de claves"]
                                ];
                            }
                        }
                    }
                } else {
                    addLog($logs, "AI Unreachable. Fallback to static protocols.", "warning");
                }
            } else {
                $riskLevel = "LOW";
            }

            // Step 4: Complete & Generate PDF via ReportService
            addLog($logs, "Generating Intelligence Report (v2 Service)...", "info");
            $pdf = new ReportService();
            $pdf->AliasNbPages();
            $pdf->AddPage();
            // Define colors based on risk
            if ($riskLevel === 'CRITICAL' || $riskLevel === 'CRÍTICO') {
                $riskColor = [255, 0, 80];
            } elseif ($riskLevel === 'HIGH' || $riskLevel === 'ALTO') {
                $riskColor = [255, 130, 0];
            } elseif ($riskLevel === 'MEDIUM' || $riskLevel === 'MEDIO') {
                $riskColor = [255, 200, 0];
            } else {
                $riskColor = [0, 243, 255];
            }

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
            $pdf->SectionTitle("1. Análisis Detallado (Auditoría Forense)");
            $aiAnalysisArray = $aiIntel['detailed_analysis'] ?? [];
            $analyzedSources = [];
            foreach ($aiAnalysisArray as $analysis) {
                if (!isset($analysis['source_name'])) {
                    continue;
                }

                $originalBreach = null;
                foreach ($breachData as $b) {
                    if (
                        strpos(strtolower($b['name']), strtolower($analysis['source_name'])) !== false ||
                        strpos(strtolower($analysis['source_name']), strtolower($b['name'])) !== false
                    ) {
                        $originalBreach = $b;
                        break;
                    }
                }

                if (!$originalBreach) {
                    $originalBreach = [
                        'name' => $analysis['source_name'],
                        'date' => 'Fecha desconocida',
                        'classes' => []
                    ];
                }

                $analyzedSources[] = strtolower($originalBreach['name']);
                $pdf->RenderIntelCard($originalBreach, $analysis, $riskColor);
                $pdf->Ln(5);
            }

            // 3. AUDIT LOG (Remaining)
            $remainingBreaches = [];
            foreach ($breachData as $b) {
                if (!in_array(strtolower($b['name']), $analyzedSources)) {
                    $remainingBreaches[] = $b;
                }
            }

            if (count($remainingBreaches) > 0) {
                $pdf->AddPage();
                $pdf->SectionTitle("2. Auditoría Completa de Brechas");
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetTextColor(71, 85, 105);
                $pdf->MultiCell(190, 6, utf8_decode("Además de los incidentes críticos detallados anteriormente, se han detectado los siguientes compromisos de seguridad que requieren atención secundaria pero constante (Puertas Traseras)."), 0, 'L');
                $pdf->Ln(5);
                $pdf->SetFillColor(240, 242, 245);
                $pdf->SetFont('Helvetica', 'B', 9);
                $pdf->Cell(60, 8, 'Fuente', 1, 0, 'L', true);
                $pdf->Cell(30, 8, 'Fecha', 1, 0, 'L', true);
                $pdf->Cell(100, 8, utf8_decode('Datos Expuestos'), 1, 1, 'L', true);
                $pdf->SetFont('Helvetica', '', 8);
                foreach ($remainingBreaches as $rb) {
                    $classesStr = implode(", ", array_map('MapaRD\Services\translate_data_class', $rb['classes']));
                    $nb = $pdf->WordWrapCount($classesStr, 100);
                    $h = 6 * $nb;
                    $pdf->CheckPageSpace($h);
                    $pdf->Cell(60, $h, text_sanitize($rb['name']), 1, 0, 'L');
                    $pdf->Cell(30, $h, text_sanitize($rb['date']), 1, 0, 'L');
                    $pdf->MultiCell(100, 6, text_sanitize($classesStr), 1, 'L');
                }
            }

            // 4. STRATEGIC CONCLUSION
            if (isset($aiIntel['strategic_conclusion'])) {
                $pdf->renderStrategicConclusion($aiIntel['strategic_conclusion']);
            }

            // 5. GLOSSARY
            if ($aiIntel && isset($aiIntel['dynamic_glossary']) && !empty($aiIntel['dynamic_glossary'])) {
                $pdf->CheckPageSpace(60);
                $pdf->SectionTitle("3. Glosario de Términos (Explicado Simple)");
                foreach ($aiIntel['dynamic_glossary'] as $term => $def) {
                    $pdf->CheckPageSpace(20);
                    $pdf->SetFont('Helvetica', 'B', 9);
                    $pdf->SetTextColor(138, 159, 202);
                    $pdf->Cell(0, 6, text_sanitize($term), 0, 1);
                    $pdf->SetFont('Helvetica', '', 9);
                    $pdf->SetTextColor(71, 85, 105);
                    $pdf->MultiCell(190, 5, text_sanitize($def));
                    $pdf->Ln(3);
                }
            }

            // FINAL OUTPUT
            $outputPath = __DIR__ . "/reports/mapard_report_$jobId.pdf";
            if (!is_dir(__DIR__ . '/reports')) {
                mkdir(__DIR__ . '/reports', 0777, true);
            }
            $pdf->Output('F', $outputPath);
            // COMPLETE JOB
            addLog($logs, "Report generated successfully.", "success");
            $pdo->prepare("UPDATE scans SET status='COMPLETED', result_path=?, logs=?, findings=? WHERE job_id=?")
                ->execute([
                    "api/reports/mapard_report_$jobId.pdf",
                    json_encode($logs),
                    json_encode($findings),
                    $jobId
                ]);
            // Final Response for this request
            echo json_encode([
                "status" => "COMPLETED",
                "result_url" => "api/reports/mapard_report_$jobId.pdf",
                "logs" => $logs
            ]);
        } catch (Exception $e) {
            addLog($logs, "CRITICAL FAILURE: " . $e->getMessage(), "error");
            $pdo->prepare("UPDATE scans SET status='FAILED', logs=? WHERE job_id=?")->execute([json_encode($logs), $jobId]);
            echo json_encode(["error" => $e->getMessage(), "logs" => $logs]);
        }
    }
}
