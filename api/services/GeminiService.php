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
            $systemPrompt = "Eres un Asesor de Seguridad Personal. Tu cliente es un INDIVIDUO (B2C), NO una empresa. \n" .
                "OBJETIVO: Explicar riesgos y soluciones a una persona común.\n" .
                "REGLAS DE TONO: \n" .
                "1. Usa 'Tú', 'Tus datos', 'Tu cuenta'. \n" .
                "2. PROHIBIDO hablar de: 'empleados', 'capacitación', 'reputación corporativa', 'sistemas internos'. \n" .
                "3. Idioma: Español (ES_MX).";
            $userPrompt = "Analiza este lote de brechas ($batchNum de $totalBatches) para una persona:\n" . json_encode($batch) . "\n\n" .
                "Genera UNICAMENTE un JSON válido con esta estructura (sin markdown):\n" .
                "{\n" .
                "  \"detailed_analysis\": [\n" .
                "    { \n" .
                "      \"source_name\": \"Nombre del servicio\", \n" .
                "      \"incident_story\": \"Qué ocurrió (en Español).\", \n" .
                "      \"risk_explanation\": \"Por qué es peligroso para MI como usuario (en Español).\", \n" .
                "      \"specific_remediation\": [\"Acción personal 1\", \"Acción personal 2\"] \n" .
                "    }\n" .
                "  ]\n" .
                "}\n\n" .
                "REGLAS:\n" .
                "1. Traduce todo al Español.\n" .
                "2. 'source_name' original.\n" .
                "3. EXACTAMENTE $count objetos.";

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

        $sysSum = "Eres un Amigo Experto en Ciberseguridad ayudando a tu vecino. Tu cliente es un CIUDADANO COMÚN y CORRIENTE.\n" .
            "TONO: Directo, personal, urgente, simple. Como si hablaras cara a cara.\n" .
            "PROHIBIDO (Palabras de Empresa): 'Estrategia multicapa', 'mitigación', 'activos', 'empleados', 'cadena de suministro', 'implementación', 'organización', 'auditoría'.\n" .
            "OBLIGATORIO (Palabras de Persona): 'Tú', 'Tu familia', 'Tus ahorros', 'Tus fotos', 'Ladrones', 'Hackers', 'Tus cuentas'.\n" .
            "REGLA DE LONGITUD: Resumen y Conclusión deben tener entre 70 y 90 palabras. Párrafos cortos.\n" .
            "EJEMPLO MALO: 'La organización debe implementar medidas proactivas'.\n" .
            "EJEMPLO BUENO: 'Debes cambiar tus contraseñas hoy mismo para que no entren a tu banco'.";

        $userSum = "Incidentes detectados: " . json_encode($metaData) . "\n\n" .
            "Genera el JSON de respuesta (Para una PERSONA, no una empresa):\n" .
            "{ \n" .
            "  \"threat_level\": \"LOW|MEDIUM|HIGH|CRITICAL\", \n" .
            "  \"executive_summary\": \"...Resumen personal (70-90 palabras). Habla de SUS datos, no de 'sistemas'.\", \n" .
            "  \"strategic_conclusion\": \"...Conclusión personal (70-90 palabras). Diles qué hacer HOY en su casa. nada de 'planes a futuro'.\", \n" .
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
