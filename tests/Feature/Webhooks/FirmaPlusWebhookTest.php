<?php

namespace Tests\Feature\Webhooks;

use Tests\TestCase;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FirmaPlusWebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Generar firma HMAC-SHA256 válida para el webhook
     */
    protected function generarFirmaHMAC(array $payload): string
    {
        $secret = config('services.firma_plus.webhook_secret', 'test_secret');
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Crear una postulación con usuario asociado
     */
    protected function crearPostulacionConUsuario(): Postulacion
    {
        $user = User::factory()->create();
        return Postulacion::factory()->create([
            'username' => $user->username,
            'estado' => 'iniciada'
        ]);
    }

    /**
     * Test: Webhook debe rechazar requests sin firma
     */
    public function test_webhook_rechaza_sin_firma(): void
    {
        $payload = [
            'transaccion_id' => 'test-123',
            'solicitud_id' => Str::uuid()->toString(),
            'estado' => 'FIRMADO',
            'firmantes_completados' => 2
        ];

        $response = $this->postJson('/api/firmas/webhook', $payload);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => 'Missing signature header (X-Signature or X-Webhook-Signature)'
        ]);
    }

    /**
     * Test: Webhook debe rechazar requests con firma inválida
     */
    public function test_webhook_rechaza_firma_invalida(): void
    {
        $payload = [
            'transaccion_id' => 'test-123',
            'solicitud_id' => Str::uuid()->toString(),
            'estado' => 'FIRMADO',
            'firmantes_completados' => 2
        ];

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => 'firma_invalida'
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => 'Invalid signature'
        ]);
    }

    /**
     * Test: Webhook debe rechazar estado inválido
     */
    public function test_webhook_rechaza_estado_invalido(): void
    {
        $solicitud = $this->crearPostulacionConUsuario();

        $payload = [
            'transaccion_id' => 'test-123',
            'solicitud_id' => $solicitud->id,
            'estado' => 'ESTADO_INVALIDO', // Estado no válido
            'firmantes_completados' => 2
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test: Webhook debe aceptar firma válida y procesar correctamente
     */
    public function test_webhook_acepta_firma_valida_y_procesa(): void
    {
        // Crear solicitud de prueba
        $solicitud = $this->crearPostulacionConUsuario();

        $payload = [
            'transaccion_id' => 'test-123',
            'solicitud_id' => $solicitud->id,
            'estado' => 'FIRMADO',
            'firmantes_completados' => 2,
            'firmantes' => [
                [
                    'nombre' => 'Juan Pérez',
                    'documento' => '12345678',
                    'firma' => 'base64_firma_data'
                ],
                [
                    'nombre' => 'María García',
                    'documento' => '87654321',
                    'firma' => 'base64_firma_data_2'
                ]
            ],
            'pdf_url' => 'https://firma-plus.com/pdf/test-123'
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Webhook procesado correctamente'
        ]);

        // Verificar que el estado fue actualizado
        $solicitud->refresh();
        $this->assertEquals('aprobada', $solicitud->estado);
    }

    /**
     * Test: Webhook debe actualizar estado a RECHAZADO
     */
    public function test_webhook_actualiza_estado_rechazado(): void
    {
        $solicitud = $this->crearPostulacionConUsuario();

        $payload = [
            'transaccion_id' => 'test-456',
            'solicitud_id' => $solicitud->id,
            'estado' => 'RECHAZADO',
            'firmantes_completados' => 0,
            'motivo_rechazo' => 'Documento inválido'
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Webhook procesado correctamente'
        ]);

        $solicitud->refresh();
        $this->assertEquals('rechazada', $solicitud->estado);
    }

    /**
     * Test: Webhook debe actualizar estado a EXPIRADO
     */
    public function test_webhook_actualiza_estado_expirado(): void
    {
        $solicitud = $this->crearPostulacionConUsuario();

        $payload = [
            'transaccion_id' => 'test-789',
            'solicitud_id' => $solicitud->id,
            'estado' => 'EXPIRADO',
            'firmantes_completados' => 0,
            'motivo_expiracion' => 'Tiempo límite excedido'
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Webhook procesado correctamente'
        ]);

        $solicitud->refresh();
        $this->assertEquals('rechazada', $solicitud->estado);
    }

    /**
     * Test: Webhook debe rechazar solicitud no encontrada
     */
    public function test_webhook_rechaza_solicitud_no_encontrada(): void
    {
        $payload = [
            'transaccion_id' => 'test-not-found',
            'solicitud_id' => Str::uuid()->toString(),
            'estado' => 'FIRMADO',
            'firmantes_completados' => 2
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(404);
        $response->assertJsonFragment([
            'success' => false
        ]);
    }

    /**
     * Test: Webhook debe rechazar UUID inválido
     */
    public function test_webhook_rechaza_uuid_invalido(): void
    {
        $payload = [
            'transaccion_id' => 'test-123',
            'solicitud_id' => 'not-a-valid-uuid',
            'estado' => 'FIRMADO',
            'firmantes_completados' => 2
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(400);
    }

    /**
     * Test: Webhook debe actualizar timeline
     */
    public function test_webhook_actualiza_timeline(): void
    {
        $solicitud = $this->crearPostulacionConUsuario();

        $payload = [
            'transaccion_id' => 'test-timeline',
            'solicitud_id' => $solicitud->id,
            'estado' => 'FIRMADO',
            'firmantes_completados' => 2
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $solicitud->refresh();
        $this->assertEquals('aprobada', $solicitud->estado);
    }

    /**
     * Test: Webhook debe manejar header X-Webhook-Signature alternativo
     */
    public function test_webhook_acepta_header_alternativo(): void
    {
        $solicitud = $this->crearPostulacionConUsuario();

        $payload = [
            'transaccion_id' => 'test-alt-header',
            'solicitud_id' => $solicitud->id,
            'estado' => 'FIRMADO',
            'firmantes_completados' => 2
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Webhook-Signature' => $signature
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Webhook procesado correctamente'
        ]);
    }
}
