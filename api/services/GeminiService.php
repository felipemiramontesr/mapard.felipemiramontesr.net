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
        // SWITCH TO FLASH (More reliable availability & speed)
        $this->model = 'gemini-1.5-flash';
        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;
        $debugUrl = $this->baseUrl . $this->model . ':generateContent?key=MASKED';

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

        // HYBRID REQUEST ENGINE
        $jsonPayload = json_encode($payload);
        $response = false;
        $httpCode = 0;
        $curlError = '';

        if (function_exists('curl_init')) {
            // OPTION A: cURL (Preferred)
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Relax SSL as requested

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === FALSE) {
                $msg = "cURL Connection Failed: " . $curlError;
                error_log("Gemini API Error: " . $msg);
                return $this->getFallbackAnalysis($data, $msg);
            }
        } else {
            // OPTION B: file_get_contents (Fallback)
            $options = [
                'http' => [
                    'header' => "Content-type: application/json\r\n",
                    'method' => 'POST',
                    'content' => $jsonPayload,
                    'timeout' => 120,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $context = stream_context_create($options);
            $response = file_get_contents($url, false, $context);

            if ($response === FALSE) {
                $msg = "Stream Connection Failed (file_get_contents)";
                error_log("Gemini API Error: " . $msg);
                return $this->getFallbackAnalysis($data, $msg);
            }

            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/^HTTP\/\d\.\d (\d+)/', $header, $matches)) {
                        $httpCode = intval($matches[1]);
                        break;
                    }
                }
            }
        }

        if ($httpCode !== 200) {
            $msg = "HTTP $httpCode | Model: {$this->model} | Url: $debugUrl | Response: " . substr($response, 0, 100);
            error_log("Gemini API Error: $msg");
            return $this->getFallbackAnalysis($data, $msg);
        }

        // SAVE RAW RESPONSE FOR DEBUGGING
        file_put_contents(__DIR__ . '/../gemini_debug.log', "--- REQUEST ---\n" . $userPrompt . "\n\n--- RESPONSE ---\n" . $response . "\n\n", FILE_APPEND);

        try {
            $jsonResponse = json_decode($response, true);
            $rawText = $jsonResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$rawText) {
                $msg = "No Candidate Text in JSON response";
                error_log("Gemini Error: " . $msg);
                return $this->getFallbackAnalysis($data, $msg);
            }

            // Clean up Markdown: Handle ```json and ``` wrapping
            $cleanJson = preg_replace('/^```json\s*|\s*```$/i', '', trim($rawText));

            // Log cleaned JSON
            file_put_contents(__DIR__ . '/../gemini_cleaned.log', $cleanJson);

            $parsed = json_decode($cleanJson, true);

            // STRICT VALIDATION
            if (!is_array($parsed) || empty($parsed['detailed_analysis'])) {
                $msg = "Missing 'detailed_analysis' array in JSON";
                error_log("Gemini Logic Error: " . $msg);
                return $this->getFallbackAnalysis($data, $msg);
            }

            return $parsed;

        } catch (Exception $e) {
            $msg = "Exception: " . $e->getMessage();
            error_log("Gemini Critical Error: " . $msg);
            return $this->getFallbackAnalysis($data, $msg);
        }
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