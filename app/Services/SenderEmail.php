<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class SenderEmail
{
    protected $email_pruebas;

    protected $emisor_email;

    protected $emisor_nombre;

    protected $emisor_clave;

    protected $asunto;

    protected $mail;

    private function configureSMTP()
    {
        $this->email_pruebas = config('mail.dev_address', 'enlinea@comfaca.com');

        // Configuración del servidor SMTP desde config/mail.php
        $this->mail->isSMTP();
        $this->mail->Host = config('mail.mailers.smtp.host', 'smtp.gmail.com');
        $this->mail->SMTPAuth = true;
        $this->mail->Username = $this->emisor_email;
        $this->mail->Password = $this->emisor_clave;

        // Encripción y puerto configurables: tls->587, ssl->465
        $encryption = config('mail.mailers.smtp.scheme', 'tls');
        if (in_array(strtolower($encryption), ['ssl', 'smtps'])) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL implícito
            $this->mail->Port = config('mail.mailers.smtp.port', 465);
        } else {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // STARTTLS
            $this->mail->Port = config('mail.mailers.smtp.port', 587);
        }

        // Opcionales para entornos restrictivos
        $this->mail->SMTPAutoTLS = true;
        $this->mail->Timeout = config('mail.mailers.smtp.timeout', 15);
        $this->mail->CharSet = 'UTF-8';
    }

    public function __construct(array|null $params = null)
    {
        $this->mail = new PHPMailer(true);
        $this->emisor_email = $params['emisor_email'] ?? config('mail.from.address');
        $this->emisor_clave = $params['emisor_clave'] ?? config('mail.mailers.smtp.password');
        $this->asunto = $params['asunto'] ?? "Comfaca Credito Notificación - Subscripción";
        $this->emisor_nombre = $params['emisor_nombre'] ?? config('mail.from.name');

        $this->configureSMTP();
    }

    public function send(
        string|array $to = '',
        string $body = '',
        ?array $attachments = null,
        string $altBody = '',
        ?array $cc = null,
        ?array $bcc = null
    ) {
        try {
            // Remitente
            $this->mail->setFrom($this->emisor_email, $this->emisor_nombre);

            // Destinatarios
            if (config('app.env') === 'local') {
                $to = $this->email_pruebas;
            }

            if (is_array($to)) {
                foreach ($to as $address) {
                    $this->mail->addAddress($address);
                }
            } else {
                $this->mail->addAddress($to);
            }

            // Copia (CC)
            if (is_array($cc)) {
                foreach ($cc as $address) {
                    $this->mail->addCC($address);
                }
            }

            // Copia oculta (BCC)
            if (is_array($bcc)) {
                foreach ($bcc as $address) {
                    $this->mail->addBCC($address);
                }
            }

            // Contenido
            $this->mail->isHTML(true); // Habilitar formato HTML
            $this->mail->Subject = $this->asunto;
            $this->mail->Body = $body;
            $this->mail->AltBody = $altBody;

            // Archivos adjuntos
            if (is_array($attachments)) {
                foreach ($attachments as $attachmentPath) {
                    if (file_exists($attachmentPath)) {
                        $this->mail->addAttachment($attachmentPath);
                    } else {
                        throw new PHPMailerException("El archivo adjunto no existe: {$attachmentPath}");
                    }
                }
            }

            $this->mail->send();

            return 'Correo enviado exitosamente';
        } catch (PHPMailerException $e) {
            throw new \Exception("Error al enviar el correo: {$this->mail->ErrorInfo}");
        }
    }
}
