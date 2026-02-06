<?php

namespace Tests\Feature\Webhooks;

use Tests\TestCase;
use App\Models\Postulacion;
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
            'X-Signature' => 'firma_invalida_12345'
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => 'Invalid signature'
        ]);
    }

    /**
     * Test: Webhook debe rechazar payload con campos faltantes
     */
    public function test_webhook_rechaza_payload_incompleto(): void
    {
        $payload = [
            'transaccion_id' => 'test-123',
            // Falta solicitud_id
            'estado' => 'FIRMADO'
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'success' => false
        ]);
    }

    /**
     * Test: Webhook debe rechazar estado inválido
     */
    public function test_webhook_rechaza_estado_invalido(): void
    {
        $solicitud = Postulacion::factory()->create([
            'estado' => 'PENDIENTE_FIRMADO',
            'proceso_firmado' => [
                'transaccion_id' => 'test-123',
                'estado' => 'PENDIENTE_FIRMADO'
            ]
        ]);

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
        $solicitud = Postulacion::factory()->create([
            'estado' => 'PENDIENTE_FIRMADO',
            'proceso_firmado' => [
                'transaccion_id' => 'test-123',
                'estado' => 'PENDIENTE_FIRMADO'
            ],
            'pdf_generado' => [
                'path' => '/tmp/test.pdf'
            ]
        ]);

        $payload = [
            'transaccion_id' => 'test-123',
            'solicitud_id' => $solicitud->id,
            'estado' => 'FIRMADO',
            'firmantes_completados' => 2,
            'firmantes' => [
                [
                    'nombre' => 'Juan Pérez',
                    'email' => 'juan@example.com',
                    'firmado' => true,
                    'fecha_firma' => '2024-02-04T20:30:00Z'
                ],
                [
                    'nombre' => 'Empresa XYZ',
                    'email' => 'empresa@xyz.com',
                    'firmado' => true,
                    'fecha_firma' => '2024-02-04T20:35:00Z'
                ]
            ]
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'procesado' => true,
                'solicitud_id' => $solicitud->id,
                'estado' => 'FIRMADO'
            ]
        ]);

        // Verificar que la solicitud se actualizó
        $solicitud->refresh();
        $this->assertEquals('FIRMADO', $solicitud->estado);
        $this->assertEquals('FIRMADO', $solicitud->proceso_firmado['estado']);
        $this->assertEquals(2, $solicitud->proceso_firmado['firmantes_completados']);
    }

    /**
     * Test: Webhook debe actualizar estado a RECHAZADO
     */
    public function test_webhook_actualiza_estado_rechazado(): void
    {
        $solicitud = Postulacion::factory()->create([
            'estado' => 'PENDIENTE_FIRMADO',
            'proceso_firmado' => [
                'transaccion_id' => 'test-456',
                'estado' => 'PENDIENTE_FIRMADO'
            ]
        ]);

        $payload = [
            'transaccion_id' => 'test-456',
            'solicitud_id' => $solicitud->id,
            'estado' => 'RECHAZADO',
            'firmantes_completados' => 0
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(200);

        $solicitud->refresh();
        $this->assertEquals('RECHAZADO', $solicitud->estado);
    }

    /**
     * Test: Webhook debe actualizar estado a EXPIRADO
     */
    public function test_webhook_actualiza_estado_expirado(): void
    {
        $solicitud = Postulacion::factory()->create([
            'estado' => 'PENDIENTE_FIRMADO',
            'proceso_firmado' => [
                'transaccion_id' => 'test-789',
                'estado' => 'PENDIENTE_FIRMADO'
            ]
        ]);

        $payload = [
            'transaccion_id' => 'test-789',
            'solicitud_id' => $solicitud->id,
            'estado' => 'EXPIRADO',
            'firmantes_completados' => 0
        ];

        $signature = $this->generarFirmaHMAC($payload);

        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Signature' => $signature
        ]);

        $response->assertStatus(200);

        $solicitud->refresh();
        $this->assertEquals('EXPIRADO', $solicitud->estado);
    }

    /**
     * Test: Webhook debe rechazar solicitud no encontrada
     */
    public function test_webhook_rechaza_solicitud_no_encontrada(): void
    {
        $solicitudIdInexistente = Str::uuid()->toString();

        $payload = [
            'transaccion_id' => 'test-999',
            'solicitud_id' => $solicitudIdInexistente,
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
     * Test: Webhook debe actualizar timeline correctamente
     */
    public function test_webhook_actualiza_timeline(): void
    {
        $solicitud = Postulacion::factory()->create([
            'estado' => 'PENDIENTE_FIRMADO',
            'proceso_firmado' => [
                'transaccion_id' => 'test-timeline',
                'estado' => 'PENDIENTE_FIRMADO'
            ],
            'timeline' => []
        ]);

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
        
        $this->assertNotEmpty($solicitud->timeline);
        $this->assertCount(1, $solicitud->timeline);
        
        $ultimoEvento = end($solicitud->timeline);
        $this->assertEquals('WEBHOOK_FIRMADO', $ultimoEvento['evento']);
        $this->assertEquals('SYSTEM_FIRMAPLUS', $ultimoEvento['usuario']);
    }

    /**
     * Test: Webhook debe manejar header X-Webhook-Signature alternativo
     */
    public function test_webhook_acepta_header_alternativo(): void
    {
        $solicitud = Postulacion::factory()->create([
            'estado' => 'PENDIENTE_FIRMADO',
            'proceso_firmado' => [
                'transaccion_id' => 'test-alt-header',
                'estado' => 'PENDIENTE_FIRMADO'
            ]
        ]);

        $payload = [
            'transaccion_id' => 'test-alt-header',
            'solicitud_id' => $solicitud->id,
            'estado' => 'FIRMADO',
            'firmantes_completados' => 1
        ];

        $signature = $this->generarFirmaHMAC($payload);

        // Usar X-Webhook-Signature en lugar de X-Signature
        $response = $this->postJson('/api/firmas/webhook', $payload, [
            'X-Webhook-Signature' => $signature
        ]);

        $response->assertStatus(200);
    }
}
