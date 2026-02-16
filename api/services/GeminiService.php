<?php
require_once __DIR__ . '/../config.php';

class GeminiService
{
    private $apiKey;
    private $model;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        $this->apiKey = GEMINI_API_KEY;
        $this->model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-1.5-flash';
    }

    public function analyzeBreach($data)
    {
        $this->model = 'gemini-2.0-flash';
        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        // 1. SPLIT INTO BATCHES (Chunk size 5)
        $batches = array_chunk($data, 5);
        $finalAnalysis = [];

        // 2. PROCESS BACHTCHES
        foreach ($batches as $index => $batch) {
            $count = count($batch);
            $batchNum = $index + 1;
            $totalBatches = count($batches);

            // Analysis Prompt
            $systemPrompt = "Eres un Analista Forense Digital. Tu única tarea es devolver un JSON con el análisis detallado de las brechas proporcionadas.";
            $userPrompt = "Analiza este lote de brechas ($batchNum de $totalBatches):\n" . json_encode($batch) . "\n\n" .
                "DEBES devolver UNICAMENTE un JSON válido con esta estructura (sin markdown):\n" .
                "{\n" .
                "  \"detailed_analysis\": [\n" .
                "    { \"source_name\": \"...\", \"incident_story\": \"...\", \"risk_explanation\": \"...\", \"specific_remediation\": [...] }\n" .
                "  ]\n" .
                "}\n\n" .
                "IMPORTANTE: Debes devolver EXACTAMENTE $count objetos en 'detailed_analysis'. Uno por cada brecha de entrada.";

            $response = $this->callGemini($url, $systemPrompt, $userPrompt);

            if ($response && isset($response['detailed_analysis']) && is_array($response['detailed_analysis'])) {
                foreach ($response['detailed_analysis'] as $item) {
                    $finalAnalysis[] = $item;
                }
            } else {
                // Fallback for this batch if it fails
                foreach ($batch as $b) {
                    $finalAnalysis[] = [
                        'source_name' => $b['name'],
                        'incident_story' => "Error de análisis IA en lote $batchNum. Datos crudos: " . $b['description'],
                        'risk_explanation' => "Clases expuestas: " . implode(", ", $b['classes']),
                        'specific_remediation' => ["Cambiar contraseñas", "Verificar 2FA"]
                    ];
                }
            }
        }

        // 3. GENERATE SUMMARY (Single call with metadata)
        $metaData = array_map(function ($b) {
            return $b['name'] . " (" . implode(",", $b['classes']) . ")"; }, $data);

        $sysSum = "Eres un CISO. Genera el resumen ejecutivo para este reporte de inteligencia.";
        $userSum = "Lista de incidentes detectados: " . json_encode($metaData) . "\n\n" .
            "Genera un JSON con:\n" .
            "{ \"threat_level\": \"LOW|MEDIUM|HIGH|CRITICAL\", \"executive_summary\": \"...\", \"strategic_conclusion\": \"...\", \"dynamic_glossary\": {...} }";

        $summary = $this->callGemini($url, $sysSum, $userSum);

        return [
            'threat_level' => $summary['threat_level'] ?? 'HIGH',
            'executive_summary' => $summary['executive_summary'] ?? 'Se detectaron múltiples compromisos de seguridad.',
            'detailed_analysis' => $finalAnalysis,
            'dynamic_glossary' => $summary['dynamic_glossary'] ?? [],
            'strategic_conclusion' => $summary['strategic_conclusion'] ?? 'Se recomienda rotación inmediata de credenciales.'
        ];
    }

    private function callGemini($url, $sys, $user)
    {
        $payload = [
            "contents" => [
                ["role" => "user", "parts" => [["text" => $sys . "\n\n" . $user]]]
            ],
            "generationConfig" => [
                "temperature" => 0.4,
                "responseMimeType" => "application/json"
            ]
        ];

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => 60,
                'ignore_errors' => true
            ]
        ];

        $context = stream_context_create($opts);
        $result = @file_get_contents($url, false, $context);

        if ($result) {
            $json = json_decode($result, true);
            if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                $txt = $json['candidates'][0]['content']['parts'][0]['text'];
                $clean = str_replace(['```json', '```'], '', $txt);
                return json_decode($clean, true);
            }
        }
        return null;
    }

    // Fallback Generator to ensure PDF never breaks
    private function getFallbackAnalysis($breaches, $debugError = '')
    {
        $analysis = [
            'threat_level' => 'HIGH',
            'executive_summary' => 'El sistema de inteligencia artificial no pudo procesar los detalles. Razón Técnica: ' . $debugError,
            'detailed_analysis' => [],
            'dynamic_glossary' => ['Error' => $debugError]
        ];

        foreach ($breaches as $b) {
            $analysis['detailed_analysis'][] = [
                'source_name' => $b['name'],
                'incident_story' => " FALLO DE CONEXIÓN IA. \n\nDETALLE TÉCNICO: $debugError \n\nPor favor reporte este código de error al administrador.",
                'risk_explanation' => "Riesgo no calculado debido a fallo técnico ($debugError).",
                'specific_remediation' => [
                    "Error Técnico: $debugError",
                    "Verifique logs del servidor.",
                    "Intente de nuevo más tarde."
                ]
            ];
        }
        return $analysis;
    }
}
?>