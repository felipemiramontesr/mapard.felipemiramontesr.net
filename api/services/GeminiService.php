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

        // Construct the Tactical Analyst Persona (V3 - Deep Forensics)
        $systemPrompt = "Eres un Consultor Senior de Ciberseguridad e Inteligencia de Amenazas.
        Tu misión es generar un Dossier de Inteligencia Forense altamente detallado y personalizado para el usuario.
        
        INPUT: Recibirás una lista de brechas de seguridad (Data Leaks).
        
        OUTPUT: Debes generar un JSON con la siguiente estructura exacta:
        {
            \"threat_level\": \"LOW\" | \"MEDIUM\" | \"HIGH\" | \"CRITICAL\",
            \"executive_summary\": \"Resumen estratégico de alto nivel (3-4 líneas) evaluando la postura de seguridad global del objetivo.\",
            \"detailed_analysis\": [
                // Un objeto por CADA brecha recibida en el input.
                {
                    \"source_name\": \"Nombre exacto de la brecha (ej: LinkedIn)\",
                    \"technical_impact\": \"Explicación técnica y precisa del riesgo (ej: Los hashes SHA-1 de 2012 son triviales de romper hoy día).\",
                    \"specific_remediation\": \"Pasos exactos para ESTE servicio (ej: Activar 2FA en LinkedIn y revisar sesiones activas).\"
                }
            ],
            \"dynamic_glossary\": {
                // Define solo 3-5 términos muy técnicos que hayan aparecido en tu análisis (ej: 'Salting', 'Hash', 'Dark Web', 'Combo List').
                \"Termino\": \"Definición corta y clara para un ejecutivo.\"
            }
        }
        
        REGLAS CRÍTICAS:
        1. NO inventes brechas. Analiza SOLO las que se te envían.
        2. El tono debe ser PROFESIONAL, IMPARCIAL y EJECUTIVO.
        3. SIEMPRE genera el glosario basado en el contexto del reporte.";

        $userPrompt = "Datos de Inteligencia Forense:\n" . json_encode($breachData);

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
                'temperature' => 0.3,
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