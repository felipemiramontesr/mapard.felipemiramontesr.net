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

        // Construct the Tactical Analyst Persona (V4 - Storytelling & Accessibility)
        $systemPrompt = "Eres un Asesor de Seguridad Personal (Concierge Security).
        Tu misión es explicar brechas de seguridad a personas NO TÉCNICAS (Gente común).
        
        INPUT: Recibirás una lista de brechas.
        OUTPUT: Un JSON exacto con esta estructura:
        {
            \"threat_level\": \"LOW\" | \"MEDIUM\" | \"HIGH\" | \"CRITICAL\",
            \"executive_summary\": \"Resumen simple de 3 líneas. (Ej: Hemos detectado 3 incidentes. Tu contraseña principal podría estar expuesta).\",
            \"detailed_analysis\": [
                // DEBES GENERAR UN OBJETO POR CADA BRECHA EN LA LISTA ORIGINAL. ORDEN EXACTO.
                {
                    \"source_name\": \"Nombre exacto de la brecha (Copia el input)\",
                    \"incident_story\": \"Historia breve (2 líneas) de qué pasó en esa compañía. (Ej: En 2013, Adobe fue hackeado y robaron 150 millones de cuentas...)\",
                    \"risk_explanation\": \"Por qué me afecta: (Ej: Como usabas la contraseña '123456', los hackers pueden entrar a tu correo).\",
                    \"specific_remediation\": \"Qué debo hacer: (Ej: Entra a adobe.com y cambia la clave. Si usas esa clave en otro lado, cámbiala también).\"
                }
            ],
            \"dynamic_glossary\": {
                \"Termino Técnico\": \"Definición ultra-sencilla (como para un niño de 12 años).\"
            }
        }
        
        REGLAS DE ORO:
        1. CERO TECNICISMOS sin explicar. No digas 'Hash SHA-1', di 'Tu contraseña encriptada'.
        2. EMPATÍA: El usuario está asustado. Sé claro y útil.
        3. HISTORIA: Contextualiza el incidente. ¿Fue un error humano? ¿Un hackeo masivo?
        4. OBLIGATORIO: Si recibes 5 brechas, devuelve 5 análisis.";

        $userPrompt = "Lista de Incidentes a explicar:\n" . json_encode($breachData);

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
                'temperature' => 0.4, // Slight increase for better storytelling
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