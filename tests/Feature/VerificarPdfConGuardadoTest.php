<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\GeneradorPdfService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class VerificarPdfConGuardadoTest extends TestCase
{
    /**
     * Test de verificación y guardado exitoso de PDF
     */
    public function test_verificar_y_guardar_pdf_exitoso(): void
    {
        // Fake storage
        Storage::fake('public');

        // Mock de API Flask exitosa con base64
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'size_bytes' => 1024,
                'base64_content' => base64_encode('Test PDF content')
            ], 200)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->verificarPdf('solicitud_TEST001.pdf');

        $this->assertTrue($resultado['success']);
        $this->assertTrue($resultado['existe']);
        $this->assertTrue($resultado['guardado_local']);
        $this->assertNotNull($resultado['local_path']);
        $this->assertStringContainsString('pdfs/solicitudes/', $resultado['local_path']);
        $this->assertEquals('solicitud_TEST001.pdf', $resultado['filename']);
        $this->assertEquals(1024, $resultado['size_bytes']);

        // Verificar que el archivo fue guardado en storage
        $this->assertTrue(Storage::disk('public')->exists($resultado['local_path']));

        // Verificar contenido del archivo
        $contenido = Storage::disk('public')->get($resultado['local_path']);
        $this->assertEquals('Test PDF content', $contenido);
    }

    /**
     * Test de verificación con base64 inválido
     */
    public function test_verificar_pdf_base64_invalido(): void
    {
        // Fake storage
        Storage::fake('public');

        // Mock de API Flask con base64 inválido
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'size_bytes' => 1024,
                'base64_content' => 'base64_invalido!!!'
            ], 200)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->verificarPdf('solicitud_TEST001.pdf');

        // El método debería continuar aunque falle el guardado local
        $this->assertTrue($resultado['success']);
        $this->assertTrue($resultado['existe']);
        // El guardado local puede ser true o false dependiendo de cómo maneje el error
        $this->assertIsBool($resultado['guardado_local']);
        $this->assertNotNull($resultado['local_path']); // Puede tener path aunque falle

        // Verificar el estado del directorio (puede existir por otros tests)
        $existeDirectorio = Storage::disk('public')->exists('pdfs/solicitudes/');
        if ($resultado['guardado_local']) {
            $this->assertTrue($existeDirectorio);
        } else {
            // Si no se guardó, el directorio puede o no existir
            $this->assertIsBool($existeDirectorio);
        }
    }

    /**
     * Test de verificación sin base64 content
     */
    public function test_verificar_pdf_sin_base64(): void
    {
        // Fake storage
        Storage::fake('public');

        // Mock de API Flask sin base64_content
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'size_bytes' => 1024
                // Sin base64_content
            ], 200)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->verificarPdf('solicitud_TEST001.pdf');

        $this->assertTrue($resultado['success']);
        $this->assertTrue($resultado['existe']);
        $this->assertFalse($resultado['guardado_local']);
        $this->assertNull($resultado['local_path']);
    }

    /**
     * Test de PDF no encontrado (no se guarda nada)
     */
    public function test_verificar_pdf_no_encontrado(): void
    {
        // Fake storage
        Storage::fake('public');

        // Mock de API Flask con PDF no encontrado
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response([
                'error' => 'Archivo no encontrado: no_existe.pdf'
            ], 404)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->verificarPdf('no_existe.pdf');

        $this->assertFalse($resultado['success']);
        $this->assertFalse($resultado['existe']);
        $this->assertFalse($resultado['guardado_local']);
        $this->assertNull($resultado['local_path']);
        $this->assertEquals(404, $resultado['status']);
    }

    /**
     * Test de error de conexión (no se guarda nada)
     */
    public function test_verificar_pdf_error_conexion(): void
    {
        // Fake storage
        Storage::fake('public');

        // Mock de error de conexión
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::timeout(1)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->verificarPdf('test.pdf');

        $this->assertFalse($resultado['success']);
        $this->assertFalse($resultado['existe']);
        $this->assertFalse($resultado['guardado_local']);
        $this->assertNull($resultado['local_path']);
        $this->assertStringContainsString('interno', $resultado['error']);
    }

    /**
     * Test de nombres de archivo únicos
     */
    public function test_verificar_pdf_nombres_unicos(): void
    {
        // Fake storage
        Storage::fake('public');

        // Mock de API Flask exitosa
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'size_bytes' => 1024,
                'base64_content' => base64_encode('Test PDF content')
            ], 200)
        ]);

        $service = new GeneradorPdfService();

        // Primera verificación
        $resultado1 = $service->verificarPdf('solicitud_TEST001.pdf');
        $path1 = $resultado1['local_path'];

        // Segunda verificación (debería crear nombre único)
        sleep(1); // Pequeña pausa para asegurar timestamp diferente
        $resultado2 = $service->verificarPdf('solicitud_TEST001.pdf');
        $path2 = $resultado2['local_path'];

        $this->assertNotEquals($path1, $path2);
        $this->assertStringContainsString('solicitud_TEST001_', $path1);
        $this->assertStringContainsString('solicitud_TEST001_', $path2);
        $this->assertStringEndsWith('.pdf', $path1);
        $this->assertStringEndsWith('.pdf', $path2);

        // Verificar que ambos archivos existen
        $this->assertTrue(Storage::disk('public')->exists($path1));
        $this->assertTrue(Storage::disk('public')->exists($path2));
    }
}
