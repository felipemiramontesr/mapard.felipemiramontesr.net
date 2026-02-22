<?php

namespace MapaRD\Services;

use PDO;
use Exception;
use MapaRD\Services\GeminiService;
use MapaRD\Services\ReportService;
use MapaRD\Services\SecurityUtils;

class ScanService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Executes a full OSINT scan for a given Job ID.
     */
    public function runScan($jobId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM scans WHERE job_id = ?");
        $stmt->execute([$jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            throw new Exception("Job $jobId not found.");
        }

        $logs = json_decode($job['logs'] ?? '[]', true) ?: [];
        $findings = [];
        $domain = $job['domain'];
        $email = $job['email'];
        $userId = $job['user_id'];

        // Phase 23: Fetch Baseline for Comparison
        $baselineFindings = $this->fetchBaselineFindings($email, $jobId);
        $isFirstScan = empty($baselineFindings);
        $newFindingsCount = 0;

        try {
            // Helper to add log
            $addLog = function (&$logs, $msg, $type = 'info') {
                foreach ($logs as $l) {
                    if ($l['message'] === $msg) {
                        return;
                    }
                }
                $logs[] = ["message" => $msg, "type" => $type, "timestamp" => date('c')];
            };

            // Step 1: DNS Recon
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
                $addLog($logs, "Resolving DNS records for $domain...", "info");
                $dns = @dns_get_record($domain, DNS_A + DNS_MX);
                if ($dns) {
                    $count = count($dns);
                    $addLog($logs, "Found $count DNS records.", "success");
                    foreach ($dns as $r) {
                        if (isset($r['ip'])) {
                            $findings[] = "A Record: " . $r['ip'];
                        }
                        if (isset($r['target'])) {
                            $findings[] = "MX Record: " . $r['target'];
                        }
                    }
                } else {
                    $addLog($logs, "No DNS records found for $domain.", "warning");
                }
            }

            // Step 2: HIBP Breach Check
            $addLog($logs, "Querying Intelligence Databases for $email...", "info");
            $hibpUrl = "https://haveibeenpwned.com/api/v3/breachedaccount/" . urlencode($email);
            $ch = curl_init($hibpUrl . "?truncateResponse=false");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "hibp-api-key: " . (defined('HIBP_API_KEY') ? HIBP_API_KEY : ''),
                "user-agent: MAPARD-OSINT-AGENT"
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $breachData = [];
            if ($httpCode === 200) {
                $breaches = json_decode($response, true);
                $count = count($breaches);
                $addLog($logs, "CRITICAL: Found $count compromised credentials.", "error");
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
                $addLog($logs, "No public breaches found for this email.", "success");
                $findings[] = "Breach Analysis: Clean.";
            } else {
                $addLog($logs, "API Error ($httpCode). Partial scan completed.", "warning");
            }

            // Step 3: AI Intelligence
            $aiIntel = null;
            $riskLevel = "LOW";
            if (!empty($breachData)) {
                $addLog($logs, "Invoking CORTEX Neural Engine...", "info");
                $gemini = new GeminiService();
                $aiIntel = $gemini->analyzeBreach($breachData);
                if ($aiIntel) {
                    $riskLevel = strtoupper($aiIntel['threat_level'] ?? 'HIGH');
                    $addLog($logs, "Threat Analysis Complete. Level: $riskLevel", "success");
                }
            }

            // Phase 26: Merging tactical state from baseline BEFORE PDF generation
            $richFindings = $aiIntel['detailed_analysis'] ?? [];
            foreach ($richFindings as &$finding) {
                // Initialize default state
                $finding['isNeutralized'] = false;
                $finding['steps'] = array_map(function ($s) {
                    return ['text' => $s, 'completed' => false];
                }, $finding['specific_remediation'] ?? []);

                // Try to migrate state from baseline
                if (!empty($baselineFindings)) {
                    foreach ($baselineFindings as $bf) {
                        if (
                            is_array($bf)
                            && isset($bf['source_name'])
                            && $bf['source_name'] === $finding['source_name']
                        ) {
                            // Defensive State Inheritance (Anti Ghost-Data)
                            $allStepsComplete = true;
                            $matchedCount = 0;
                            $totalSteps = count($finding['steps']);

                            foreach ($finding['steps'] as &$step) {
                                $stepIsDone = false;
                                foreach ($bf['steps'] ?? [] as $bStep) {
                                    if (
                                        isset($bStep['text'], $bStep['completed'])
                                        && $bStep['text'] === $step['text']
                                    ) {
                                        $stepIsDone = $bStep['completed'];
                                        $matchedCount++;
                                    }
                                }
                                $step['completed'] = $stepIsDone;
                                if (!$stepIsDone) {
                                    $allStepsComplete = false;
                                }
                            }

                            // Strictly recalculate Neutralized state based on the current steps,
                            // ignoring the old bf['isNeutralized'] blind flag which might be corrupted.
                            if ($totalSteps > 0 && $matchedCount === $totalSteps && $allStepsComplete) {
                                $finding['isNeutralized'] = true;
                            } else {
                                $finding['isNeutralized'] = false;
                            }
                        }
                    }
                }
            }

            // Step 4: PDF Report
            $addLog($logs, "Generating Intelligence Report...", "info");
            $pdf = new ReportService();
            $pdf->AliasNbPages();
            $pdf->AddPage();

            // Set Risk Color
            $riskColor = $this->getRiskColor($riskLevel);

            // Render Report Sections
            $this->renderPdfReport(
                $pdf,
                $email,
                $riskLevel,
                $riskColor,
                $aiIntel,
                $breachData,
                $isFirstScan,
                $newFindingsCount,
                $baselineFindings,
                $richFindings // Pass the enriched findings here
            );

            $outputPath = __DIR__ . "/../reports/mapard_report_$jobId.pdf";
            if (!is_dir(__DIR__ . '/../reports')) {
                mkdir(__DIR__ . '/../reports', 0777, true);
            }
            $pdf->Output('F', $outputPath);

            // Save Progress
            $encryptedFindings = SecurityUtils::encrypt(json_encode($richFindings));
            $encryptedLogs = SecurityUtils::encrypt(json_encode($logs));

            $scanUpdateSql = "UPDATE scans SET status='COMPLETED', result_path=?, logs=?, findings=?, is_encrypted=1 ";
            $scanUpdateSql .= "WHERE job_id=?";
            $this->pdo->prepare($scanUpdateSql)
                ->execute([
                    "api/reports/mapard_report_$jobId.pdf",
                    $encryptedLogs,
                    $encryptedFindings,
                    $jobId
                ]);

            // Phase 28: Analysis Snapshot & Checksum
            $findingsJson = json_encode($richFindings);
            $checksum = hash('sha256', $findingsJson);

            // Store Snapshot
            $snapshotSql = "INSERT INTO analysis_snapshots ";
            $snapshotSql .= "(user_id, job_id, checksum, raw_data_json) VALUES (?, ?, ?, ?)";
            $this->pdo->prepare($snapshotSql)->execute([
                $userId,
                $jobId,
                $checksum,
                $encryptedFindings // Use the same encrypted findings for consistency
            ]);

            // [NEW] Update FSM State: First analysis complete
            // Run UPSERT logic logic (SQLite) to handle missing config rows for old target profiles.
            $configSql = "INSERT INTO user_security_config (user_id, is_first_analysis_complete, updated_at) 
                          VALUES (?, 1, CURRENT_TIMESTAMP) 
                          ON CONFLICT(user_id) DO UPDATE SET 
                          is_first_analysis_complete=1, updated_at=CURRENT_TIMESTAMP";
            $this->pdo->prepare($configSql)->execute([$userId]);

            return [
                "status" => "COMPLETED",
                "result_url" => "api/reports/mapard_report_$jobId.pdf",
                "findings" => $richFindings,
                "delta_new" => $newFindingsCount ?? 0,
                "is_baseline" => $isFirstScan,
                "checksum" => $checksum
            ];
        } catch (Exception $e) {
            $addLog($logs, "CRITICAL FAILURE: " . $e->getMessage(), "error");
            $encryptedLogs = SecurityUtils::encrypt(json_encode($logs));
            $this->pdo->prepare("UPDATE scans SET status='FAILED', logs=?, is_encrypted=1 WHERE job_id=?")
                ->execute([$encryptedLogs, $jobId]);
            throw $e;
        }
    }

    private function getRiskColor($level)
    {
        if ($level === 'CRITICAL' || $level === 'CRÍTICO') {
            return [255, 0, 80];
        }
        if ($level === 'HIGH' || $level === 'ALTO') {
            return [255, 130, 0];
        }
        if ($level === 'MEDIUM' || $level === 'MEDIO') {
            return [255, 200, 0];
        }
        return [0, 243, 255];
    }

    private function renderPdfReport(
        $pdf,
        $email,
        $riskLevel,
        $riskColor,
        $aiIntel,
        $breachData,
        $isBaseline,
        $deltaNew,
        $baselineFindings,
        $richFindings = [] // Added Phase 26
    ) {
        $pdf->header($isBaseline);
        $pdf->SetY(40);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(107, 116, 144);
        $pdf->Cell(35, 6, text_sanitize('Objetivo:'), 0, 0);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(26, 31, 58);
        $pdf->Cell(0, 6, $email, 0, 1);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(107, 116, 144);
        $pdf->Cell(35, 6, text_sanitize('Nivel de Riesgo:'), 0, 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor($riskColor[0], $riskColor[1], $riskColor[2]);
        $pdf->Cell(0, 6, text_sanitize($riskLevel), 0, 1);
        $pdf->Ln(5);

        // Phase 24: Trend Analysis
        $pdf->renderTrendAnalysis($deltaNew, $isBaseline);
        $pdf->Ln(5);

        if ($aiIntel && isset($aiIntel['executive_summary'])) {
            $pdf->RenderExecutiveSummary($aiIntel['executive_summary']);
        }

        if ($aiIntel && !empty($aiIntel['detailed_analysis'])) {
            $pdf->SectionTitle("1. Análisis Detallado");
            foreach ($aiIntel['detailed_analysis'] as $analysis) {
                // Find matching breach data
                $original = null;
                foreach ($breachData as $b) {
                    if (stripos($b['name'], $analysis['source_name'] ?? '') !== false) {
                        $original = $b;
                        break;
                    }
                }
                if ($original) {
                    // Phase 24: Tag as new if not in baseline
                    $isNew = false;
                    if (!$isBaseline) {
                        $matchFound = false;
                        foreach ($baselineFindings as $bf) {
                            $bfName = is_array($bf) ? ($bf['source_name'] ?? '') : $bf;
                            if (stripos($bfName, $original['name']) !== false) {
                                $matchFound = true;
                                break;
                            }
                        }
                        $isNew = !$matchFound;
                        if ($isNew) {
                            $newFindingsCount++;
                        }
                    }

                    // Phase 26: Determine if this specific card is neutralized
                    $isNeutralized = false;
                    foreach ($richFindings as $rf) {
                        if ($rf['source_name'] === ($analysis['source_name'] ?? '')) {
                            $isNeutralized = $rf['isNeutralized'] ?? false;
                            break;
                        }
                    }

                    $pdf->RenderIntelCard($original, $analysis, $riskColor, $isNew, $isNeutralized);
                    $pdf->Ln(5);
                }
            }
        }

        if (isset($aiIntel['strategic_conclusion'])) {
            $pdf->renderStrategicConclusion($aiIntel['strategic_conclusion']);
        }
    }

    /**
     * Automated Engine: Runs daily scans for all verified users.
     */
    public function runAutomatedScans()
    {
        $stmt = $this->pdo->query("SELECT * FROM users WHERE is_verified = 1");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($users as $user) {
            // Check if scan already done today
            $scanCheckSql = "SELECT COUNT(*) FROM scans WHERE user_id = ? ";
            $scanCheckSql .= "AND created_at > datetime('now', '-24 hours')";
            $check = $this->pdo->prepare($scanCheckSql);
            $check->execute([$user['id']]);
            if ($check->fetchColumn() > 0) {
                continue;
            }

            // Trigger New Scan
            $jobId = uniqid('auto_', true);
            $email = $user['email_target'];
            $domain = explode('@', $email)[1] ?? '';

            $insertSql = "INSERT INTO scans (job_id, user_id, email, domain, status) VALUES (?, ?, ?, ?, 'PENDING')";
            $this->pdo->prepare($insertSql)
                ->execute([$jobId, $user['id'], $email, $domain]);

            try {
                $this->runScan($jobId);
            } catch (Exception $e) {
                // Log failed auto-scan but continue with others
                error_log("Auto-scan failed for user " . $user['id'] . ": " . $e->getMessage());
            }
        }
    }

    private function fetchBaselineFindings($email, $currentJobId)
    {
        $baselineSql = "SELECT findings, is_encrypted FROM scans WHERE email = ? AND status = 'COMPLETED' ";
        $baselineSql .= "AND job_id != ? ORDER BY created_at ASC LIMIT 1";
        $stmt = $this->pdo->prepare($baselineSql);
        $stmt->execute([$email, $currentJobId]);
        $baseline = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($baseline) {
            $data = $baseline['is_encrypted'] ? SecurityUtils::decrypt($baseline['findings']) : $baseline['findings'];
            return json_decode($data, true) ?: [];
        }
        return [];
    }
}
