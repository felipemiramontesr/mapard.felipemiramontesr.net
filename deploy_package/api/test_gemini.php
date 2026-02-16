<?php
require_once __DIR__ . '/services/GeminiService.php';

echo "🦅 INICIANDO PRUEBA DE CONEXIÓN CORTEX (Model: " . GEMINI_MODEL . ")...\n";

// Mock Data (A classic nasty breach)
$mockBreach = [
    [
        "Name" => "LinkedIn",
        "Title" => "LinkedIn",
        "Domain" => "linkedin.com",
        "BreachDate" => "2016-05-18",
        "DataClasses" => ["Email addresses", "Passwords", "Job titles"]
    ],
    [
        "Name" => "Adobe",
        "Title" => "Adobe",
        "Domain" => "adobe.com",
        "BreachDate" => "2013-10-04",
        "DataClasses" => ["Email addresses", "Password hints", "Usernames"]
    ]
];

$service = new GeminiService();
$start = microtime(true);
$result = $service->analyzeBreach($mockBreach);
$duration = round(microtime(true) - $start, 2);

if ($result) {
    echo "✅ CONEXIÓN EXITOSA ($duration s)\n";
    echo "---------------------------------------------------\n";
    echo "THREAT LEVEL: " . $result['threat_level'] . "\n";
    echo "SUMMARY: " . $result['executive_summary'] . "\n";
    echo "INTEL: \n";
    foreach ($result['actionable_intel'] as $step) {
        echo " - $step\n";
    }
    echo "---------------------------------------------------\n";
} else {
    echo "❌ FALLO EN LA CONEXIÓN O PARSEO.\n";
    echo "Revisa el log de errores de PHP.\n";
}
?>