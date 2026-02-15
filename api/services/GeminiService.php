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
        IMPORTANTE: Responde SOLO con JSON válido. Sin bloque de código markdown (```json).";

        $userPrompt = "Analiza estas brechas de seguridad:\n" . json_encode($data) . "\n\n" .
            "Genera un JSON con esta estructura exacta:\n" .
            "{
              \"threat_level\": \"LOW|MEDIUM|HIGH|CRITICAL\",
              \"executive_summary\": \"Resumen ejecutivo de alto nivel...\",
              \"detailed_analysis\": [
                {
                  \"source_name\": \"Nombre de la fuente\",
                  \"incident_story\": \"Narrativa detallada del incidente...\",
                  \"risk_explanation\": \"Por qué es peligroso...\",
                  \"specific_remediation\": [\"Paso 1\", \"Paso 2\"]
                }
              ],
              \"dynamic_glossary\": {\"Término\": \"Definición\"}
            }";

        $body = [
            'contents' => [
                ['parts' => [['text' => $systemPrompt . "\n---\n" . $userPrompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 4000 // Reduced for Pro
            ]
        ];

        // HYBRID REQUEST ENGINE
        $response = null;
        $httpCode = 0;
        $startTime = microtime(true);
        $errorMsg = "";

        // OPTION A: cURL (Preferred)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60s timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Relax SSL as requested
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) $errorMsg = "cURL Error: " . curl_error($ch);
            curl_close($ch);
        } else {
            // OPTION B: file_get_contents
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($body),
                    'timeout' => 60,
                    'ignore_errors' => true
                ],
                'ssl' => [
                   'verify_peer' => false,
                   'verify_peer_name' => false
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            // Parse headers for HTTP code
            if (isset($http_response_header)) {
                preg_match('#HTTP/\d\.\d (\d{3})#', $http_response_header[0], $matches);
                $httpCode = isset($matches[1]) ? intval($matches[1]) : 0;
            }
        }
        
        // Handle API Errors
        if ($httpCode !== 200) {
            $msg = "HTTP $httpCode | Model: {$this->model} | Response: " . substr($response, 0, 150);
            error_log("Gemini API Error: $msg");

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