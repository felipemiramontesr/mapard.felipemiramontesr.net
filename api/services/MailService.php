<?php

namespace MapaRD\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    /**
     * Sends a tactical 2FA code to the target email using PHPMailer (SMTP).
     */
    public static function send2FA($toEmail, $code)
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : '';
            $mail->SMTPAuth = true;
            $mail->Username = defined('SMTP_USER') ? SMTP_USER : '';
            $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
            $mail->SMTPSecure = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'ssl';
            $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 465;
            $mail->CharSet = 'UTF-8';

            // Recipients
            $fromEmail = defined('SMTP_USER') ? SMTP_USER : 'noreply@mapard.felipemiramontesr.net';
            $mail->setFrom($fromEmail, 'MAPARD Intelligence');
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "MAPARD: Tactical Access Code Required";

            $message = "
            <html>
            <head>
                <style>
                    body {
                        background-color: #0a0e27; 
                        margin: 0; padding: 40px 20px;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    }
                    .card {
                        background-color: #111827;
                        border: 1px solid #4a5578;
                        padding: 40px;
                        max-width: 500px;
                        margin: auto;
                        border-radius: 4px;
                        text-align: center;
                    }
                    .header {
                        color: #ffffff;
                        text-transform: uppercase;
                        font-size: 14px;
                        font-weight: 600;
                        letter-spacing: 0.2em;
                        margin-bottom: 30px;
                    }
                    .content-text {
                        color: #c5cae0;
                        font-size: 14px;
                        line-height: 1.6;
                        margin-bottom: 30px;
                    }
                    .code-box {
                        color: #8a9fca;
                        font-size: 42px;
                        letter-spacing: 12px;
                        font-family: 'Courier New', Courier, monospace;
                        font-weight: 200;
                        margin: 40px 0;
                        padding: 20px;
                        border-top: 1px solid #4a5578;
                        border-bottom: 1px solid #4a5578;
                    }
                    .footer {
                        margin-top: 40px;
                        font-size: 10px;
                        color: #6b7490;
                        font-family: 'Courier New', Courier, monospace;
                        letter-spacing: 0.1em;
                        text-transform: uppercase;
                    }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div class='header'>TERMINAL ACCESS REQUIRED</div>
                    <div class='content-text'>
                        Se ha detectado una solicitud de acceso al Dossier de Inteligencia para la credencial:<br>
                        <strong style='color: #ffffff; display: block; margin-top: 10px;'>$toEmail</strong>
                    </div>
                    
                    <div class='code-box'>$code</div>
                    
                    <div class='content-text' style='font-size: 12px; opacity: 0.8;'>
                        Este código táctico es de un solo uso y expirará en breve.
                    </div>
                    
                    <div class='footer'>
                        MAPARD CORTEX ENGINE // TACTICAL_GATE_M<br>
                        ID_OP: " . strtoupper(bin2hex(random_bytes(4))) . "
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->Body = $message;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("MailService Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Sends a tactical rescue code to the target email for password recovery.
     */
    public static function sendRescueCode($toEmail, $code)
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : '';
            $mail->SMTPAuth = true;
            $mail->Username = defined('SMTP_USER') ? SMTP_USER : '';
            $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
            $mail->SMTPSecure = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'ssl';
            $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 465;
            $mail->CharSet = 'UTF-8';

            // Recipients
            $fromEmail = defined('SMTP_USER') ? SMTP_USER : 'noreply@mapard.felipemiramontesr.net';
            $mail->setFrom($fromEmail, 'MAPARD Intelligence');
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "MAPARD: Emergency Access Recovery";

            $message = "
            <html>
            <head>
                <style>
                    body {
                        background-color: #0a0e27; 
                        margin: 0; padding: 40px 20px;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    }
                    .card {
                        background-color: #111827;
                        border: 1px solid #4a5578;
                        padding: 40px;
                        max-width: 500px;
                        margin: auto;
                        border-radius: 4px;
                        text-align: center;
                    }
                    .header {
                        color: #ff3366;
                        text-transform: uppercase;
                        font-size: 14px;
                        font-weight: 600;
                        letter-spacing: 0.2em;
                        margin-bottom: 30px;
                    }
                    .content-text {
                        color: #c5cae0;
                        font-size: 14px;
                        line-height: 1.6;
                        margin-bottom: 30px;
                    }
                    .code-box {
                        color: #ff3366;
                        font-size: 42px;
                        letter-spacing: 12px;
                        font-family: 'Courier New', Courier, monospace;
                        font-weight: 200;
                        margin: 40px 0;
                        padding: 20px;
                        border-top: 1px solid #4a5578;
                        border-bottom: 1px solid #4a5578;
                    }
                    .footer {
                        margin-top: 40px;
                        font-size: 10px;
                        color: #6b7490;
                        font-family: 'Courier New', Courier, monospace;
                        letter-spacing: 0.1em;
                        text-transform: uppercase;
                    }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div class='header'>RECOVERY PROTOCOL ACTIVATED</div>
                    <div class='content-text'>
                        Se ha detectado una solicitud de recuperación de clave para la credencial:<br>
                        <strong style='color: #ffffff; display: block; margin-top: 10px;'>$toEmail</strong>
                    </div>
                    
                    <div class='code-box'>$code</div>
                    
                    <div class='content-text' style='font-size: 12px; opacity: 0.8;'>
                        Este código anulará la seguridad anterior. Tiene una validez de 10 minutos.
                    </div>
                    
                    <div class='footer'>
                        MAPARD CORTEX ENGINE // TACTICAL_RESCUE_S<br>
                        ID_RESCUE: " . strtoupper(bin2hex(random_bytes(4))) . "
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->Body = $message;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("MailService Error (Rescue): {$mail->ErrorInfo}");
            return false;
        }
    }
}
