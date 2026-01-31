<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SenderEmail;

class SenderEmailDiagnosticTest extends TestCase
{
    /**
     * Test de diagnóstico para verificar la configuración SMTP.
     */
    public function test_diagnose_smtp_configuration()
    {
        echo "\n=== DIAGNÓSTICO DE CONFIGURACIÓN SMTP ===\n";
        
        // Mostrar configuración actual
        echo "APP_ENV: " . config('app.env') . "\n";
        echo "MAIL_MAILER: " . config('mail.default') . "\n";
        echo "MAIL_HOST: " . config('mail.mailers.smtp.host') . "\n";
        echo "MAIL_PORT: " . config('mail.mailers.smtp.port') . "\n";
        echo "MAIL_USERNAME: " . config('mail.mailers.smtp.username') . "\n";
        echo "MAIL_PASSWORD: " . (config('mail.mailers.smtp.password') ? '***CONFIGURADO***' : 'NO CONFIGURADO') . "\n";
        echo "MAIL_FROM_ADDRESS: " . config('mail.from.address') . "\n";
        echo "MAIL_FROM_NAME: " . config('mail.from.name') . "\n";
        echo "MAIL_SCHEME: " . config('mail.mailers.smtp.scheme') . "\n";
        echo "DEV_ADDRESS: " . config('mail.dev_address', 'NO CONFIGURADO') . "\n";
        
        // Verificar variables críticas
        $this->assertNotEmpty(config('mail.mailers.smtp.host'), 'MAIL_HOST no está configurado');
        $this->assertNotEmpty(config('mail.mailers.smtp.port'), 'MAIL_PORT no está configurado');
        $this->assertNotEmpty(config('mail.mailers.smtp.username'), 'MAIL_USERNAME no está configurado');
        $this->assertNotEmpty(config('mail.mailers.smtp.password'), 'MAIL_PASSWORD no está configurado');
        $this->assertNotEmpty(config('mail.from.address'), 'MAIL_FROM_ADDRESS no está configurado');
        
        echo "\n=== CONFIGURACIÓN BÁSICA VERIFICADA ===\n";
    }

    /**
     * Test de conexión SMTP básico.
     */
    public function test_smtp_connection()
    {
        echo "\n=== TEST DE CONEXIÓN SMTP ===\n";
        
        try {
            // Crear instancia de PHPMailer para probar conexión
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuración SMTP
            $mail->isSMTP();
            $mail->Host = config('mail.mailers.smtp.host');
            $mail->SMTPAuth = true;
            $mail->Username = config('mail.mailers.smtp.username');
            $mail->Password = config('mail.mailers.smtp.password');
            $mail->Port = config('mail.mailers.smtp.port');
            
            // Configurar encriptación
            $encryption = config('mail.mailers.smtp.scheme', 'tls');
            if (in_array(strtolower($encryption), ['ssl', 'smtps'])) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Timeout y charset
            $mail->Timeout = config('mail.mailers.smtp.timeout', 15);
            $mail->CharSet = 'UTF-8';
            
            echo "Intentando conectar a: " . $mail->Host . ":" . $mail->Port . "\n";
            echo "Usuario: " . $mail->Username . "\n";
            echo "Encriptación: " . $encryption . "\n";
            
            // Intentar conexión SMTP
            $mail->SMTPConnect();
            
            echo "✅ CONEXIÓN SMTP EXITOSA\n";
            
            // Cerrar conexión
            $mail->smtp->close();
            
            $this->assertTrue(true, 'Conexión SMTP establecida correctamente');
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            echo "❌ ERROR DE CONEXIÓN SMTP: " . $e->getMessage() . "\n";
            echo "Código de error: " . $e->getCode() . "\n";
            
            // Información adicional de depuración
            if ($mail->SMTPDebug) {
                echo "Debug SMTP: " . $mail->smtp->getError() . "\n";
            }
            
            $this->fail('No se pudo conectar al servidor SMTP: ' . $e->getMessage());
            
        } catch (\Exception $e) {
            echo "❌ ERROR GENERAL: " . $e->getMessage() . "\n";
            $this->fail('Error inesperado durante la conexión SMTP: ' . $e->getMessage());
        }
    }

    /**
     * Test de envío simple sin usar SenderEmail.
     */
    public function test_simple_email_send()
    {
        echo "\n=== TEST DE ENVÍO SIMPLE ===\n";
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuración SMTP
            $mail->isSMTP();
            $mail->Host = config('mail.mailers.smtp.host');
            $mail->SMTPAuth = true;
            $mail->Username = config('mail.mailers.smtp.username');
            $mail->Password = config('mail.mailers.smtp.password');
            $mail->Port = config('mail.mailers.smtp.port');
            
            // Encriptación
            $encryption = config('mail.mailers.smtp.scheme', 'tls');
            if (in_array(strtolower($encryption), ['ssl', 'smtps'])) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $mail->CharSet = 'UTF-8';
            
            // Remitente y destinatario
            $mail->setFrom(config('mail.from.address'), config('mail.from.name', 'Test'));
            
            // En entorno local, enviar a dirección de pruebas
            $to = config('app.env') === 'local' 
                ? config('mail.dev_address', 'sistemas@comfaca.com') 
                : 'maxedwwin@gmail.com';
                
            $mail->addAddress($to);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = 'Test Simple de PHPMailer';
            $mail->Body = '<h1>Test Simple</h1><p>Este es un test de envío simple.</p>';
            $mail->AltBody = 'Test Simple - Este es un test de envío simple.';
            
            echo "Enviando correo a: " . $to . "\n";
            
            // Enviar
            $mail->send();
            
            echo "✅ CORREO ENVIADO EXITOSAMENTE\n";
            
            $this->assertTrue(true, 'Correo enviado correctamente');
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            echo "❌ ERROR AL ENVIAR: " . $e->getMessage() . "\n";
            echo "Error info: " . $e->errorMessage() . "\n";
            $this->fail('Error al enviar correo: ' . $e->getMessage());
            
        } catch (\Exception $e) {
            echo "❌ ERROR GENERAL: " . $e->getMessage() . "\n";
            $this->fail('Error inesperado: ' . $e->getMessage());
        }
    }
}
