<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SolicitudCredito;
use App\Models\SolicitudDocumento;
use App\Services\SolicitudService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SolicitudDocumentoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake storage para testing
        Storage::fake('public');

        // Crear usuario de prueba para cumplir la FK
        \App\Models\User::create([
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password_hash' => Hash::make('password'),
            'full_name' => 'Test User'
        ]);
    }

    /**
     * Test de creación de documento en tabla solicitud_documentos
     */
    public function test_crear_documento_en_tabla(): void
    {
        // Crear solicitud de prueba
        $solicitud = SolicitudCredito::create([
            'numero_solicitud' => 'TEST-001',
            'owner_username' => 'test_user',
            'monto_solicitado' => 50000,
            'plazo_meses' => 12,
            'tasa_interes' => 8.0,
            'estado' => 'PENDIENTE'
        ]);

        // Datos del documento
        $fileData = [
            'id' => 'uuid-12345',
            'documento_requerido_id' => 'DOC-001',
            'nombre_original' => 'documento.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano' => 1024,
            'ruta_archivo' => 'documentos/solicitudes/test_file.pdf'
        ];

        // Crear documento usando el modelo
        $documento = SolicitudDocumento::create([
            'solicitud_id' => $solicitud->numero_solicitud,
            'documento_uuid' => $fileData['id'],
            'documento_requerido_id' => $fileData['documento_requerido_id'],
            'nombre_original' => $fileData['nombre_original'],
            'saved_filename' => basename($fileData['ruta_archivo']),
            'tipo_mime' => $fileData['tipo_mime'],
            'tamano_bytes' => $fileData['tamano'],
            'ruta_archivo' => $fileData['ruta_archivo'],
            'activo' => true
        ]);

        // Verificar que se creó correctamente
        $this->assertInstanceOf(SolicitudDocumento::class, $documento);
        $this->assertEquals($solicitud->numero_solicitud, $documento->solicitud_id);
        $this->assertEquals($fileData['id'], $documento->documento_uuid);
        $this->assertEquals($fileData['documento_requerido_id'], $documento->documento_requerido_id);
        $this->assertTrue($documento->activo);

        // Verificar relación con solicitud
        $this->assertEquals($solicitud->numero_solicitud, $documento->solicitud->numero_solicitud);
    }

    /**
     * Test de relación entre solicitud y documentos
     */
    public function test_relacion_solicitud_documentos(): void
    {
        // Crear solicitud
        $solicitud = SolicitudCredito::create([
            'numero_solicitud' => 'TEST-002',
            'owner_username' => 'test_user',
            'monto_solicitado' => 75000,
            'plazo_meses' => 24,
            'tasa_interes' => 7.5,
            'estado' => 'APROBADO'
        ]);

        // Crear múltiples documentos
        $documentos = [];
        for ($i = 1; $i <= 3; $i++) {
            $documentos[] = SolicitudDocumento::create([
                'solicitud_id' => $solicitud->numero_solicitud,
                'documento_uuid' => "uuid-{$i}",
                'documento_requerido_id' => "DOC-00{$i}",
                'nombre_original' => "documento_{$i}.pdf",
                'saved_filename' => "doc_{$i}.pdf",
                'tipo_mime' => 'application/pdf',
                'tamano_bytes' => 1024 * $i,
                'ruta_archivo' => "documentos/solicitudes/doc_{$i}.pdf",
                'activo' => true
            ]);
        }

        // Verificar relación
        $documentosRelacionados = $solicitud->documentos;
        $this->assertCount(3, $documentosRelacionados);

        // Verificar que todos los documentos pertenezcan a la solicitud
        foreach ($documentosRelacionados as $doc) {
            $this->assertEquals($solicitud->numero_solicitud, $doc->solicitud_id);
            $this->assertTrue($doc->activo);
        }

        // Verificar scope active
        $documentosActivos = $solicitud->documentos()->active()->get();
        $this->assertCount(3, $documentosActivos);

        // Desactivar un documento
        $documentos[0]->update(['activo' => false]);
        $documentosActivos = $solicitud->documentos()->active()->get();
        $this->assertCount(2, $documentosActivos);
    }

    /**
     * Test de scope por tipo de documento
     */
    public function test_scope_por_tipo_documento(): void
    {
        // Crear solicitud
        $solicitud = SolicitudCredito::create([
            'numero_solicitud' => 'TEST-003',
            'owner_username' => 'test_user',
            'monto_solicitado' => 30000,
            'plazo_meses' => 6,
            'tasa_interes' => 9.0,
            'estado' => 'PENDIENTE'
        ]);

        // Crear documentos de diferentes tipos
        SolicitudDocumento::create([
            'solicitud_id' => $solicitud->numero_solicitud,
            'documento_uuid' => 'uuid-cc',
            'documento_requerido_id' => 'CC',
            'nombre_original' => 'cedula.pdf',
            'saved_filename' => 'cedula.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 500,
            'ruta_archivo' => 'documentos/solicitudes/cedula.pdf',
            'activo' => true
        ]);

        SolicitudDocumento::create([
            'solicitud_id' => $solicitud->numero_solicitud,
            'documento_uuid' => 'uuid-nit',
            'documento_requerido_id' => 'NIT',
            'nombre_original' => 'nit.pdf',
            'saved_filename' => 'nit.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 800,
            'ruta_archivo' => 'documentos/solicitudes/nit.pdf',
            'activo' => true
        ]);

        // Verificar scope por tipo
        $documentosCC = $solicitud->documentos()->byType('CC')->get();
        $this->assertCount(1, $documentosCC);
        $this->assertEquals('CC', $documentosCC->first()->documento_requerido_id);

        $documentosNIT = $solicitud->documentos()->byType('NIT')->get();
        $this->assertCount(1, $documentosNIT);
        $this->assertEquals('NIT', $documentosNIT->first()->documento_requerido_id);
    }

    /**
     * Test de formato de tamaño de archivo
     */
    public function test_formato_tamano_archivo(): void
    {
        // Crear solicitud primero para cumplir FK
        SolicitudCredito::create([
            'numero_solicitud' => 'TEST-004',
            'owner_username' => 'test_user',
            'monto_solicitado' => 10000,
            'plazo_meses' => 6,
            'tasa_interes' => 8.0,
            'estado' => 'PENDIENTE'
        ]);

        // Crear documento
        $documento = SolicitudDocumento::create([
            'solicitud_id' => 'TEST-004',
            'documento_uuid' => 'uuid-test',
            'documento_requerido_id' => 'TEST',
            'nombre_original' => 'test.pdf',
            'saved_filename' => 'test.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 2048, // 2 KB
            'ruta_archivo' => 'test.pdf',
            'activo' => true
        ]);

        // Verificar formato
        $this->assertEquals('2 KB', $documento->tamano_formatted);

        // Probar diferentes tamaños
        $documento->tamano_bytes = 1024; // 1 KB
        $this->assertEquals('1 KB', $documento->tamano_formatted);

        $documento->tamano_bytes = 1048576; // 1 MB
        $this->assertEquals('1 MB', $documento->tamano_formatted);
    }

    /**
     * Test de soft deletes
     */
    public function test_soft_deletes(): void
    {
        // Crear solicitud primero para cumplir FK
        SolicitudCredito::create([
            'numero_solicitud' => 'TEST-005',
            'owner_username' => 'test_user',
            'monto_solicitado' => 15000,
            'plazo_meses' => 12,
            'tasa_interes' => 7.5,
            'estado' => 'PENDIENTE'
        ]);

        // Crear documento
        $documento = SolicitudDocumento::create([
            'solicitud_id' => 'TEST-005',
            'documento_uuid' => 'uuid-delete',
            'documento_requerido_id' => 'DELETE',
            'nombre_original' => 'delete.pdf',
            'saved_filename' => 'delete.pdf',
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 1024,
            'ruta_archivo' => 'delete.pdf',
            'activo' => true
        ]);

        // Verificar que existe
        $this->assertInstanceOf(SolicitudDocumento::class, SolicitudDocumento::find($documento->id));

        // Soft delete
        $documento->delete();

        // Verificar que no aparece en consultas normales
        $this->assertNull(SolicitudDocumento::find($documento->id));

        // Verificar que aparece con withTrashed
        $this->assertInstanceOf(SolicitudDocumento::class, SolicitudDocumento::withTrashed()->find($documento->id));

        // Restore
        $documento->restore();
        $this->assertInstanceOf(SolicitudDocumento::class, SolicitudDocumento::find($documento->id));
    }
}
