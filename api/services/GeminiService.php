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

    public function analyzeBreach($breachData)
    {
        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        // Construct the Tactical Analyst Persona
        $systemPrompt = "Eres un Analista de Ciberinteligencia Senior (Nivel Black Ops / OTAN). 
        Tu misión es analizar reportes de brechas de datos (Data Leaks) y generar un informe táctico en ESPAÑOL NEUTRO.
        
        Tu salida debe ser ÚNICAMENTE un objeto JSON válido con la siguiente estructura (sin markdown, sin ```json):
        {
            \"threat_level\": \"LOW\" | \"MEDIUM\" | \"HIGH\" | \"CRITICAL\",
            \"executive_summary\": \"Dos párrafos concisos y directos resumiendo el impacto estratégico de la filtración.\",
            \"actionable_intel\": [
                \"Acción inmediata 1\",
                \"Acción inmediata 2\",
                \"Acción inmediata 3\"
            ]
        }
        
        CRITERIOS DE AMENAZA:
        - CRITICAL: Si incluye Passwords, Huellas Digitales, o Datos Financieros.
        - HIGH: Si incluye Teléfonos, Direcciones Físicas o IP.
        - MEDIUM: Si incluye Nombres completos, Fechas de nacimiento.
        - LOW: Si solo son correos electrónicos o usernames.
        
        TONO: Militar, directo, sin rodeos. Usa términos como 'Vector de ataque', 'Exfiltración', 'Compromiso de identidad'.";

        $userPrompt = "Analiza estos datos de brecha:\n" . json_encode($breachData);

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
                'temperature' => 0.4, // Low creativity for consistent reporting
                'responseMimeType' => 'application/json'
            ]
        ];

        // Use file_get_contents instead of cURL (to avoid extension dependency issues)
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($payload),
                'timeout' => 10,
                'ignore_errors' => true // To capture error responses
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        // Check for HTTP errors
        if ($response === FALSE) {
            error_log("Gemini API Error: Connection failed");
            return null;
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
            return null; // Fail-safe
        }

        try {
            $jsonResponse = json_decode($response, true);
            $rawText = $jsonResponse['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$rawText)
                return null;

            // Clean up Markdown if Gemini ignores the system prompt
            $cleanJson = str_replace(['```json', '```'], '', $rawText);

            return json_decode($cleanJson, true);

        } catch (Exception $e) {
            error_log("Gemini JSON Parse Error: " . $e->getMessage());
            return null;
        }
    }
}
?>