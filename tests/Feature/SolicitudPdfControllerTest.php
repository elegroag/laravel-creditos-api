<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SolicitudService;
use App\Services\GeneradorPdfService;
use App\Models\SolicitudCredito;
use App\Models\SolicitudSolicitante;
use App\Models\SolicitudPayload;
use App\Models\FirmanteSolicitud;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SolicitudPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected SolicitudService $solicitudService;
    protected GeneradorPdfService $generadorPdfService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->solicitudService = $this->createMock(SolicitudService::class);
        $this->generadorPdfService = $this->createMock(GeneradorPdfService::class);
    }

    /**
     * Test de generación de PDF con datos completos
     */
    public function test_generar_pdf_con_datos_completos(): void
    {
        // Mock de la API Flask
        Http::fake([
            'http://localhost:8080/api/creditos/generate-pdf' => Http::response([
                'success' => true,
                'message' => 'PDF generado exitosamente',
                'data' => [
                    'pdf_path' => '/tmp/solicitud_TEST001.pdf',
                    'pdf_filename' => 'solicitud_TEST001.pdf'
                ]
            ], 200)
        ]);

        // Crear datos de prueba
        $solicitud = $this->createSolicitudWithRelations();

        // Mock del servicio de solicitudes
        $this->solicitudService->method('getById')
            ->with('TEST001')
            ->willReturn($solicitud);

        // Mock del servicio generador PDF
        $this->generadorPdfService->method('generarPdfCreditos')
            ->willReturn([
                'success' => true,
                'status' => 200,
                'data' => [
                    'data' => [
                        'pdf_path' => '/tmp/solicitud_TEST001.pdf',
                        'pdf_filename' => 'solicitud_TEST001.pdf'
                    ]
                ]
            ]);

        // Crear instancia del controller con los mocks
        $controller = new \App\Http\Controllers\Api\SolicitudPdfController(
            $this->solicitudService,
            $this->generadorPdfService
        );

        // Usar reflexión para acceder al método privado
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generarPdfConScript');
        $method->setAccessible(true);

        // Ejecutar el método
        $resultado = $method->invoke($controller, 'TEST001');

        // Verificar resultado
        $this->assertTrue($resultado['success']);
        $this->assertArrayHasKey('data', $resultado);
        $this->assertEquals('solicitud_TEST001.pdf', $resultado['data']['pdf_filename']);
    }

    /**
     * Test de generación de PDF con solicitud no encontrada
     */
    public function test_generar_pdf_con_solicitud_no_encontrada(): void
    {
        // Mock del servicio de solicitudes retornando null
        $this->solicitudService->method('getById')
            ->with('NONEXISTENT')
            ->willReturn(null);

        // Crear instancia del controller
        $controller = new \App\Http\Controllers\Api\SolicitudPdfController(
            $this->solicitudService,
            $this->generadorPdfService
        );

        // Usar reflexión para acceder al método privado
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generarPdfConScript');
        $method->setAccessible(true);

        // Ejecutar el método
        $resultado = $method->invoke($controller, 'NONEXISTENT');

        // Verificar resultado
        $this->assertFalse($resultado['success']);
        $this->assertEquals('Solicitud no encontrada', $resultado['error']);
    }

    /**
     * Test de generación de PDF con error en API Flask
     */
    public function test_generar_pdf_con_error_api_flask(): void
    {
        // Mock de error en API Flask
        Http::fake([
            'http://localhost:8080/api/creditos/generate-pdf' => Http::response([
                'error' => 'Template no encontrado'
            ], 404)
        ]);

        // Crear solicitud básica
        $solicitud = new SolicitudCredito([
            'numero_solicitud' => 'TEST002',
            'monto_solicitado' => 50000,
            'plazo_meses' => 12,
            'tasa_interes' => 8.0
        ]);

        // Mock del servicio de solicitudes
        $this->solicitudService->method('getById')
            ->with('TEST002')
            ->willReturn($solicitud);

        // Mock del servicio generador PDF retornando error
        $this->generadorPdfService->method('generarPdfCreditos')
            ->willReturn([
                'success' => false,
                'error' => 'Template no encontrado',
                'status' => 404,
                'response' => '{"error": "Template no encontrado"}'
            ]);

        // Crear instancia del controller
        $controller = new \App\Http\Controllers\Api\SolicitudPdfController(
            $this->solicitudService,
            $this->generadorPdfService
        );

        // Usar reflexión para acceder al método privado
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generarPdfConScript');
        $method->setAccessible(true);

        // Ejecutar el método
        $resultado = $method->invoke($controller, 'TEST002');

        // Verificar resultado
        $this->assertFalse($resultado['success']);
        $this->assertStringContainsString('Error', $resultado['error']);
        $this->assertArrayHasKey('details', $resultado);
    }

    /**
     * Test de mapeo de datos con firmantes y convenio
     */
    public function test_mapeo_datos_con_firmantes_y_convenio(): void
    {
        // Mock de API Flask exitosa
        Http::fake([
            'http://localhost:8080/api/creditos/generate-pdf' => Http::response([
                'success' => true,
                'data' => [
                    'pdf_filename' => 'solicitud_full.pdf'
                ]
            ], 200)
        ]);

        // Crear solicitud con relaciones
        $solicitud = $this->createSolicitudWithRelations(true, true);

        // Mock del servicio de solicitudes
        $this->solicitudService->method('getById')
            ->with('FULL001')
            ->willReturn($solicitud);

        // Mock del servicio generador PDF para capturar los datos enviados
        $capturedData = null;
        $this->generadorPdfService->method('generarPdfCreditos')
            ->willReturnCallback(function ($data) use (&$capturedData) {
                $capturedData = $data;
                return [
                    'success' => true,
                    'status' => 200,
                    'data' => ['data' => ['pdf_filename' => 'solicitud_full.pdf']]
                ];
            });

        // Crear instancia del controller
        $controller = new \App\Http\Controllers\Api\SolicitudPdfController(
            $this->solicitudService,
            $this->generadorPdfService
        );

        // Usar reflexión para acceder al método privado
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('generarPdfConScript');
        $method->setAccessible(true);

        // Ejecutar el método
        $resultado = $method->invoke($controller, 'FULL001');

        // Verificar resultado
        $this->assertTrue($resultado['success']);

        // Verificar datos enviados a la API
        $this->assertNotNull($capturedData);
        $this->assertEquals('FULL001', $capturedData['solicitud_id']);
        $this->assertTrue($capturedData['incluir_convenio']);
        $this->assertTrue($capturedData['incluir_firmantes']);
        $this->assertNotEmpty($capturedData['convenio_data']);
        $this->assertNotEmpty($capturedData['firmantes_data']);
        $this->assertCount(2, $capturedData['firmantes_data']);
    }

    /**
     * Crea una solicitud con relaciones para testing
     */
    private function createSolicitudWithRelations(bool $withFirmantes = false, bool $withConvenio = false): SolicitudCredito
    {
        $solicitud = new SolicitudCredito([
            'numero_solicitud' => $withFirmantes ? 'FULL001' : 'TEST001',
            'owner_username' => 'test_user',
            'monto_solicitado' => 75000,
            'monto_aprobado' => 75000,
            'plazo_meses' => 24,
            'tasa_interes' => 7.5,
            'destino_credito' => 'Capital de trabajo',
            'descripcion' => 'Test solicitud',
            'estado' => 'APROBADO'
        ]);

        // Mock de solicitante
        $solicitante = new \stdClass();
        $solicitante->nombre_completo = 'Juan Pérez';
        $solicitante->tipo_documento = 'CC';
        $solicitante->numero_documento = '12345678';
        $solicitante->email = 'juan.perez@email.com';
        $solicitante->telefono = '3001234567';
        $solicitante->direccion = 'Calle 123 #45-67';
        $solicitante->ciudad = 'Bogotá';
        $solicitante->departamento = 'Cundinamarca';
        $solicitante->cargo = 'Gerente';
        $solicitante->empresa = 'Empresa Test';
        $solicitante->salario = 5000000;
        $solicitante->tipo_contrato = 'Indefinido';

        if ($withConvenio) {
            $solicitante->convenio_id = 'CONV001';
            $convenio = new \stdClass();
            $convenio->nombre = 'Convenio Especial';
            $convenio->codigo = 'CONV001';
            $convenio->tasa_descuento = 1.5;
            $convenio->empresa = 'Empresa Convenio';
            $solicitante->convenio = $convenio;
        }

        // Mock de payload
        $payload = new \stdClass();
        $payload->payload = json_encode([
            'campo_adicional_1' => 'valor_1',
            'campo_adicional_2' => 'valor_2'
        ]);

        // Mock de firmantes
        $firmantes = collect([]);
        if ($withFirmantes) {
            $firmante1 = new \stdClass();
            $firmante1->nombre_completo = 'Firmante Principal';
            $firmante1->numero_documento = '87654321';
            $firmante1->email = 'firmante1@email.com';
            $firmante1->rol = 'Representante Legal';
            $firmante1->tipo = 'Principal';
            $firmante1->orden = 1;

            $firmante2 = new \stdClass();
            $firmante2->nombre_completo = 'Firmante Secundario';
            $firmante2->numero_documento = '98765432';
            $firmante2->email = 'firmante2@email.com';
            $firmante2->rol = 'Testigo';
            $firmante2->tipo = 'Secundario';
            $firmante2->orden = 2;

            $firmantes = collect([$firmante1, $firmante2]);
        }

        // Mock de relaciones usando métodos mágicos
        $solicitud->solicitante = $solicitante;
        $solicitud->payload = $payload;
        $solicitud->firmantes = $firmantes;

        // Mock de timestamps
        $solicitud->created_at = now();
        $solicitud->updated_at = now();

        return $solicitud;
    }
}
