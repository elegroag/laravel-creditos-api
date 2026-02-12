<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\SolicitudService;
use App\Services\GeneradorPdfService;
use App\Services\TrabajadorService;
use App\Services\PdfGenerationService;
use App\Models\SolicitudCredito;
use App\Models\SolicitudSolicitante;
use App\Models\SolicitudPayload;
use App\Models\User;
use App\Models\EstadoSolicitud;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SolicitudPdfControllerTest extends TestCase
{
    use RefreshDatabase;

    protected SolicitudService $solicitudService;
    protected GeneradorPdfService $generadorPdfService;
    protected TrabajadorService $trabajadorService;
    protected PdfGenerationService $pdfGenerationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->solicitudService = $this->createMock(SolicitudService::class);
        $this->generadorPdfService = $this->createMock(GeneradorPdfService::class);
        $this->trabajadorService = $this->createMock(TrabajadorService::class);
        $this->pdfGenerationService = $this->createMock(PdfGenerationService::class);
    }

    /**
     * Test de creación del controller con dependencias correctas
     */
    public function test_controller_se_crea_con_dependencias_correctas(): void
    {
        // Verificar que el controller se puede crear con todas las dependencias
        $controller = new \App\Http\Controllers\Api\SolicitudPdfController(
            $this->solicitudService,
            $this->generadorPdfService,
            $this->trabajadorService,
            $this->pdfGenerationService
        );

        $this->assertInstanceOf(\App\Http\Controllers\Api\SolicitudPdfController::class, $controller);
    }

    /**
     * Test de estructura básica del controller
     */
    public function test_controller_tiene_metodos_esperados(): void
    {
        $controller = new \App\Http\Controllers\Api\SolicitudPdfController(
            $this->solicitudService,
            $this->generadorPdfService,
            $this->trabajadorService,
            $this->pdfGenerationService
        );

        // Verificar que los métodos públicos existen
        $this->assertTrue(method_exists($controller, 'generarPdfSolicitud'));
        $this->assertTrue(method_exists($controller, 'descargarPdfSolicitud'));
        $this->assertTrue(method_exists($controller, 'verificarEstadoPdf'));
        $this->assertTrue(method_exists($controller, 'eliminarPdfSolicitud'));
        $this->assertTrue(method_exists($controller, 'obtenerEstadisticasPdf'));
    }

    /**
     * Test de creación de datos de prueba
     */
    public function test_creacion_datos_de_prueba(): void
    {
        $solicitud = $this->createSolicitudWithRelations();
        
        $this->assertInstanceOf(SolicitudCredito::class, $solicitud);
        $this->assertEquals('TEST001', $solicitud->numero_solicitud);
        $this->assertEquals(50000, $solicitud->valor_solicitud);
        $this->assertEquals('PENDIENTE', $solicitud->estado);
        
        // Verificar relaciones
        $this->assertInstanceOf(SolicitudSolicitante::class, $solicitud->solicitante);
        $this->assertInstanceOf(SolicitudPayload::class, $solicitud->payload);
    }

    /**
     * Test de mock de servicios
     */
    public function test_servicios_se_pueden_mokear(): void
    {
        // Verificar que los mocks se crean correctamente
        $this->assertInstanceOf(\PHPUnit\Framework\MockObject\MockObject::class, $this->solicitudService);
        $this->assertInstanceOf(\PHPUnit\Framework\MockObject\MockObject::class, $this->generadorPdfService);
        $this->assertInstanceOf(\PHPUnit\Framework\MockObject\MockObject::class, $this->trabajadorService);
        $this->assertInstanceOf(\PHPUnit\Framework\MockObject\MockObject::class, $this->pdfGenerationService);
    }

    /**
     * Helper para crear una solicitud con relaciones
     */
    protected function createSolicitudWithRelations(): SolicitudCredito
    {
        // Crear usuario primero para foreign key
        $user = User::factory()->create(['username' => 'test_user']);
        
        // Crear estado para foreign key
        $estado = EstadoSolicitud::create([
            'id' => 'PENDIENTE',
            'nombre' => 'PENDIENTE',
            'descripcion' => 'Estado pendiente',
            'orden' => 1
        ]);

        // Crear solicitud básica
        $solicitud = SolicitudCredito::create([
            'numero_solicitud' => 'TEST001',
            'owner_username' => $user->username,
            'valor_solicitud' => 50000,
            'plazo_meses' => 12,
            'tasa_interes' => 8.5,
            'estado' => 'PENDIENTE',
            'producto_tipo' => 'PERSONAL',
            'tipo_credito' => '001',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Crear solicitante
        SolicitudSolicitante::create([
            'solicitud_id' => $solicitud->numero_solicitud,
            'tipo_documento' => '1',
            'numero_documento' => '12345678',
            'nombres' => 'Test',
            'apellidos' => 'User',
            'email' => 'test@example.com',
            'telefono_movil' => '3001234567',
            'direccion' => 'Test Address',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Crear payload
        SolicitudPayload::create([
            'solicitud_id' => $solicitud->numero_solicitud,
            'informacion_laboral' => json_encode([
                'empresa' => 'Test Company',
                'cargo' => 'Test Position',
                'salario' => 2000000,
                'antiguedad' => 2
            ]),
            'ingresos_descuentos' => json_encode([
                'ingresos_mensuales' => 2000000,
                'egresos_mensuales' => 500000,
                'otros_ingresos' => 0
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $solicitud;
    }
}
