<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\GeneradorPdfService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerificarPdfApiTest extends TestCase
{
    /**
     * Test de verificación de PDF exitoso
     */
    public function test_verificar_pdf_exitoso(): void
    {
        // Mock de API Flask exitosa
        Http::fake([
            '*/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'size_bytes' => 1024,
                'base64_content' => 'JVBERi0xLjQK...'
            ], 200)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('solicitud_TEST001.pdf');

        $this->assertTrue($resultado['success']);
        $this->assertTrue($resultado['existe']);
        $this->assertEquals('solicitud_TEST001.pdf', $resultado['filename']);
        $this->assertEquals(1024, $resultado['size_bytes']);
        $this->assertNotEmpty($resultado['base64_content']);
        $this->assertEquals(200, $resultado['status']);
    }

    /**
     * Test de PDF no encontrado
     */
    public function test_verificar_pdf_no_encontrado(): void
    {
        // Mock de API Flask con PDF no encontrado
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response([
                'error' => 'Archivo no encontrado: no_existe.pdf'
            ], 404)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('no_existe.pdf');

        $this->assertFalse($resultado['success']);
        $this->assertFalse($resultado['existe']);
        $this->assertEquals(404, $resultado['status']);
        $this->assertStringContainsString('no encontrado', $resultado['error']);
    }

    /**
     * Test de error de conexión
     */
    public function test_verificar_pdf_error_conexion(): void
    {
        // Mock de error de conexión
        Http::fake([
            '*/api/download-pdf*' => Http::response(['error' => 'Connection timeout'], 500)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('test.pdf');

        $this->assertFalse($resultado['success']);
        $this->assertFalse($resultado['existe']);
        $this->assertEquals(500, $resultado['status']);
    }

    /**
     * Test de archivo no PDF
     */
    public function test_verificar_archivo_no_pdf(): void
    {
        // Mock de API Flask rechazando archivo no PDF
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response([
                'error' => 'El archivo debe ser un PDF'
            ], 400)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('test.txt');

        $this->assertFalse($resultado['success']);
        $this->assertFalse($resultado['existe']);
        $this->assertEquals(400, $resultado['status']);
        $this->assertStringContainsString('PDF', $resultado['error']);
    }

    /**
     * Test de path traversal
     */
    public function test_verificar_path_traversal(): void
    {
        // Mock de API Flask rechazando path traversal
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response([
                'error' => 'Path inválido: solo se permiten nombres de archivo'
            ], 400)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('../../../etc/passwd');

        $this->assertFalse($resultado['success']);
        $this->assertFalse($resultado['existe']);
        $this->assertEquals(400, $resultado['status']);
        $this->assertStringContainsString('inválido', $resultado['error']);
    }

    /**
     * Test de respuesta vacía o malformed
     */
    public function test_verificar_respuesta_malformed(): void
    {
        // Mock de respuesta malformed
        Http::fake([
            'http://localhost:8080/api/download-pdf*' => Http::response('', 500)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('test.pdf');

        $this->assertFalse($resultado['success']);
        $this->assertFalse($resultado['existe']);
        $this->assertEquals(404, $resultado['status']);
    }
}
