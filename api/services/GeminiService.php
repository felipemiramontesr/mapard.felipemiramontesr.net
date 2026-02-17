<?php

namespace MapaRD\Services;

// require_once moved to constructor

class GeminiService
{
    private $apiKey;
    private $model;
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct()
    {
        if (file_exists(__DIR__ . '/../config.php')) {
            require_once __DIR__ . '/../config.php';
        }
        $this->apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : 'test_key';
        $this->model = defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-1.5-flash';
    }

    public function analyzeBreach($data)
    {
        $this->model = 'gemini-2.0-flash';

        if (empty($data)) {
            return [
                'threat_level' => 'LOW',
                'executive_summary' => 'No se detectaron vulneraciones de datos en las fuentes consultadas.',
                'detailed_analysis' => [],
                'dynamic_glossary' => [],
                'strategic_conclusion' => 'Su huella digital parece segura por el momento.'
            ];
        }

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
            $systemPrompt = "Eres un Asesor de Seguridad Personal. " .
                "Tu cliente es un INDIVIDUO (B2C), NO una empresa. \n" .
                "OBJETIVO: Explicar riesgos y soluciones a una persona común.\n" .
                "REGLAS DE TONO: \n" .
                "1. Usa 'Tú', 'Tus datos', 'Tu cuenta'. \n" .
                "2. PROHIBIDO hablar de: 'empleados', 'capacitación', 'reputación corporativa', " .
                "'sistemas internos'. \n" .
                "3. Idioma: Español (ES_MX).";
            $userPrompt = "Analiza este lote de brechas ($batchNum de $totalBatches) para una persona:\n" .
                json_encode($batch) . "\n\n" .
                "Genera UNICAMENTE un JSON válido con esta estructura (sin markdown). \n" .
                "REGLAS CRÍTICAS DE CONTENIDO:\n" .
                "1. incident_story: DEBE contar la historia del incidente. " .
                "NO uses frases genéricas como 'Ocurrió una brecha'. " .
                "Di: 'En Mayo de 2023, atacantes accedieron a los servidores de X...'. " .
                "Si no hay datos, di: 'No se encontraron detalles públicos específicos, " .
                "pero sus datos aparecieron en una lista de tráfico ilegal.'\n" .
                "2. specific_remediation: DEVUELVE UNA LISTA DE 3 ACCIONES CONCRETAS. " .
                "Ejemplo: ['Cambiar contraseña en Netflix', 'Activar 2FA en ajustes de cuenta', " .
                "'Revocar permisos de apps de terceros']. " .
                "NO des consejos vagos.\n\n" .
                "{\n" .
                "  \"detailed_analysis\": [\n" .
                "    { \n" .
                "      \"source_name\": \"Nombre del servicio\", \n" .
                "      \"incident_story\": \"Historia específica del incidente (Min 30 palabras).\", \n" .
                "      \"risk_explanation\": \"Por qué es peligroso para MI como usuario (en Español).\", \n" .
                "      \"specific_remediation\": [\"Acción 1 (Verbo Imperativo)\", \"Acción 2\", \"Acción 3\"] \n" .
                "    }\n" .
                "  ]\n" .
                "}\n\n" .
                "REGLAS TÉCNICAS:\n" .
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
                        'incident_story' => "Error de análisis IA en lote $batchNum. " .
                            "Datos crudos: " . $b['description'],
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

        $sysSum = "Eres un Asesor de Ciberseguridad Personal Certificado. " .
            "Tu cliente es una persona individual que ha sido vulnerada.\n" .
            "OBJETIVO: Informar con seriedad y empatía, sin usar jerga corporativa.\n" .
            "TONO: Profesional, Claro, Alerta y Directo. (Estilo soporte técnico premium).\n" .
            "PROHIBIDO (Lenguaje Corporativo): 'Organización', 'Mitigación estratégica', " .
            "'Cadena de suministro', 'Interdepartamental', 'Activos', 'Auditoría'.\n" .
            "OBLIGATORIO (Lenguaje Personal): 'Sus datos', 'Su identidad', 'Sus cuentas', " .
            "'Riesgo de fraude', 'Hackers'.\n" .
            "REGLA DE LONGITUD: Resumen y Conclusión deben tener entre 70 y 90 palabras " .
            "para llenar el espacio en el PDF.\n" .
            "EJEMPLO: 'Hemos detectado que sus credenciales están expuestas. " .
            "Es vital que cambie sus contraseñas ahora mismo para proteger sus cuentas bancarias.'";

        $userSum = "Incidentes detectados: " . json_encode($metaData) . "\n\n" .
            "Genera el JSON de respuesta (Para un USUARIO INDIVIDUAL):\n" .
            "{ \n" .
            "  \"threat_level\": \"LOW|MEDIUM|HIGH|CRITICAL\", \n" .
            "  \"executive_summary\": \"...Resumen profesional (70-90 palabras). " .
            "Enfocado en el riesgo personal...\", \n" .
            "  \"strategic_conclusion\": \"...Recomendación experta (70-90 palabras). " .
            "Pasos claros y serios...\", \n" .
            "  \"dynamic_glossary\": {\"Termino\": \"Definición\"} \n" .
            "}";

        $summary = $this->callGemini($url, $sysSum, $userSum);

        return [
            'threat_level' => $summary['threat_level'] ?? 'HIGH',
            'executive_summary' => $summary['executive_summary'] ?? 'Se detectaron múltiples compromisos de seguridad.',
            'detailed_analysis' => $finalAnalysis,
            'dynamic_glossary' => $summary['dynamic_glossary'] ?? [],
            'strategic_conclusion' => $summary['strategic_conclusion']
                ?? 'Se recomienda rotación inmediata de credenciales.'
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
            'executive_summary' => 'El sistema de inteligencia artificial no pudo procesar los detalles. ' .
                'Razón Técnica: ' . $debugError,
            'detailed_analysis' => [],
            'dynamic_glossary' => ['Error' => $debugError]
        ];

        foreach ($breaches as $b) {
            $analysis['detailed_analysis'][] = [
                'source_name' => $b['name'],
                'incident_story' => " FALLO DE CONEXIÓN IA. \n\nDETALLE TÉCNICO: $debugError \n\n" .
                    "Por favor reporte este código de error al administrador.",
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
