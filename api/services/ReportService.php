<?php

namespace MapaRD\Services;

use FPDF;

// Helper function moved here as a static method or private helper
function text_sanitize($str)
{
    $str = html_entity_decode($str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return iconv('UTF-8', 'windows-1252//TRANSLIT', $str);
}

function translate_data_class($class)
{
    $map = [
        'Email addresses' => 'Correos electrónicos',
        'Passwords' => 'Contraseñas',
        'Usernames' => 'Nombres de usuario', /* ... (rest of the map) ... */
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

class ReportService extends FPDF
{
    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->SetMargins(10, 40, 10);
        $this->SetAutoPageBreak(true, 20);
    }

    public function header($isBaseline = true)
    {
        $this->SetFillColor(10, 14, 39);
        $this->Rect(0, 0, 210, 35, 'F');

        $this->SetDrawColor(138, 159, 202);
        $this->SetLineWidth(0.5);
        $this->Line(0, 35, 210, 35);

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetXY(12, 10);
        $title = $isBaseline ? 'MAPARD - DOSSIER DE INTELIGENCIA' : 'MAPARD - HISTORIAL ACTIVO';
        $this->Cell(0, 10, $title, 0, 1, 'L');

        $this->SetFont('Helvetica', '', 8);
        $this->SetXY(12, 18);
        $this->SetTextColor(138, 159, 202);
        $this->Cell(0, 5, utf8_decode('CIBERINTELIGENCIA & ANÁLISIS FORENSE'), 0, 1, 'L');
    }

    public function footer()
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

    public function checkPageSpace($h)
    {
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
            $this->SetY(45); // Reset below header
        }
    }

    public function wordWrapCount($text, $width)
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $nb = 0;
        $words = explode(' ', $text);
        $currLine = '';

        foreach ($words as $word) {
            $checkLine = $currLine ? $currLine . ' ' . $word : $word;
            if ($this->GetStringWidth($checkLine) < $width) {
                $currLine = $checkLine;
            } else {
                $nb++;
                $currLine = $word;
            }
        }
        if ($currLine) {
            $nb++;
        }

        return $nb;
    }

    public function sectionTitle($title)
    {
        $this->checkPageSpace(25);
        if ($this->GetY() < 45) {
            $this->SetY(45);
        }

        $this->Ln(10);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(0, 8, text_sanitize($title), 0, 1, 'L');

        $this->SetDrawColor(138, 159, 202);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 190, $this->GetY());
        $this->Ln(6);
    }

    public function renderIntelCard($breach, $analysis, $riskColor, $isNew = false, $isNeutralized = false)
    {
        $source = $breach['name'];
        $date = $breach['date'];

        $story = isset($analysis['incident_story']) && !empty($analysis['incident_story'])
            ? $analysis['incident_story']
            : "El reporte de inteligencia para $source no pudo recuperar el contexto histórico específico." .
            " Se trató de una vulneración de datos registrada en $date.";

        $risk = isset($analysis['risk_explanation'])
            ? $analysis['risk_explanation']
            : "La exposición de sus datos en este servicio aumenta el riesgo de suplantación de identidad.";

        $rawActions = isset($analysis['specific_remediation'])
            ? $analysis['specific_remediation']
            : ["Cambie su contraseña inmediatamente."];
        if (is_string($rawActions)) {
            $rawActions = [$rawActions];
        }

        $classes = "Expuesto: " . implode(", ", array_map('MapaRD\Services\translate_data_class', $breach['classes']));

        // COMPACT MODE SETTINGS
        $lineH = 4; // Reduced from 4.5

        // Context Setup
        $this->SetFont('Helvetica', '', 9);
        $storyLines = $this->wordWrapCount($story, 180);
        $riskLines = $this->wordWrapCount($risk, 180);

        $this->SetFont('Helvetica', '', 8);
        $classLines = $this->wordWrapCount($classes, 180);

        $actionsHeight = 0;
        $this->SetFont('Helvetica', '', 9);
        foreach ($rawActions as $act) {
            $nb = $this->wordWrapCount($act, 165);
            $actionsHeight += ($nb * $lineH) + 1; // Reduced gap
        }
        $actionsHeight += 6; // Header padding
        $actionsHeight += 4; // Add bottom padding

        // Calculate total card height
        $cardHeight = 15 + ($classLines * 4) + 2 + 5 +
            ($storyLines * $lineH) + 2 + 5 +
            ($riskLines * $lineH) + 3 + $actionsHeight + 5;

        $this->checkPageSpace($cardHeight);
        if ($this->GetY() < 45) {
            $this->SetY(45);
        }

        $baseY = $this->GetY();

        // Card Background
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(200, 209, 224);
        $this->SetLineWidth(0.2);
        $this->Rect(10, $baseY, 190, $cardHeight, 'DF');

        // Risk Border Color (Gray if neutralized, colored otherwise)
        if ($isNeutralized) {
            $this->SetFillColor(40, 120, 80); // Success Green for border
        } else {
            $this->SetFillColor($riskColor[0], $riskColor[1], $riskColor[2]);
        }
        $this->Rect(10, $baseY, 2, $cardHeight, 'F');

        // Header
        $this->SetXY(16, $baseY + 4);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(110, 5, text_sanitize($source), 0, 0);

        // Badges
        $this->SetFont('Helvetica', 'B', 7);
        if ($isNeutralized) {
            $this->SetFillColor(40, 120, 80);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(25, 4, 'NEUTRALIZADO', 0, 0, 'C', true);
            $this->SetX($this->GetX() + 2);
        }

        if ($isNew) {
            $this->SetFillColor(255, 0, 80);
            $this->SetTextColor(255, 255, 255);
            $this->Cell(15, 4, 'NUEVA', 0, 0, 'C', true);
        }

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(107, 116, 144);
        $this->SetX(145);
        $this->Cell(55, 5, text_sanitize("Incidente: $date"), 0, 1, 'R');

        // Classes
        $this->SetX(16);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(138, 159, 202);
        $this->MultiCell(180, 4, text_sanitize($classes));

        $this->Ln(1);
        $this->SetDrawColor(240, 240, 240);
        $this->Line(16, $this->GetY(), 195, $this->GetY());
        $this->Ln(2);

        // Story
        $this->SetX(16);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(0, 5, utf8_decode("CONTEXTO:"), 0, 1);

        $this->SetX(16);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(60, 70, 90);
        $this->MultiCell(180, $lineH, text_sanitize($story));
        $this->Ln(2);

        // Risk
        $this->SetX(16);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(0, 5, utf8_decode("IMPACTO:"), 0, 1);

        $this->SetX(16);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(60, 70, 90);

        if ($isNeutralized) {
            $risk = "[RIESGO MITIGADO]: " . $risk;
            $this->SetTextColor(40, 120, 80);
        }
        $this->MultiCell(180, $lineH, text_sanitize($risk));
        $this->Ln(2);

        // Actions
        $remY = $this->GetY();
        if ($isNeutralized) {
            $this->SetFillColor(240, 250, 245);
            $this->SetDrawColor(180, 220, 200);
        } else {
            $this->SetFillColor(245, 250, 247);
            $this->SetDrawColor(200, 220, 210);
        }
        $this->Rect(15, $remY, 180, $actionsHeight, 'DF');

        $this->SetXY(20, $remY + 2);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(40, 120, 80);
        $title = $isNeutralized ? "ACCIONES COMPLETADAS:" : "PLAN DE ACCIÓN:";
        $this->Cell(0, 5, utf8_decode($title), 0, 1);

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(50, 60, 70);

        foreach ($rawActions as $i => $act) {
            $this->SetX(20);
            if ($isNeutralized) {
                $this->SetTextColor(40, 120, 80);
                $this->Cell(5, $lineH, "[X]", 0, 0);
            } else {
                $this->SetTextColor(50, 60, 70);
                $this->Cell(5, $lineH, ($i + 1) . ".", 0, 0);
            }
            $this->MultiCell(165, $lineH, text_sanitize($act));
            $this->Ln(1);
        }

        $this->SetY($baseY + $cardHeight + 4);
    }

    public function renderExecutiveSummary($summary)
    {
        // 1. Setup Context for Accurate Measurement
        $this->SetFont('Helvetica', '', 9);
        $lineH = 5;
        $nb = $this->wordWrapCount($summary, 180);

        // Calculation: Top(4) + Header(6) + Gap(2) + Text($nb * 5) + Bottom(4) = 16 + Text
        $height = 16 + ($nb * $lineH);

        $this->checkPageSpace($height);

        // Background
        $this->SetFillColor(249, 250, 252);
        $this->SetDrawColor(200, 209, 224);
        $this->Rect(10, $this->GetY(), 190, $height, 'DF');

        // Header
        $this->SetXY(15, $this->GetY() + 4); // Top padding 4mm
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(0, 6, utf8_decode("RESUMEN DE SEGURIDAD"), 0, 1);

        // Body
        $this->SetX(15);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(71, 85, 105);
        $this->MultiCell(180, $lineH, text_sanitize($summary));

        $this->SetY($this->GetY() + 4); // Bottom padding 4mm
        $this->Ln(5);
    }

    public function renderStrategicConclusion($conclusion)
    {
        // 1. Setup Context for Accurate Measurement
        $this->SetFont('Helvetica', '', 10);
        $lineH = 6;
        $nb = $this->wordWrapCount($conclusion, 180);

        // Calculation: Top(4) + Header(6) + Gap(2) + Text($nb * 6) + Bottom(4) = 16 + Text
        // Adding +2mm safety buffer for font rendering differences -> 18
        $height = 18 + ($nb * $lineH);

        $this->Ln(5); // Top margin
        $this->checkPageSpace($height);

        // Background
        $this->SetFillColor(255, 235, 235);
        $this->SetDrawColor(255, 0, 0);
        $this->Rect(10, $this->GetY(), 190, $height, 'DF');

        // Header
        $this->SetXY(15, $this->GetY() + 4);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(200, 0, 0);
        $this->Cell(0, 6, utf8_decode("CONCLUSIÓN ESTRATÉGICA"), 0, 1);

        // Body
        $this->SetX(15);
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(50, 0, 0);
        $this->MultiCell(180, $lineH, text_sanitize($conclusion));

        $this->SetY($this->GetY() + 4);
        $this->Ln(5);
    }

    public function renderTrendAnalysis($deltaNew, $isBaseline)
    {
        $this->checkPageSpace(40);
        $this->Ln(5);

        $baseY = $this->GetY();
        $this->SetFillColor(240, 245, 255);
        $this->SetDrawColor(138, 159, 202);
        $this->Rect(10, $baseY, 190, 25, 'DF');

        $this->SetXY(15, $baseY + 5);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(0, 5, utf8_decode("ANÁLISIS DE TENDENCIA"), 0, 1);

        $this->SetX(15);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(71, 85, 105);
        if ($isBaseline) {
            $msg = "Análisis inicial realizado. Este dossier servirá como Baseline para futuras detecciones tácticas.";
        } else {
            if ($deltaNew > 0) {
                $msg = "ALERTA: Se han detectado $deltaNew nuevas brechas de seguridad desde el análisis inicial.";
                $this->SetTextColor(200, 0, 0);
            } else {
                $msg = "Estado Neutralizado: No se han detectado nuevas filtraciones de datos ";
                $msg .= "en este ciclo de monitoreo.";
                $this->SetTextColor(40, 120, 80);
            }
        }
        $this->MultiCell(180, 5, text_sanitize($msg));
        $this->Ln(10);
    }
}
