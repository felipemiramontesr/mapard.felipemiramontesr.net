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

        // HYBRID REQUEST ENGINE
        if (function_exists('curl_init')) {
            // OPTION A: cURL (Preferred)
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            // Relax SSL for local dev if needed, typically strictly verified in prod
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === FALSE) {
                error_log("Gemini API Error: cURL Connection failed - " . $curlError);
                return $this->getFallbackAnalysis($data);
            }
        } else {
            // OPTION B: file_get_contents (Fallback for limited envs)
            $options = [
                'http' => [
                    'header' => "Content-type: application/json\r\n",
                    'method' => 'POST',
                    'content' => json_encode($payload),
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

            // Check for HTTP errors in Fallback Mode
            if ($response === FALSE) {
                error_log("Gemini API Error: Stream Connection failed");
                return $this->getFallbackAnalysis($data);
            }

            // Parse headers for status code
            $httpCode = 0;
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
            error_log("Gemini API Error: HTTP $httpCode - Response: $response");
            return $this->getFallbackAnalysis($data);
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

            if ($response === FALSE) {
                // EXPOSE CURL ERROR IN FALLBACK STORY
                $msg = "cURL Error: " . $curlError; 
                error_log("Gemini API Error: " . $msg);
                return $this->getFallbackAnalysis($data, $msg);
            }
        } else {
            // ... (OMITTED for brevity, keep existing else block but update the return)
            // But since I'm replacing the whole file content in my head, I need to be careful with the replace tool.
            // Actually, I will just update the calls and the method definition.
        }

        // ...
        
    // UPDATED FALLBACK WITH ERROR INJECTION
    private function getFallbackAnalysis($breaches, $debugError = '') {
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