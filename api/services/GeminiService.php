<?php

namespace MapaRD\Services;

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
            $systemPrompt = "Eres un Analista Forense Digital experto en Ciberseguridad. Tu tarea es analizar brechas de seguridad y proporcionar un informe técnico detallado. IMPORTANTE: TODA LA SALIDA DEBE ESTAR EN ESPAÑOL (ES_MX). NO DES RESPUESTAS EN INGLÉS.";
            $userPrompt = "Analiza este lote de brechas ($batchNum de $totalBatches):\n" . json_encode($batch) . "\n\n" .
                "Genera UNICAMENTE un JSON válido con esta estructura (sin markdown):\n" .
                "{\n" .
                "  \"detailed_analysis\": [\n" .
                "    { \n" .
                "      \"source_name\": \"Nombre del servicio\", \n" .
                "      \"incident_story\": \"Explicación detallada del incidente en Español.\", \n" .
                "      \"risk_explanation\": \"Por qué es peligroso para el usuario en Español.\", \n" .
                "      \"specific_remediation\": [\"Paso 1\", \"Paso 2\"] \n" .
                "    }\n" .
                "  ]\n" .
                "}\n\n" .
                "REGLAS:\n" .
                "1. Traduce todo el contenido al Español.\n" .
                "2. 'source_name' debe mantenerse original.\n" .
                "3. Devuelve EXACTAMENTE $count objetos.";

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
            return $b['name'] . " (" . implode(",", $b['classes']) . ")";
        }, $data);

        $sysSum = "Eres un CISO (Chief Information Security Officer). Tu objetivo es generar un reporte de ALTO NIVEL. \n" .
            "REGLA DE ORO: El 'executive_summary' y la 'strategic_conclusion' DEBEN tener entre 80 y 100 palabras cada uno. Ni más, ni menos. \n" .
            "Idioma: Español Neutro o de México.";

        $userSum = "Incidentes: " . json_encode($metaData) . "\n\n" .
            "Genera el JSON de respuesta:\n" .
            "{ \n" .
            "  \"threat_level\": \"LOW|MEDIUM|HIGH|CRITICAL\", \n" .
            "  \"executive_summary\": \"...texto del resumen (80-100 palabras)...\", \n" .
            "  \"strategic_conclusion\": \"...texto de la conclusión (80-100 palabras)...\", \n" .
            "  \"dynamic_glossary\": {\"Termino\": \"Definición\"} \n" .
            "}";

        $summary = $this->callGemini($url, $sysSum, $userSum);

        return [
            'threat_level' => $summary['threat_level'] ?? 'HIGH',
            'executive_summary' => $summary['executive_summary'] ?? 'Se detectaron múltiples compromisos de seguridad.',
            'detailed_analysis' => $finalAnalysis,
            'dynamic_glossary' => $summary['dynamic_glossary'] ?? [],
            'strategic_conclusion' => $summary['strategic_conclusion'] ?? 'Se recomienda rotación inmediata de credenciales.'
        ];
    }

    protected function callGemini($url, $sys, $user)
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
