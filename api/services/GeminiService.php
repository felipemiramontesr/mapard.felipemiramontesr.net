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
        // HARDCODE MODEL TO ENSURE V7
        $this->model = 'gemini-1.5-pro';
        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        // Construct the Tactical Analyst Persona (V7 - High Reasoning & One-Shot)
        $systemPrompt = "Eres un Estratega de Ciberseguridad Senior.
        Tu misión es analizar una lista de brechas y generar un reporte JSON detallado para el cliente.
        
        INPUT: Lista de brechas (Array).
        
        INSTRUCCIONES CLAVE:
        1. Debes generar un objeto en 'detailed_analysis' POR CADA brecha recibida. Si recibes 3 brechas, devuelve 3 análisis.
        2. NO devuelvas arrays vacíos. Si te falta información, infiere el riesgo basado en el tipo de servicio.
        3. 'incident_story' debe ser narrativo y explicar el contexto.
        4. 'specific_remediation' debe ser un array de 3 strings distintas (Técnica, Legal, Preventiva).

        EJEMPLO DE SALIDA (JSON):
        {
            \"threat_level\": \"CRITICAL\",
            \"executive_summary\": \"Se detectaron exposiciones críticas en servicios financieros y sociales...\",
            \"detailed_analysis\": [
                {
                    \"source_name\": \"Adobe\",
                    \"incident_story\": \"En octubre de 2013, atacantes accedieron a la red de Adobe y sustrajeron datos de 153 millones de cuentas, incluyendo contraseñas cifradas y pistas.\",
                    \"risk_explanation\": \"Dado que Adobe almacena métodos de pago, el riesgo de fraude financiero y phishing dirigido es muy alto.\",
                    \"specific_remediation\": [
                        \"Cambie su contraseña en Adobe y active la autenticación en dos pasos (2FA).\",
                        \"Solicite a su banco un monitoreo de transacciones por posibles cargos no reconocidos.\",
                        \"Utilice un gestor de contraseñas para generar claves únicas en el futuro.\"
                    ]
                }
            ],
            \"dynamic_glossary\": {
                \"Encryption\": \"Proceso de codificar datos para que solo autorizados los lean.\"
            }
        }";

        $userPrompt = "Analiza estas brechas y genera el JSON completo:\n" . json_encode($data);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $userPrompt]
                    ]
                ]
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.6, // Higher temperature = More creativity/variety
                'responseMimeType' => 'application/json'
            ]
        ];

        // Use file_get_contents instead of cURL (to avoid extension dependency issues)
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
                'timeout' => 120, // Increased for 1.5 Pro Latency (17+ breaches)
                'ignore_errors' => true // To capture error responses
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        // Check for HTTP errors
        if ($response === FALSE) {
            error_log("Gemini API Error: Connection failed");
            return $this->getFallbackAnalysis($data);
        }

        // Parse HTTP status code from headers
        $httpCode = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('/^HTTP\/\d\.\d (\d+)/', $header, $matches)) {
                    $httpCode = intval($matches[1]);
                    break;
                }
            }
        }

        if ($httpCode !== 200) {
            error_log("Gemini API Error: HTTP $httpCode - Response: $response");
            return $this->getFallbackAnalysis($data); // Corrected variable
        }

        // SAVE RAW RESPONSE FOR DEBUGGING
        file_put_contents(__DIR__ . '/../gemini_debug.log', "--- REQUEST ---\n" . $userPrompt . "\n\n--- RESPONSE ---\n" . $response . "\n\n", FILE_APPEND);

        try {
            $jsonResponse = json_decode($response, true);
            $rawText = $jsonResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$rawText) {
                error_log("Gemini Error: No text in candidate.");
                return $this->getFallbackAnalysis($data); // Corrected variable
            }

            // Clean up Markdown: Handle ```json and ``` wrapping
            $cleanJson = preg_replace('/^```json\s*|\s*```$/i', '', trim($rawText));

            // Log cleaned JSON
            file_put_contents(__DIR__ . '/../gemini_cleaned.log', $cleanJson);

            $parsed = json_decode($cleanJson, true);

            // STRICT VALIDATION: Ensure we actually have the data we need
            if (!is_array($parsed) || empty($parsed['detailed_analysis'])) {
                error_log("Gemini Logic Error: JSON is valid but missing 'detailed_analysis'. content: " . substr($cleanJson, 0, 100) . "...");
                return $this->getFallbackAnalysis($data); // Corrected variable
            }

            return $parsed;

        } catch (Exception $e) {
            error_log("Gemini Critical Error: " . $e->getMessage());
            return $this->getFallbackAnalysis($data); // Corrected variable
        }
    }

    // Fallback Generator to ensure PDF never breaks
    private function getFallbackAnalysis($breaches)
    {
        $analysis = [
            'threat_level' => 'HIGH',
            'executive_summary' => 'El sistema de inteligencia artificial no pudo procesar los detalles específicos en este momento, pero se han detectado brechas confirmadas.',
            'detailed_analysis' => [],
            'dynamic_glossary' => [
                'Error de Análisis' => 'No se pudo conectar con el motor neuronal. Se muestran datos crudos.'
            ]
        ];

        foreach ($breaches as $b) {
            $analysis['detailed_analysis'][] = [
                'source_name' => $b['name'],
                'incident_story' => "No se pudo generar la historia detallada por un error de conexión con la IA. Sin embargo, la brecha es real y sus datos están expuestos.",
                'risk_explanation' => "Sus credenciales en este servicio son públicas. El riesgo de acceso no autorizado es inminente.",
                'specific_remediation' => [
                    "Cambie su contraseña en " . $b['name'] . " inmediatamente.",
                    "Active la verificación en dos pasos (2FA).",
                    "Verifique si usa esta misma contraseña en otros sitios."
                ]
            ];
        }
        return $analysis;
    }
}
?>