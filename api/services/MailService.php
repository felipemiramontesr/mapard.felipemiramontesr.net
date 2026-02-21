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
            $mail->setFrom(defined('SMTP_USER') ? SMTP_USER : 'noreply@mapard.felipemiramontesr.net', 'MAPARD Intelligence');
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = "MAPARD: Tactical Access Code Required";

            $message = "
            <html>
            <head>
                <style>
                    body { background-color: #0a0e27; color: #ffffff; font-family: 'Courier New', Courier, monospace; padding: 20px; }
                    .card { border: 1px solid #00f3ff; padding: 20px; max-width: 500px; margin: auto; box-shadow: 0 0 20px rgba(0, 243, 255, 0.2); }
                    .header { color: #00f3ff; text-transform: uppercase; font-size: 18px; border-bottom: 1px solid #1e293b; padding-bottom: 10px; margin-bottom: 20px; }
                    .code-box { background-color: #1e293b; padding: 20px; text-align: center; font-size: 32px; letter-spacing: 12px; color: #00f3ff; border: 1px dashed #00f3ff; margin: 20px 0; font-weight: bold; }
                    .footer { margin-top: 30px; font-size: 11px; color: #64748b; border-top: 1px solid #1e293b; padding-top: 10px; }
                </style>
            </head>
            <body>
                <div class='card'>
                    <div class='header'>Terminal Access Required</div>
                    <p>Se ha detectado una solicitud de acceso al Dossier de Inteligencia para:</p>
                    <p style='color: #fca311; font-weight: bold;'>$toEmail</p>
                    
                    <p>Utilice el siguiente código táctico de verificación:</p>
                    <div class='code-box'>$code</div>
                    
                    <p>Este código es de un solo uso y expirará en breve.</p>
                    
                    <div class='footer'>
                        MAPARD CORTEX ENGINE | PROTOCOLO TÁCTICO M<br>
                        Identificador de Operación: " . bin2hex(random_bytes(4)) . "
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
}
