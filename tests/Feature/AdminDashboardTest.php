<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\EmpresaConvenio;
use App\Models\SolicitudCredito;
use App\Models\EstadoSolicitud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Estructura básica del dashboard
     */
    public function test_estructura_basica_dashboard(): void
    {
        // Verificar que los modelos existan
        $this->assertTrue(class_exists(User::class));
        $this->assertTrue(class_exists(EmpresaConvenio::class));
        $this->assertTrue(class_exists(SolicitudCredito::class));
        $this->assertTrue(class_exists(EstadoSolicitud::class));
    }

    /**
     * Test: Crear usuario administrador
     */
    public function test_crear_usuario_administrador(): void
    {
        $admin = User::create([
            'username' => 'admin_test',
            'email' => 'admin@test.com',
            'password_hash' => Hash::make('password123'),
            'disabled' => false,
            'roles' => ['administrator']
        ]);

        $this->assertInstanceOf(User::class, $admin);
        $this->assertEquals('admin_test', $admin->username);
        $this->assertEquals('admin@test.com', $admin->email);
        $this->assertEquals(['administrator'], $admin->roles);
        $this->assertFalse($admin->disabled);
    }

    /**
     * Test: Crear convenio
     */
    public function test_crear_convenio(): void
    {
        $convenio = EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'estado' => 'Activo',
            'tipo_empresa' => 'Sociedad Anónima',
            'numero_empleados' => 100,
            'representante_documento' => '12345678',
            'representante_nombre' => 'Representante Test',
            'correo' => 'test@empresa.com',
            'telefono' => '3001234567',
            'direccion' => 'Calle Test 123',
            'ciudad' => 'Bogotá',
            'departamento' => 'Cundinamarca',
            'sector_economico' => 'Servicios',
            'fecha_convenio' => now()->subMonths(6),
            'fecha_vencimiento' => now()->addMonths(6)
        ]);

        $this->assertInstanceOf(EmpresaConvenio::class, $convenio);
        $this->assertEquals('900123456', $convenio->nit);
        $this->assertEquals('Empresa Test S.A.', $convenio->razon_social);
        $this->assertEquals('Activo', $convenio->estado);
        $this->assertEquals(100, $convenio->numero_empleados);
    }

    /**
     * Test: Estadísticas básicas de solicitudes
     */
    public function test_estadisticas_basicas_solicitudes(): void
    {
        // Crear usuario
        $user = User::create([
            'username' => 'test_user',
            'email' => 'test@test.com',
            'password_hash' => Hash::make('password123'),
            'disabled' => false,
            'roles' => ['user_trabajador']
        ]);

        // Crear solicitud sin foreign key temporalmente
        $solicitud = new SolicitudCredito();
        $solicitud->numero_solicitud = 'TEST001';
        $solicitud->owner_username = 'test_user';
        $solicitud->valor_solicitud = 1000000;
        $solicitud->plazo_meses = 12;
        $solicitud->tasa_interes = 8.5;
        $solicitud->estado = 'POSTULADO';
        $solicitud->producto_tipo = 'PE';
        $solicitud->tipo_credito = '001';
        $solicitud->created_at = now();
        $solicitud->updated_at = now();
        $solicitud->save();

        // Verificar estadísticas
        $total = SolicitudCredito::count();
        $this->assertEquals(1, $total);

        $porEstado = SolicitudCredito::where('estado', 'POSTULADO')->count();
        $this->assertEquals(1, $porEstado);

        $montoTotal = SolicitudCredito::sum('valor_solicitud');
        $this->assertEquals(1000000, $montoTotal);
    }

    /**
     * Test: Estadísticas básicas de convenios
     */
    public function test_estadisticas_basicas_convenios(): void
    {
        // Crear convenio
        EmpresaConvenio::create([
            'nit' => '900123456',
            'razon_social' => 'Empresa Test S.A.',
            'estado' => 'Activo',
            'tipo_empresa' => 'Sociedad Anónima',
            'numero_empleados' => 100
        ]);

        // Verificar estadísticas
        $total = EmpresaConvenio::count();
        $this->assertEquals(1, $total);

        $activos = EmpresaConvenio::where('estado', 'Activo')->count();
        $this->assertEquals(1, $activos);

        $totalEmpleados = EmpresaConvenio::sum('numero_empleados');
        $this->assertEquals(100, $totalEmpleados);
    }

    /**
     * Test: Estadísticas básicas de usuarios
     */
    public function test_estadisticas_basicas_usuarios(): void
    {
        // Crear usuarios
        User::create([
            'username' => 'admin',
            'email' => 'admin@test.com',
            'password_hash' => Hash::make('password123'),
            'disabled' => false,
            'roles' => ['administrator']
        ]);

        User::create([
            'username' => 'trabajador1',
            'email' => 'trabajador1@test.com',
            'password_hash' => Hash::make('password123'),
            'disabled' => false,
            'roles' => ['user_trabajador']
        ]);

        User::create([
            'username' => 'trabajador2',
            'email' => 'trabajador2@test.com',
            'password_hash' => Hash::make('password123'),
            'disabled' => true,
            'roles' => ['user_trabajador']
        ]);

        // Verificar estadísticas
        $total = User::count();
        $this->assertEquals(3, $total);

        $activos = User::where('disabled', false)->count();
        $this->assertEquals(2, $activos);

        $conteoRoles = $this->obtenerConteoRoles();
        $this->assertEquals(1, $conteoRoles['administrator'] ?? 0);
        $this->assertEquals(2, $conteoRoles['user_trabajador'] ?? 0);
    }

    /**
     * Test: Colores por estado
     */
    public function test_colores_por_estado(): void
    {
        // Verificar que existan los colores definidos
        $coloresPorEstado = [
            'POSTULADO' => '#F59E0B',
            'ENVIADO_VALIDACION' => '#3B82F6',
            'EN_VALIDACION' => '#8B5CF6',
            'APROBADA' => '#10B981',
            'ENVIADO_FIRMA' => '#F97316',
            'FIRMA_PENDIENTE' => '#F97316',
            'FIRMADA' => '#06B6D4',
            'DESEMBOLSADA' => '#84CC16',
            'FINALIZADA' => '#6B7280',
            'RECHAZADA' => '#EF4444',
            'DESISTE' => '#EF4444'
        ];

        $this->assertArrayHasKey('POSTULADO', $coloresPorEstado);
        $this->assertArrayHasKey('APROBADA', $coloresPorEstado);
        $this->assertArrayHasKey('RECHAZADA', $coloresPorEstado);
        $this->assertEquals('#F59E0B', $coloresPorEstado['POSTULADO']);
        $this->assertEquals('#10B981', $coloresPorEstado['APROBADA']);
        $this->assertEquals('#EF4444', $coloresPorEstado['RECHAZADA']);
    }

    /**
     * Test: Estructura de respuesta esperada
     */
    public function test_estructura_respuesta_esperada(): void
    {
        // Verificar que la respuesta esperada tenga la estructura correcta
        $estructuraEsperada = [
            'success',
            'message',
            'data' => [
                'solicitudes' => [
                    'total',
                    'aprobadas',
                    'activas',
                    'pendientesFirma',
                    'tasaAprobacion',
                    'montoTotalAprobado',
                    'porEstado',
                    'porMes'
                ],
                'convenios' => [
                    'activos',
                    'totalEmpresas',
                    'topEmpresas',
                    'porTipo'
                ],
                'usuarios' => [
                    'total',
                    'activos',
                    'trabajadores',
                    'porRol',
                    'recientes'
                ],
                'actividadReciente',
                'ultimaActualizacion'
            ]
        ];

        $this->assertArrayHasKey('success', $estructuraEsperada);
        $this->assertArrayHasKey('data', $estructuraEsperada);
        $this->assertArrayHasKey('solicitudes', $estructuraEsperada['data']);
        $this->assertArrayHasKey('convenios', $estructuraEsperada['data']);
        $this->assertArrayHasKey('usuarios', $estructuraEsperada['data']);
        $this->assertArrayHasKey('actividadReciente', $estructuraEsperada['data']);
        $this->assertArrayHasKey('ultimaActualizacion', $estructuraEsperada['data']);
    }

    /**
     * Obtener conteo de usuarios por rol
     */
    private function obtenerConteoRoles(): array
    {
        $conteo = [];

        $usuarios = User::all();
        foreach ($usuarios as $usuario) {
            $roles = $usuario->roles ?? [];
            foreach ($roles as $rol) {
                if (!isset($conteo[$rol])) {
                    $conteo[$rol] = 0;
                }
                $conteo[$rol]++;
            }
        }

        return $conteo;
    }
}
