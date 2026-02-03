<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\GeneradorPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GeneradorPdfApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected GeneradorPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeneradorPdfService();
    }

    /**
     * Test de integración real con la API Flask (solo si está disponible)
     */
    public function test_integracion_real_api_flask(): void
    {
        // Este test solo se ejecuta si la API Flask está corriendo
        $healthCheck = $this->service->verificarSalud();

        if (!$healthCheck['healthy']) {
            $this->markTestSkipped('API Flask no está disponible para test de integración');
        }

        // Datos de prueba reales
        $data = [
            'solicitud_id' => 'TEST-2024-001',
            'solicitud_data' => [
                'numero_solicitud' => 'TEST-2024-001',
                'monto_solicitado' => 25000,
                'plazo_meses' => 18,
                'tasa_interes' => 8.0,
                'destino_credito' => 'Capital de trabajo',
                'descripcion' => 'Test de integración'
            ],
            'trabajador_data' => [
                'nombre_completo' => 'Test Integration',
                'numero_documento' => '99999999',
                'email' => 'test@integration.com'
            ],
            'incluir_convenio' => false,
            'incluir_firmantes' => false
        ];

        $resultado = $this->service->generarPdfCreditos($data);

        $this->assertTrue($resultado['success']);
        $this->assertEquals(200, $resultado['status']);
        $this->assertArrayHasKey('data', $resultado);
        $this->assertArrayHasKey('pdf_filename', $resultado['data']['data']);
    }

    /**
     * Test de integración con datos completos
     */
    public function test_integracion_datos_completos(): void
    {
        $healthCheck = $this->service->verificarSalud();

        if (!$healthCheck['healthy']) {
            $this->markTestSkipped('API Flask no está disponible para test de integración');
        }

        $data = [
            'solicitud_id' => 'FULL-2024-001',
            'solicitud_data' => [
                'numero_solicitud' => 'FULL-2024-001',
                'monto_solicitado' => 75000,
                'plazo_meses' => 36,
                'tasa_interes' => 7.5,
                'destino_credito' => 'Expansión',
                'descripcion' => 'Test completo con todos los datos'
            ],
            'trabajador_data' => [
                'nombre_completo' => 'Usuario Completo',
                'numero_documento' => '88888888',
                'email' => 'completo@test.com',
                'telefono' => '3008888888',
                'direccion' => 'Calle de prueba 123'
            ],
            'convenio_data' => [
                'nombre_convenio' => 'Convenio Test',
                'tasa_descuento' => 1.0,
                'codigo_convenio' => 'TEST-001'
            ],
            'firmantes_data' => [
                [
                    'nombre_completo' => 'Firmante Principal',
                    'numero_documento' => '77777777',
                    'rol' => 'Representante Legal',
                    'email' => 'firmante@test.com'
                ],
                [
                    'nombre_completo' => 'Testigo Test',
                    'numero_documento' => '66666666',
                    'rol' => 'Testigo',
                    'email' => 'testigo@test.com'
                ]
            ],
            'incluir_convenio' => true,
            'incluir_firmantes' => true
        ];

        $resultado = $this->service->generarPdfCreditos($data);

        $this->assertTrue($resultado['success']);
        $this->assertEquals(200, $resultado['status']);
        $this->assertArrayHasKey('pdf_filename', $resultado['data']['data']);
    }

    /**
     * Test de error de validación real
     */
    public function test_integracion_error_validacion_real(): void
    {
        $healthCheck = $this->service->verificarSalud();

        if (!$healthCheck['healthy']) {
            $this->markTestSkipped('API Flask no está disponible para test de integración');
        }

        // Enviar datos inválidos para probar validación
        $data = [
            'solicitud_id' => 'INVALID-TEST',
            'solicitud_data' => [], // Vacío para provocar error
        ];

        $resultado = $this->service->generarPdfCreditos($data);

        $this->assertFalse($resultado['success']);
        $this->assertContains($resultado['status'], [400, 422, 500]);
    }

    /**
     * Test de conexión con API Flask no iniciada
     */
    public function test_api_flask_no_disponible(): void
    {
        // Configurar URL incorrecta para simular API no disponible
        config(['services.generador_pdf_api.url' => 'http://localhost:9999']);

        $service = new GeneradorPdfService();
        $healthCheck = $service->verificarSalud();

        $this->assertFalse($healthCheck['healthy']);
        $this->assertArrayHasKey('error', $healthCheck);
    }

    /**
     * Test de timeout real
     */
    public function test_timeout_real(): void
    {
        // Configurar timeout muy corto
        config(['services.generador_pdf_api.timeout' => 1]);

        $service = new GeneradorPdfService();

        $data = [
            'solicitud_id' => 'TIMEOUT-TEST',
            'solicitud_data' => [
                'numero_solicitud' => 'TIMEOUT-TEST',
                'monto_solicitado' => 1000
            ]
        ];

        $resultado = $service->generarPdfCreditos($data);

        // Si la API está disponible pero lenta, podría tener timeout
        // O si es muy rápida, simplemente procesará la solicitud
        if (!$resultado['success']) {
            $this->assertContains($resultado['error'], [
                'Error de conexión con API externa',
                'Error interno'
            ]);
        } else {
            // Si fue exitoso, verificamos que sea válido
            $this->assertEquals(200, $resultado['status']);
        }
    }
}
