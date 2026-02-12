<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\GeneradorPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GeneradorPdfApiTest extends TestCase
{
    use RefreshDatabase;

    protected GeneradorPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeneradorPdfService();
    }

    /**
     * Test básico de conexión con la API Flask
     */
    public function test_verificar_salud_api_flask(): void
    {
        // Mock de respuesta health check
        Http::fake([
            $this->service->getBaseUrl() . '/api/health' => Http::response([
                'status' => 'healthy',
                'service' => 'pdf-generator'
            ], 200)
        ]);

        $resultado = $this->service->verificarSalud();

        $this->assertTrue($resultado['healthy']);
        $this->assertEquals(200, $resultado['status']);
        $this->assertEquals('pdf-generator', $resultado['response']['service']);
    }

    /**
     * Test de generación de PDF exitosa
     */
    public function test_generar_pdf_creditos_exitoso(): void
    {
        // Mock de respuesta exitosa de Flask API
        Http::fake([
            $this->service->getBaseUrl() . '/api/creditos/generate-pdf' => Http::response([
                'success' => true,
                'message' => 'PDF generado exitosamente',
                'data' => [
                    'pdf_path' => '/tmp/solicitud_123.pdf',
                    'pdf_filename' => 'solicitud_123.pdf'
                ]
            ], 200)
        ]);

        $data = [
            'solicitud_data' => [
                'numero_solicitud' => 'SOL-2024-001',
                'monto_solicitado' => 50000,
                'plazo_meses' => 12,
                'tasa_interes' => 8.5
            ],
            'trabajador_data' => [
                'nombre_completo' => 'Juan Pérez',
                'numero_documento' => '12345678',
                'email' => 'juan.perez@email.com'
            ],
            'incluir_convenio' => true,
            'incluir_firmantes' => true
        ];

        $resultado = $this->service->generarPdfCreditos($data);

        $this->assertTrue($resultado['success']);
        $this->assertArrayHasKey('data', $resultado);
        $this->assertEquals('solicitud_123.pdf', $resultado['data']['pdf_filename']);

        // Verificar que se llamó a la API con los datos correctos
        Http::assertSent(function ($request) use ($data) {
            return $request->url() === $this->service->getBaseUrl() . '/api/creditos/generate-pdf' &&
                $request->method() === 'POST' &&
                $request->data()['solicitud_data']['numero_solicitud'] === 'SOL-2024-001' &&
                $request->data()['trabajador_data']['nombre_completo'] === 'Juan Pérez';
        });
    }

    /**
     * Test de error en la API Flask
     */
    public function test_generar_pdf_con_error_api(): void
    {
        // Mock de respuesta de error
        Http::fake([
            $this->service->getBaseUrl() . '/api/creditos/generate-pdf' => Http::response([
                'success' => false,
                'error' => 'Error en API externa',
                'status' => 500
            ], 500)
        ]);

        $data = [
            'solicitud_data' => [
                'numero_solicitud' => 'SOL-2024-001'
            ]
        ];

        $resultado = $this->service->generarPdfCreditos($data);

        $this->assertFalse($resultado['success']);
        $this->assertEquals(500, $resultado['status']);
        $this->assertEquals('Error en API externa', $resultado['error']);
    }

    /**
     * Test de timeout de conexión
     */
    public function test_generar_pdf_con_timeout(): void
    {
        // Mock de timeout - simulamos una excepción de timeout
        Http::fake([
            'http://localhost:8080/api/creditos/generate-pdf' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Timeout');
            }
        ]);

        $data = [
            'solicitud_data' => [
                'numero_solicitud' => 'SOL-2024-001'
            ]
        ];

        $resultado = $this->service->generarPdfCreditos($data);

        $this->assertFalse($resultado['success']);
        $this->assertEquals('Error en API externa', $resultado['error']);
        $this->assertArrayHasKey('status', $resultado);
    }

    /**
     * Test de validación de datos requeridos
     */
    public function test_generar_pdf_sin_datos_requeridos(): void
    {
        Http::fake([
            $this->service->getBaseUrl() . '/api/creditos/generate-pdf' => Http::response([
                'error' => 'Campo requerido faltante: solicitud_data',
                'details' => ['field' => 'solicitud_data']
            ], 400)
        ]);

        $data = []; // Sin datos requeridos

        $resultado = $this->service->generarPdfCreditos($data);

        $this->assertFalse($resultado['success']);
        $this->assertEquals(400, $resultado['status']);
        $this->assertStringContainsString('Campo requerido faltante', $resultado['response']);
    }

    /**
     * Test de autenticación básica
     */
    public function test_generar_pdf_con_autenticacion(): void
    {
        // Mock que verifica autenticación
        Http::fake([
            $this->service->getBaseUrl() . '/api/creditos/generate-pdf' => Http::response([
                'success' => true,
                'message' => 'PDF generado exitosamente'
            ], 200)
        ]);

        // Configurar credenciales en el entorno de prueba
        config([
            'services.generador_pdf_api.user' => 'test_user',
            'services.generador_pdf_api.password' => 'test_pass'
        ]);

        $service = new GeneradorPdfService();

        $data = ['solicitud_data' => ['numero_solicitud' => 'SOL-2024-001']];
        $service->generarPdfCreditos($data);

        // Verificar que se envió con autenticación básica
        Http::assertSent(function ($request) {
            $auth = $request->header('Authorization')[0] ?? '';
            return str_contains($auth, 'Basic ') &&
                str_contains(base64_decode(str_replace('Basic ', '', $auth)), 'test_user:test_pass');
        });
    }

    /**
     * Test de endpoint de salud no disponible
     */
    public function test_verificar_salud_api_no_disponible(): void
    {
        // Mock de API no disponible
        Http::fake([
            $this->service->getBaseUrl() . '/api/health' => Http::response('', 500)
        ]);

        $resultado = $this->service->verificarSalud();

        $this->assertFalse($resultado['healthy']);
    }

    /**
     * Test de obtener URL base
     */
    public function test_obtener_url_base(): void
    {
        $url = $this->service->getBaseUrl();
        $this->assertIsString($url);
        $this->assertNotEmpty($url);
        // Verificar que es una URL válida
        $this->assertStringContainsString('http', $url);
    }

    /**
     * Test de datos completos de solicitud
     */
    public function test_generar_pdf_con_datos_completos(): void
    {
        Http::fake([
            $this->service->getBaseUrl() . '/api/creditos/generate-pdf' => Http::response([
                'success' => true,
                'data' => [
                    'pdf_path' => '/tmp/solicitud_completa.pdf',
                    'pdf_filename' => 'solicitud_completa.pdf'
                ]
            ], 200)
        ]);

        $data = [
            'solicitud_data' => [
                'numero_solicitud' => 'SOL-2024-001',
                'monto_solicitado' => 100000,
                'plazo_meses' => 24,
                'tasa_interes' => 7.5,
                'destino_credito' => 'Capital de trabajo',
                'descripcion' => 'Préstamo para expansión'
            ],
            'trabajador_data' => [
                'nombre_completo' => 'María García',
                'numero_documento' => '87654321',
                'email' => 'maria.garcia@email.com',
                'telefono' => '3001234567',
                'direccion' => 'Calle 45 #67-89'
            ],
            'convenio_data' => [
                'nombre_convenio' => 'Convenio Especial',
                'tasa_descuento' => 1.5,
                'codigo_convenio' => 'CONV-001'
            ],
            'firmantes_data' => [
                [
                    'nombre_completo' => 'Firmante 1',
                    'numero_documento' => '11111111',
                    'rol' => 'Representante Legal'
                ],
                [
                    'nombre_completo' => 'Firmante 2',
                    'numero_documento' => '22222222',
                    'rol' => 'Testigo'
                ]
            ],
            'incluir_convenio' => true,
            'incluir_firmantes' => true
        ];

        $resultado = $this->service->generarPdfCreditos($data);

        $this->assertTrue($resultado['success']);
        $this->assertArrayHasKey('data', $resultado);

        // Verificar que todos los datos fueron enviados
        Http::assertSent(function ($request) use ($data) {
            $sentData = $request->data();
            return $sentData['solicitud_data']['numero_solicitud'] === 'SOL-2024-001' &&
                $sentData['trabajador_data']['nombre_completo'] === 'María García' &&
                count($sentData['firmantes_data']) === 2 &&
                $sentData['incluir_convenio'] === true;
        });
    }
}
