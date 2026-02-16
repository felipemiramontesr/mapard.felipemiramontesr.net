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

    public function header()
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

    public function renderIntelCard($breach, $analysis, $riskColor)
    {
        $source = $breach['name'];
        $date = $breach['date'];

        $story = isset($analysis['incident_story']) && !empty($analysis['incident_story'])
            ? $analysis['incident_story']
            : "El reporte de inteligencia para $source no pudo recuperar el contexto histórico específico. Se trató de una vulneración de datos registrada en $date.";

        $risk = isset($analysis['risk_explanation'])
            ? $analysis['risk_explanation']
            : "La exposición de sus datos en este servicio aumenta el riesgo de suplantación de identidad.";

        $rawActions = isset($analysis['specific_remediation']) ? $analysis['specific_remediation'] : ["Cambie su contraseña inmediatamente."];
        if (is_string($rawActions)) {
            $rawActions = [$rawActions];
        }

        $classes = "Expuesto: " . implode(", ", array_map('MapaRD\Services\translate_data_class', $breach['classes']));

        $lineH = 4.5;
        $storyLines = ceil(strlen($story) / 90);
        $riskLines = ceil(strlen($risk) / 90);

        $actionsHeight = 0;
        foreach ($rawActions as $act) {
            $actionsHeight += (ceil(strlen($act) / 85) * $lineH) + 2;
        }
        $actionsHeight += 8;

        $classLines = ceil(strlen($classes) / 90);
        $cardHeight = 45 + ($storyLines * $lineH) + ($riskLines * $lineH) + $actionsHeight + ($classLines * $lineH);

        $this->checkPageSpace($cardHeight);
        if ($this->GetY() < 45) {
            $this->SetY(45);
        }

        $baseY = $this->GetY();

        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(200, 209, 224);
        $this->SetLineWidth(0.2);
        $this->Rect(10, $baseY, 190, $cardHeight, 'DF');

        $this->SetFillColor($riskColor[0], $riskColor[1], $riskColor[2]);
        $this->Rect(10, $baseY, 2, $cardHeight, 'F');

        $this->SetXY(16, $baseY + 5);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(120, 6, text_sanitize($source), 0, 0);

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(107, 116, 144);
        $this->Cell(60, 6, text_sanitize("Incidente: $date"), 0, 1, 'R');

        $this->SetX(16);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(138, 159, 202);
        $this->MultiCell(180, $lineH, text_sanitize($classes));

        $this->Ln(2);
        $this->SetDrawColor(240, 240, 240);
        $this->Line(16, $this->GetY(), 195, $this->GetY());
        $this->Ln(4);

        $this->SetX(16);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(0, 5, utf8_decode("¿QUÉ PASÓ? (Contexto):"), 0, 1);

        $this->SetX(16);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(60, 70, 90);
        $this->MultiCell(180, $lineH, text_sanitize($story));
        $this->Ln(3);

        $this->SetX(16);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(26, 31, 58);
        $this->Cell(0, 5, utf8_decode("¿POR QUÉ ME AFECTA?:"), 0, 1);

        $this->SetX(16);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(60, 70, 90);
        $this->MultiCell(180, $lineH, text_sanitize($risk));
        $this->Ln(3);

        $remY = $this->GetY();
        $this->SetFillColor(245, 250, 247);
        $this->SetDrawColor(200, 220, 210);
        $this->Rect(15, $remY, 180, $actionsHeight, 'DF');

        $this->SetXY(20, $remY + 3);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(40, 120, 80);
        $this->Cell(0, 5, utf8_decode("PLAN DE ACCIÓN (3 PASOS):"), 0, 1);

        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(50, 60, 70);

        foreach ($rawActions as $i => $act) {
            $this->SetX(20);
            $this->Cell(5, $lineH, ($i + 1) . ".", 0, 0);
            $this->MultiCell(165, $lineH, text_sanitize($act));
            $this->Ln(1);
        }

        $this->SetY($baseY + $cardHeight + 5);
    }

    public function renderExecutiveSummary($summary)
    {
        $this->checkPageSpace(40);
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
