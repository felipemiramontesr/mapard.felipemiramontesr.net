<?php

namespace MapaRD\Services;

class MailService
{
    /**
     * Sends a tactical 2FA code to the target email.
     * Uses PHP's mail() function with tactical headers.
     */
    public static function send2FA($toEmail, $code)
    {
        $subject = "MAPARD: Tactical Access Code Required";

        $message = "
        <html>
        <head>
            <title>MAPARD Security Alert</title>
        </head>
        <body style='background-color: #0a0e27; color: #ffffff; font-family: monospace; padding: 20px;'>
            <div style='border: 1px solid #00f3ff; padding: 20px; max-width: 600px; margin: auto;'>
                <h1 style='color: #00f3ff; text-transform: uppercase;'>Terminal Access Requested</h1>
                <p>Se ha solicitado acceso al Dossier de Inteligencia para:</p>
                <p style='color: #fca311; font-weight: bold;'>$toEmail</p>
                
                <hr style='border: 0; border-top: 1px solid #1e293b; margin: 20px 0;'>
                
                <p>Su código táctico de verificación es:</p>
                <div style='background-color: #1e293b; padding: 15px; text-align: center; font-size: 24px; letter-spacing: 10px; color: #00f3ff; border: 1px dashed #00f3ff;'>
                    $code
                </div>
                
                <p style='margin-top: 20px; font-size: 12px; color: #64748b;'>
                    Este código es válido por 10 minutos. Si no solicitó esta conexión, ignore este mensaje.
                    <br>MAPARD Protocol M - Inteligencia Táctica.
                </p>
            </div>
        </body>
        </html>
        ";

        // To send HTML mail, the Content-type header must be set
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=utf-8';

        // Additional headers
        $headers[] = 'To: ' . $toEmail;
        $headers[] = 'From: MAPARD Intelligence <noreply@mapard.felipemiramontesr.net>';
        $headers[] = 'X-Mailer: PHP/' . phpversion();

        return mail($toEmail, $subject, $message, implode("\r\n", $headers));
    }
}
