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
        // 🚀 USER REQUEST: PROCESS EVERYTHING (No Limits)
        // Attempt to override server timeout to 5 minutes
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        // PERFORMANCE GUARD: DISABLED BY USER REQUEST
        // $totalBreaches = count($data);
        // if ($totalBreaches > 5) { ... }

        // VERIFIED MODEL (Switching to Flash for Speed/Reliability)
        $this->model = 'gemini-2.0-flash';
        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        // Construct the Tactical Analyst Persona
        $systemPrompt = "Eres un Estratega de Ciberseguridad Senior.
        Tu misión es analizar una lista de brechas y generar un reporte JSON detallado para el cliente.
        IMPORTANTE: Responde SOLO con JSON válido. Sin bloque de código markdown (```json).";

        $count = count($data);
        $userPrompt = "Analiza estas $count brechas de seguridad:\n" . json_encode($data) . "\n\n" .
            "Genera un JSON con esta estructura exacta:\n" .
            "{
              \"threat_level\": \"LOW|MEDIUM|HIGH|CRITICAL\",
              \"executive_summary\": \"Resumen ejecutivo...\",
        INSTRUCCIONES CLAVE DE PERSUASIÓN:
        1. 'detailed_analysis': DEBES devolver un array con EXACTAMENTE $count objetos.
           - Si la entrada tiene $count, la salida TIENE QUE tener $count.
           - Provoca un error si devuelves menos de $count.
           - Analiza CADA UNA. No omitas ninguna.
        3. 'strategic_conclusion': CIERRE DE VENTA.
           - DI: \"La acumulación de estos incidentes crea un perfil de riesgo insostenible. Se requiere una limpieza profunda inmediata.\"
           - Genera miedo a la \"persistencia\" del atacante.

        EJEMPLO DE SALIDA (JSON): \",
              \"detailed_analysis\": [
                {
                  \"source_name\": \"Nombre\",
                  \"incident_story\": \"Historia...\",
                  \"risk_explanation\": \"Impacto crítico...\",
                  \"specific_remediation\": [\"Acción 1\", \"Acción 2\"]
                }
              ],
              \"dynamic_glossary\": {\"Término\": \"Definición\"},
              \"strategic_conclusion\": \"Texto persuasivo final...\"
            }";

        $body = [
            'contents' => [
                ['parts' => [['text' => $systemPrompt . "\n---\n" . $userPrompt]]]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json'
            ]
        ];

        // HYBRID REQUEST ENGINE
        $response = null;
        $httpCode = 0;
        $errorMsg = "";

        // OPTION A: cURL (Preferred)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch))
                $errorMsg = "cURL Error: " . curl_error($ch);
            curl_close($ch);
        } else {
            // OPTION B: file_get_contents (Fallback)
            $opts = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($body),
                    'timeout' => 120,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $context = stream_context_create($opts);
            $response = @file_get_contents($url, false, $context);
            if (isset($http_response_header)) {
                preg_match('#HTTP/\d\.\d (\d{3})#', $http_response_header[0], $matches);
                $httpCode = isset($matches[1]) ? intval($matches[1]) : 0;
            }
        }

        // Handle Transport Errors
        if ($httpCode !== 200) {
            $shortResponse = substr($response ? $response : $errorMsg, 0, 150);
            $msg = "HTTP $httpCode | Model: {$this->model} | Info: $shortResponse";
            error_log("Gemini API Error: $msg");
            return $this->getFallbackAnalysis($data, $msg);
        }

        // Parse Response
        $json = json_decode($response, true);

        // Extract Text
        $rawText = null;
        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            $rawText = $json['candidates'][0]['content']['parts'][0]['text'];
        }

        if (!$rawText) {
            error_log("Gemini Error: No text in response");
            return $this->getFallbackAnalysis($data, "Empty AI Response");
        }

        // Clean up Markdown: Handle ```json and ``` wrapping
        $cleanJson = preg_replace('/^```json\s*|\s*```$/i', '', trim($rawText));

        // SMART EXTRACTION: If chatty preamble exists, find the first { and last }
        if (strpos($cleanJson, '{') !== 0) {
            if (preg_match('/\{[\s\S]*\}/', $cleanJson, $matches)) {
                $cleanJson = $matches[0];
            }
        }

        // Decode JSON from AI
        $parsed = json_decode($cleanJson, true);

        if (!is_array($parsed) || empty($parsed['detailed_analysis'])) {
            $jsonErr = json_last_error_msg();
            $msg = "Invalid JSON structure. JSON Error: $jsonErr. Preview: " . substr($cleanJson, 0, 50) . "...";
            error_log("Gemini Logic Error: $msg");
            return $this->getFallbackAnalysis($data, $msg);
        }

        // Success!
        return $parsed;
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