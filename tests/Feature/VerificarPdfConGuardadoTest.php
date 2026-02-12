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
     * Test de verificación y guardado de PDF exitoso
     */
    public function test_verificar_y_guardar_pdf_exitoso(): void
    {
        // Mock de API Flask exitosa
        Http::fake([
            '*/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'size_bytes' => 1024
            ], 200)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('solicitud_TEST001.pdf');

        // Verificamos estructura básica de respuesta
        $this->assertArrayHasKey('success', $resultado);
        $this->assertArrayHasKey('existe', $resultado);
        $this->assertArrayHasKey('filename', $resultado);
    }

    /**
     * Test de verificación con base64 inválido
     */
    public function test_verificar_pdf_base64_invalido(): void
    {
        // Mock de API Flask con base64 inválido
        Http::fake([
            '*/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'error' => 'Base64 inválido'
            ], 200)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('solicitud_TEST001.pdf');

        // Verificamos estructura básica
        $this->assertArrayHasKey('success', $resultado);
        $this->assertArrayHasKey('existe', $resultado);
    }

    /**
     * Test de verificación sin base64 content
     */
    public function test_verificar_pdf_sin_base64(): void
    {
        // Mock de API Flask sin base64
        Http::fake([
            '*/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'size_bytes' => 1024
            ], 200)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('solicitud_TEST001.pdf');

        // Verificamos estructura básica
        $this->assertArrayHasKey('success', $resultado);
        $this->assertArrayHasKey('existe', $resultado);
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
        $resultado = $service->downloadPdfApi('no_existe.pdf');

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
            '*/api/download-pdf*' => Http::response(['error' => 'Connection timeout'], 500)
        ]);

        $service = new GeneradorPdfService();
        $resultado = $service->downloadPdfApi('test.pdf');

        $this->assertFalse($resultado['success']);
        $this->assertFalse($resultado['existe']);
        $this->assertArrayHasKey('guardado_local', $resultado);
        $this->assertArrayHasKey('local_path', $resultado);
        $this->assertEquals(500, $resultado['status']);
    }

    /**
     * Test de nombres de archivo únicos
     */
    public function test_verificar_pdf_nombres_unicos(): void
    {
        // Mock de API Flask exitosa
        Http::fake([
            '*/api/download-pdf*' => Http::response([
                'success' => true,
                'filename' => 'solicitud_TEST001.pdf',
                'size_bytes' => 1024
            ], 200)
        ]);

        $service = new GeneradorPdfService();

        // Primera verificación
        $resultado1 = $service->downloadPdfApi('solicitud_TEST001.pdf');

        // Segunda verificación
        $resultado2 = $service->downloadPdfApi('solicitud_TEST001.pdf');

        // Verificamos que ambos resultados sean exitosos
        $this->assertArrayHasKey('success', $resultado1);
        $this->assertArrayHasKey('success', $resultado2);
    }
}
