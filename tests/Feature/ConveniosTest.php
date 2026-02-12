<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\EmpresaConvenio;
use App\Services\ConvenioValidationService;
use App\Services\TrabajadorService;
use App\Services\ExternalApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConveniosTest extends TestCase
{
    use RefreshDatabase;

    protected ConvenioValidationService $convenioService;
    protected TrabajadorService $trabajadorService;
    protected ExternalApiService $externalApiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trabajadorService = $this->createMock(TrabajadorService::class);
        $this->externalApiService = $this->createMock(ExternalApiService::class);
        $this->convenioService = new ConvenioValidationService($this->trabajadorService, $this->externalApiService);
    }

    /**
     * Test: Validar convenio trabajador exitoso
     */
    public function test_validar_convenio_trabajador_exitoso(): void
    {
        // Crear convenio de prueba
        $convenio = EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'fecha_convenio' => now()->subMonths(6),
            'fecha_vencimiento' => now()->addMonths(6),
            'estado' => 'Activo',
            'representante_documento' => '12345678',
            'representante_nombre' => 'Representante Test',
            'correo' => 'test@empresa.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle Test 123',
            'ciudad' => 'Bogotá',
            'departamento' => 'Cundinamarca',
            'sector_economico' => 'Servicios',
            'tipo_empresa' => 'Sociedad Anónima'
        ]);

        // Mock del servicio de trabajadores
        $this->trabajadorService->method('obtenerDatosTrabajador')
            ->willReturn([
                'cedula' => '80123456',
                'nombre' => 'Juan Pérez',
                'estado' => 'A', // Activo
                'fecha_afiliacion' => now()->subMonths(8)->format('Y-m-d'), // 8 meses de antigüedad
                'empresa' => [
                    'nit' => '900123456',
                    'nombre' => 'Empresa Test S.A.'
                ]
            ]);

        // Ejecutar la validación
        $resultado = $this->convenioService->validarConvenioTrabajador('900123456', '80123456');

        // Verificar resultado
        $this->assertTrue($resultado['elegible']);
        $this->assertEquals('Activo', $resultado['convenio']['estado']);
        $this->assertEquals('900123456', $resultado['convenio']['nit']);
        $this->assertEquals('Juan Pérez', $resultado['trabajador']['nombre_completo']);
        $this->assertEquals('A', $resultado['trabajador']['estado']);
    }

    /**
     * Test: Validar convenio trabajador sin convenio activo
     */
    public function test_validar_convenio_trabajador_sin_convenio_activo(): void
    {
        // Crear convenio inactivo
        EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'fecha_convenio' => now()->subMonths(12),
            'fecha_vencimiento' => now()->subMonth(), // Vencido
            'estado' => 'Inactivo',
            'representante_documento' => '12345678',
            'representante_nombre' => 'Representante Test'
        ]);

        // Mock del servicio de trabajadores
        $this->trabajadorService->method('obtenerDatosTrabajador')
            ->willReturn([
                'cedula' => '80123456',
                'nombre' => 'Juan Pérez',
                'estado' => 'A',
                'fecha_afiliacion' => now()->subMonths(8)->format('Y-m-d'),
                'empresa' => [
                    'nit' => '900123456',
                    'nombre' => 'Empresa Test S.A.'
                ]
            ]);

        // Ejecutar la validación y esperar excepción
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no tiene convenio activo');

        $this->convenioService->validarConvenioTrabajador('900123456', '80123456');
    }

    /**
     * Test: Validar convenio trabajador con tiempo insuficiente
     */
    public function test_validar_convenio_trabajador_tiempo_insuficiente(): void
    {
        // Crear convenio activo
        EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'fecha_convenio' => now()->subMonths(6),
            'fecha_vencimiento' => now()->addMonths(6),
            'estado' => 'Activo',
            'representante_documento' => '12345678',
            'representante_nombre' => 'Representante Test'
        ]);

        // Mock del servicio de trabajadores con menos de 6 meses
        $this->trabajadorService->method('obtenerDatosTrabajador')
            ->willReturn([
                'cedula' => '80123456',
                'nombre' => 'Juan Pérez',
                'estado' => 'A',
                'fecha_afiliacion' => now()->subMonths(3)->format('Y-m-d'), // Solo 3 meses de antigüedad
                'empresa' => [
                    'nit' => '900123456',
                    'nombre' => 'Empresa Test S.A.'
                ]
            ]);

        // Ejecutar la validación y esperar excepción
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no cumple con el tiempo mínimo');

        $this->convenioService->validarConvenioTrabajador('900123456', '80123456');
    }

    /**
     * Test: Validar convenio trabajador inactivo
     */
    public function test_validar_convenio_trabajador_inactivo(): void
    {
        // Crear convenio activo
        EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'fecha_convenio' => now()->subMonths(6),
            'fecha_vencimiento' => now()->addMonths(6),
            'estado' => 'Activo',
            'representante_documento' => '12345678',
            'representante_nombre' => 'Representante Test'
        ]);

        // Mock del servicio de trabajadores con estado inactivo
        $this->trabajadorService->method('obtenerDatosTrabajador')
            ->willReturn([
                'cedula' => '80123456',
                'nombre' => 'Juan Pérez',
                'estado' => 'I', // Inactivo
                'fecha_afiliacion' => now()->subMonths(8)->format('Y-m-d'),
                'empresa' => [
                    'nit' => '900123456',
                    'nombre' => 'Empresa Test S.A.'
                ]
            ]);

        // Ejecutar la validación y esperar excepción
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no está activo');

        $this->convenioService->validarConvenioTrabajador('900123456', '80123456');
    }

    /**
     * Test: Validar convenio trabajador no encontrado
     */
    public function test_validar_convenio_trabajador_no_encontrado(): void
    {
        // Mock del servicio que lanza excepción
        $this->trabajadorService->method('obtenerDatosTrabajador')
            ->willThrowException(new \Exception('No se encontraron datos para el trabajador'));

        // Ejecutar la validación y esperar excepción
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No se encontraron datos');

        $this->convenioService->validarConvenioTrabajador('900123456', '80123456');
    }

    /**
     * Test: Crear convenio con datos completos
     */
    public function test_crear_convenio_datos_completos(): void
    {
        $convenio = EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'fecha_convenio' => now()->subMonths(6),
            'fecha_vencimiento' => now()->addMonths(6),
            'estado' => 'Activo',
            'representante_documento' => '12345678',
            'representante_nombre' => 'Representante Test',
            'correo' => 'test@empresa.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle Test 123',
            'ciudad' => 'Bogotá',
            'departamento' => 'Cundinamarca',
            'sector_economico' => 'Servicios',
            'tipo_empresa' => 'Sociedad Anónima'
        ]);

        $this->assertInstanceOf(EmpresaConvenio::class, $convenio);
        $this->assertEquals('900123456', $convenio->nit);
        $this->assertEquals('Empresa Test S.A.', $convenio->razon_social);
        $this->assertEquals('Activo', $convenio->estado);
        $this->assertEquals('Representante Test', $convenio->representante_nombre);
        $this->assertEquals('test@empresa.com', $convenio->correo);
    }

    /**
     * Test: Consultar convenio por NIT
     */
    public function test_consultar_convenio_por_nit(): void
    {
        // Crear convenio
        $convenio = EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'estado' => 'Activo'
        ]);

        // Consultar por NIT
        $convenioEncontrado = EmpresaConvenio::where('nit', '900123456')->first();

        $this->assertNotNull($convenioEncontrado);
        $this->assertEquals($convenio->id, $convenioEncontrado->id);
        $this->assertEquals('Empresa Test S.A.', $convenioEncontrado->razon_social);
    }

    /**
     * Test: Consultar convenios activos
     */
    public function test_consultar_convenios_activos(): void
    {
        // Crear convenios con diferentes estados
        EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Activa',
            'estado' => 'Activo'
        ]);

        EmpresaConvenio::create([
            'nit' => '900123457',
            'razon_social' => 'Empresa Inactiva',
            'estado' => 'Inactivo'
        ]);

        EmpresaConvenio::create([
            'nit' => '900123458',
            'razon_social' => 'Empresa Vencida',
            'estado' => 'Vencido'
        ]);

        // Consultar solo convenios activos
        $conveniosActivos = EmpresaConvenio::where('estado', 'Activo')->get();

        $this->assertCount(1, $conveniosActivos);
        $this->assertEquals('Empresa Activa', $conveniosActivos->first()->razon_social);
    }

    /**
     * Test: Validar estructura de datos del servicio
     */
    public function test_estructura_datos_servicio(): void
    {
        // Crear convenio
        $convenio = EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'estado' => 'Activo'
        ]);

        // Mock del servicio de trabajadores
        $this->trabajadorService->method('obtenerDatosTrabajador')
            ->willReturn([
                'cedula' => '80123456',
                'nombre' => 'Juan Pérez',
                'apellido' => 'García',
                'estado' => 'A',
                'fecha_afiliacion' => now()->subMonths(8)->format('Y-m-d'),
                'salario' => 2000000,
                'cargo' => 'Desarrollador',
                'email' => 'juan.perez@empresa.com',
                'empresa' => [
                    'nit' => '900123456',
                    'nombre' => 'Empresa Test S.A.'
                ]
            ]);

        // Ejecutar la validación
        $resultado = $this->convenioService->validarConvenioTrabajador('900123456', '80123456');

        // Verificar estructura completa
        $this->assertArrayHasKey('elegible', $resultado);
        $this->assertArrayHasKey('convenio', $resultado);
        $this->assertArrayHasKey('trabajador', $resultado);

        // Verificar estructura del convenio
        $this->assertArrayHasKey('id', $resultado['convenio']);
        $this->assertArrayHasKey('nit', $resultado['convenio']);
        $this->assertArrayHasKey('razon_social', $resultado['convenio']);
        $this->assertArrayHasKey('estado', $resultado['convenio']);

        // Verificar estructura del trabajador
        $this->assertArrayHasKey('cedula', $resultado['trabajador']);
        $this->assertArrayHasKey('nombre_completo', $resultado['trabajador']);
        $this->assertArrayHasKey('estado', $resultado['trabajador']);
        $this->assertArrayHasKey('meses_servicio', $resultado['trabajador']);
        $this->assertArrayHasKey('salario', $resultado['trabajador']);
        $this->assertArrayHasKey('cargo', $resultado['trabajador']);
        $this->assertArrayHasKey('email', $resultado['trabajador']);
    }
}
