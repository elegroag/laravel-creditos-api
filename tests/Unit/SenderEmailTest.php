<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SenderEmail;

class SenderEmailTest extends TestCase
{
    /**
     * Test que envía un correo de prueba usando el servicio SenderEmail.
     */
    public function test_send_email_with_sender_email_service()
    {
        // Configuración para el envío
        $params = [
            'emisor_email' => config('mail.from.address'),
            'emisor_clave' => config('mail.mailers.smtp.password'),
            'asunto' => 'Test de SenderEmail Service',
            'emisor_nombre' => config('mail.from.name', 'Comfaca Crédito')
        ];

        // Crear instancia del servicio
        $sender = new SenderEmail($params);

        // Contenido del correo
        $to = 'maxedwwin@gmail.com';
        $body = '
            <html>
            <head>
                <title>Test de SenderEmail</title>
            </head>
            <body>
                <h1>Test del Servicio SenderEmail</h1>
                <p>Este es un correo de prueba para verificar que el servicio SenderEmail funciona correctamente.</p>
                <p><strong>Fecha de envío:</strong> ' . now()->format('Y-m-d H:i:s') . '</p>
                <p><strong>Entorno:</strong> ' . config('app.env') . '</p>
                <hr>
                <p><small>Este correo fue enviado automáticamente desde el sistema de pruebas.</small></p>
            </body>
            </html>
        ';

        $altBody = 'Test del Servicio SenderEmail - Este es un correo de prueba para verificar que el servicio SenderEmail funciona correctamente.';

        try {
            // Enviar el correo
            $result = $sender->send($to, $body, null, $altBody);

            // Verificar que el correo se envió correctamente
            $this->assertEquals('Correo enviado exitosamente', $result);
        } catch (\Exception $e) {
            // Si hay un error, lo mostramos en el test
            $this->fail('Error al enviar el correo: ' . $e->getMessage());
        }
    }

    /**
     * Test que envía un correo con múltiples destinatarios.
     */
    public function test_send_email_to_multiple_recipients()
    {
        $params = [
            'emisor_email' => config('mail.from.address'),
            'emisor_clave' => config('mail.mailers.smtp.password'),
            'asunto' => 'Test Múltiples Destinatarios',
            'emisor_nombre' => config('mail.from.name', 'Comfaca Crédito')
        ];

        $sender = new SenderEmail($params);

        $to = ['maxedwwin@gmail.com', config('mail.dev_address', 'sistemas@comfaca.com')];
        $body = '
            <html>
            <body>
                <h2>Test con Múltiples Destinatarios</h2>
                <p>Este correo se envía a múltiples destinatarios para probar la funcionalidad.</p>
                <p>Destinatarios: ' . implode(', ', $to) . '</p>
            </body>
            </html>
        ';

        $altBody = 'Test con Múltiples Destinatarios - Este correo se envía a múltiples destinatarios para probar la funcionalidad.';

        try {
            $result = $sender->send($to, $body, null, $altBody);
            $this->assertEquals('Correo enviado exitosamente', $result);
        } catch (\Exception $e) {
            $this->fail('Error al enviar correo a múltiples destinatarios: ' . $e->getMessage());
        }
    }

    /**
     * Test que envía un correo con CC y BCC.
     */
    public function test_send_email_with_cc_and_bcc()
    {
        $params = [
            'emisor_email' => config('mail.from.address'),
            'emisor_clave' => config('mail.mailers.smtp.password'),
            'asunto' => 'Test con CC y BCC',
            'emisor_nombre' => config('mail.from.name', 'Comfaca Crédito')
        ];

        $sender = new SenderEmail($params);

        $to = 'maxedwwin@gmail.com';
        $cc = [config('mail.dev_address', 'sistemas@comfaca.com')];
        $bcc = ['test@comfaca.com'];

        $body = '
            <html>
            <body>
                <h2>Test con CC y BCC</h2>
                <p>Este correo prueba el envío con copia (CC) y copia oculta (BCC).</p>
                <ul>
                    <li>Para: maxedwwin@gmail.com</li>
                    <li>CC: ' . $cc[0] . '</li>
                    <li>BCC: ' . $bcc[0] . '</li>
                </ul>
            </body>
            </html>
        ';

        $altBody = 'Test con CC y BCC - Este correo prueba el envío con copia (CC) y copia oculta (BCC).';

        try {
            $result = $sender->send($to, $body, null, $altBody, $cc, $bcc);
            $this->assertEquals('Correo enviado exitosamente', $result);
        } catch (\Exception $e) {
            $this->fail('Error al enviar correo con CC y BCC: ' . $e->getMessage());
        }
    }
}
