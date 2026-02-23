<?php
/**
 * MAPARD ROOT DOCTOR V1.1
 * Stealth Root Diagnostic
 */

if (!isset($_GET['auth']) || $_GET['auth'] !== 'zero_day_wipe') {
    header("HTTP/1.1 404 Not Found");
    die();
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>SISTEMA DE DIAGNÓSTICO MAPARD</title>
    <style>
        body {
            background: #000;
            color: #0f0;
            font-family: monospace;
            padding: 20px;
        }

        .success {
            color: #0f0;
        }

        .fail {
            color: #f00;
        }

        .section {
            margin-bottom: 20px;
            border: 1px solid #141;
            padding: 10px;
        }
    </style>
</head>

<body>
    <h1>🏥 DIAGNÓSTICO ROOT - MAPARD</h1>

    <div class="section">
        <h3>[1] Entorno PHP</h3>
        <ul>
            <li>Versión:
                <?php echo PHP_VERSION; ?>
            </li>
            <li>Memoria:
                <?php echo ini_get('memory_limit'); ?>
            </li>
            <li>OS:
                <?php echo PHP_OS; ?>
            </li>
        </ul>
    </div>

    <div class="section">
        <h3>[2] Extensiones de Red</h3>
        <ul>
            <li>cURL:
                <?php echo extension_loaded('curl') ? '<span class="success">OK</span>' : '<span class="fail">NO</span>'; ?>
            </li>
            <li>OpenSSL:
                <?php echo extension_loaded('openssl') ? '<span class="success">OK</span>' : '<span class="fail">NO</span>'; ?>
            </li>
        </ul>
    </div>

    <div class="section">
        <h3>[3] Pruebas de Enlace (Sin Verbose)</h3>
        <?php
        $targets = [
            'Google' => 'https://www.google.com',
            'HIBP' => 'https://haveibeenpwned.com/api/v3/breaches'
        ];

        foreach ($targets as $name => $url) {
            echo "<strong>Probando $name...</strong> ";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/121.0.0.0');
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Intencional para ver si el error es el Cert
        
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($code >= 200 && $code < 400) {
                echo "<span class='success'>EXITO (CODE $code)</span><br>";
            } else {
                echo "<span class='fail'>FALLO (CODE $code). Error: $err</span><br>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h3>[4] Diagnóstico SSL Local</h3>
        <?php
        $certs = openssl_get_cert_locations();
        echo "Ruta CA: " . $certs['default_cert_file'] . "<br>";
        echo "Existe: " . (file_exists($certs['default_cert_file']) ? "SÍ" : "NO");
        ?>
    </div>

</body>

</html>