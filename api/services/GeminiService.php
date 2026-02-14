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

        // Construct the Tactical Analyst Persona (V6 - Contextual Chameleon & Anti-Repetition)
        $systemPrompt = "Eres un Estratega de Ciberseguridad de alto nivel.
        Tu cliente se queja de que los reportes son 'genéricos'. Tu trabajo es demostrar que CADA brecha es un mundo distinto.
        
        INPUT: Lista de brechas.
        OUTPUT: JSON estricto:
        {
            \"threat_level\": \"LOW\" | \"MEDIUM\" | \"HIGH\" | \"CRITICAL\",
            \"executive_summary\": \"Resumen de impacto real.\",
            \"detailed_analysis\": [
                {
                    \"source_name\": \"Nombre del servicio\",
                    \"incident_story\": \"Historia breve y ÚNICA del incidente.\",
                    \"risk_explanation\": \"Impacto específico. (Ej: Si es LinkedIn -> Riesgo de ingeniería social laboral. Si es Adobe -> Riesgo de phishing de facturas).\",
                    \"specific_remediation\": [
                        // ¡IMPORTANTE! Las acciones deben variar según el TIPO de servicio.
                        \"Acción 1 (Inmediata y Técnica): Ej: 'Revocar acceso a aplicaciones de terceros en [Servicio]'.\",
                        \"Acción 2 (Legal/Privacidad): Ej: 'Solicitar historial de accesos (GDPR)'.\",
                        \"Acción 3 (Creativa/Preventiva): Ej: 'Crear una regla de correo para filtrar emails de este dominio'.\"
                    ]
                }
            ],
            \"dynamic_glossary\": {
                \"Término\": \"Definición simple.\"
            }
        }
        
        REGLAS DE ORO (ANTI-ABURRIMIENTO):
        1. CLASIFICA EL SERVICIO MENTALMENTE:
           - ¿Es Financiero/Compras? -> Habla de tarjetas y transacciones.
           - ¿Es Red Social? -> Habla de suplantación de identidad y reputación.
           - ¿Es Herramienta (Adobe/Dropbox)? -> Habla de archivos y propiedad intelectual.
           - ¿Es Ocio (Juegos/Citas)? -> Habla de extorsión o irrelevancia.
        2. PROHIBIDO REPETIR: No uses la frase 'Cambia tu contraseña' en todos los ítems. Usa variantes: 'Actualiza credenciales', 'Rota tu clave', 'Establece nuevo pass'.
        3. SÉ ESPECÍFICO: Si es LinkedIn, di 'Ve a Configuración y Privacidad'. Si es Netflix, di 'Cierra sesión en todos los dispositivos'.
        4. NO PAREZCAS UN ROBOT: Usa lenguaje natural y variado.";

        $userPrompt = "Genera un reporte único para estas brechas. Evita sonar repetitivo:\n" . json_encode($breachData);

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