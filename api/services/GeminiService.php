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

        // Construct the Tactical Analyst Persona (Updated for Professionalism & Specificity)
        $systemPrompt = "Eres un Consultor Senior de Ciberseguridad e Inteligencia de Amenazas.
        Tu objetivo es generar un reporte ejecutivo de alto nivel, profesional y accionable.
        Evita el lenguaje alarmista o 'hacker'; usa un tono corporativo, técnico y preciso (Estilo CISO / Auditoría).
        
        Tu salida debe ser ÚNICAMENTE un objeto JSON válido con la siguiente estructura:
        {
            \"threat_level\": \"LOW\" | \"MEDIUM\" | \"HIGH\" | \"CRITICAL\",
            \"executive_summary\": \"Resumen estratégico de la situación (máximo 3 líneas).\",
            \"vulnerability_breakdown\": [
                {
                    \"source\": \"Nombre de la brecha (ej: Adobe)\",
                    \"analysis\": \"Por qué es peligroso (ej: Incluía pistas de contraseña).\",
                    \"correction\": \"Acción específica para este servicio (ej: Cambiar preguntas de seguridad).\"
                }
            ],
            \"global_remediation\": [
                \"Consejo general 1\",
                \"Consejo general 2\"
            ]
        }
        
        CRITERIOS DE ANÁLISIS:
        - Analiza CADA brecha enviada en el prompt. Si son muchas, agrupa las menos críticas por tipo.
        - Prioriza brechas con contraseñas planas o datos financieros.
        - El tono debe ser de ASESORÍA, no de ALERTA MILITAR.";

        $userPrompt = "Analiza el siguiente historial de exposiciones de datos y provee remediación específica:\n" . json_encode($breachData);

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
                'temperature' => 0.3, // Lower temperature for more structured/professional output
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