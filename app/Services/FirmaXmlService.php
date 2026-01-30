<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use DOMDocument;
use DOMXPath;
use Exception;

class FirmaXmlService
{
    /**
     * Construir XML de firmas para solicitud de crédito
     */
    public function buildFirmasSolicitudCreditoXml(string $solicitudXmlBytes, array $firmas, string $claveFirma): string
    {
        try {
            Log::info('Construyendo XML de firmas', [
                'num_firmas' => count($firmas),
                'solicitud_size' => strlen($solicitudXmlBytes)
            ]);

            // Validar clave de firma
            $this->validarClaveFirma($claveFirma);

            // Cargar XML de solicitud
            $dom = new DOMDocument();
            $dom->loadXML($solicitudXmlBytes);
            
            // Crear estructura de firmas
            $firmasNode = $dom->createElement('firmas');
            
            foreach ($firmas as $firma) {
                $firmaNode = $this->crearNodoFirma($dom, $firma, $claveFirma);
                $firmasNode->appendChild($firmaNode);
            }
            
            // Agregar metadatos
            $metadatosNode = $this->crearMetadatos($dom, count($firmas));
            $firmasNode->appendChild($metadatosNode);
            
            // Reemplazar o agregar nodo de firmas
            $xpath = new DOMXPath($dom);
            $existingFirmas = $xpath->query('//firmas');
            
            if ($existingFirmas->length > 0) {
                $existingFirmas->item(0)->parentNode->replaceChild($firmasNode, $existingFirmas->item(0));
            } else {
                $dom->documentElement->appendChild($firmasNode);
            }
            
            $xmlResult = $dom->saveXML();
            
            Log::info('XML de firmas construido exitosamente', [
                'size' => strlen($xmlResult)
            ]);
            
            return $xmlResult;

        } catch (Exception $e) {
            Log::error('Error al construir XML de firmas', [
                'error' => $e->getMessage(),
                'num_firmas' => count($firmas)
            ]);
            
            throw $e;
        }
    }

    /**
     * Agregar firma a XML de firmas existente
     */
    public function appendFirmaToFirmasXml(string $solicitudXmlBytes, string $firmasXmlBytes, array $firma, string $claveFirma): string
    {
        try {
            Log::info('Agregando firma a XML existente', [
                'solicitud_size' => strlen($solicitudXmlBytes),
                'firmas_size' => strlen($firmasXmlBytes)
            ]);

            // Validar clave de firma
            $this->validarClaveFirma($claveFirma);

            // Cargar XML de firmas
            $dom = new DOMDocument();
            $dom->loadXML($firmasXmlBytes);
            
            // Crear nueva firma
            $firmaNode = $this->crearNodoFirma($dom, $firma, $claveFirma);
            
            // Agregar a nodo de firmas existente
            $xpath = new DOMXPath($dom);
            $firmasNodes = $xpath->query('//firmas');
            
            if ($firmasNodes->length > 0) {
                $firmasNode = $firmasNodes->item(0);
                $firmasNode->appendChild($firmaNode);
                
                // Actualizar metadatos
                $this->actualizarMetadatos($dom, $firmasNode);
            } else {
                throw new Exception('No se encontró nodo de firmas en el XML');
            }
            
            $xmlResult = $dom->saveXML();
            
            Log::info('Firma agregada exitosamente', [
                'size' => strlen($xmlResult)
            ]);
            
            return $xmlResult;

        } catch (Exception $e) {
            Log::error('Error al agregar firma a XML', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Validar clave de firma
     */
    private function validarClaveFirma(string $claveFirma): void
    {
        if (strlen($claveFirma) < 10) {
            throw new Exception('Clave de firma inválida');
        }

        // Aquí se podrían agregar validaciones adicionales
        // como verificar formato, caracteres especiales, etc.
    }

    /**
     * Crear nodo de firma
     */
    private function crearNodoFirma(DOMDocument $dom, array $firma, string $claveFirma): DOMElement
    {
        $firmaNode = $dom->createElement('firma');
        
        // Agregar datos del firmante
        $firmaNode->setAttribute('id', $firma['id'] ?? uniqid());
        $firmaNode->setAttribute('nombre', $firma['nombre'] ?? '');
        $firmaNode->setAttribute('documento', $firma['documento'] ?? '');
        $firmaNode->setAttribute('cargo', $firma['cargo'] ?? '');
        $firmaNode->setAttribute('fecha', $firma['fecha'] ?? now()->format('Y-m-d H:i:s'));
        
        // Agregar firma digital (simulada)
        $firmaDigital = $this->generarFirmaDigital($firma, $claveFirma);
        $firmaNode->setAttribute('firma_digital', $firmaDigital);
        
        // Agregar timestamp
        $timestamp = $dom->createElement('timestamp');
        $timestamp->textContent = now()->toISOString();
        $firmaNode->appendChild($timestamp);
        
        return $firmaNode;
    }

    /**
     * Crear metadatos del documento
     */
    private function crearMetadatos(DOMDocument $dom, int $numFirmas): DOMElement
    {
        $metadatosNode = $dom->createElement('metadatos');
        
        $metadatosNode->setAttribute('version', '1.0');
        $metadatosNode->setAttribute('fecha_creacion', now()->format('Y-m-d H:i:s'));
        $metadatosNode->setAttribute('num_firmas', $numFirmas);
        $metadatosNode->setAttribute('algoritmo', 'SHA256');
        
        return $metadatosNode;
    }

    /**
     * Actualizar metadatos existentes
     */
    private function actualizarMetadatos(DOMDocument $dom, DOMElement $firmasNode): void
    {
        $xpath = new DOMXPath($dom);
        $metadatosNodes = $xpath->query('.//metadatos', $firmasNode);
        
        if ($metadatosNodes->length > 0) {
            $metadatosNode = $metadatosNodes->item(0);
            
            // Contar firmas actuales
            $firmaNodes = $xpath->query('.//firma', $firmasNode);
            $numFirmas = $firmaNodes->length;
            
            $metadatosNode->setAttribute('num_firmas', $numFirmas);
            $metadatosNode->setAttribute('fecha_actualizacion', now()->format('Y-m-d H:i:s'));
        }
    }

    /**
     * Generar firma digital (simulada)
     */
    private function generarFirmaDigital(array $firma, string $claveFirma): string
    {
        // En producción, esto debería usar una librería criptográfica real
        // Por ahora, simulamos una firma digital
        $datos = json_encode($firma) . $claveFirma;
        return hash('sha256', $datos);
    }

    /**
     * Validar estructura XML
     */
    public function validarEstructuraXml(string $xmlBytes): bool
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($xmlBytes);
            
            // Verificar que sea un XML válido
            if ($dom->documentElement === null) {
                return false;
            }
            
            // Verificar elementos requeridos
            $xpath = new DOMXPath($dom);
            $solicitud = $xpath->query('//solicitud');
            
            return $solicitud->length > 0;

        } catch (Exception $e) {
            Log::error('Error validando estructura XML', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Extraer información de firmas del XML
     */
    public function extraerInformacionFirmas(string $xmlBytes): array
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($xmlBytes);
            
            $xpath = new DOMXPath($dom);
            $firmaNodes = $xpath->query('//firma');
            
            $firmas = [];
            
            foreach ($firmaNodes as $firmaNode) {
                $firmas[] = [
                    'id' => $firmaNode->getAttribute('id'),
                    'nombre' => $firmaNode->getAttribute('nombre'),
                    'documento' => $firmaNode->getAttribute('documento'),
                    'cargo' => $firmaNode->getAttribute('cargo'),
                    'fecha' => $firmaNode->getAttribute('fecha'),
                    'firma_digital' => $firmaNode->getAttribute('firma_digital')
                ];
            }
            
            return $firmas;

        } catch (Exception $e) {
            Log::error('Error extrayendo información de firmas', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Verificar integridad de firmas
     */
    public function verificarIntegridadFirmas(string $xmlBytes): array
    {
        try {
            $firmas = $this->extraerInformacionFirmas($xmlBytes);
            $resultados = [];
            
            foreach ($firmas as $firma) {
                $isValid = $this->verificarFirmaIndividual($firma);
                
                $resultados[] = [
                    'firma_id' => $firma['id'],
                    'nombre' => $firma['nombre'],
                    'documento' => $firma['documento'],
                    'valida' => $isValid,
                    'mensaje' => $isValid ? 'Firma válida' : 'Firma inválida'
                ];
            }
            
            return $resultados;

        } catch (Exception $e) {
            Log::error('Error verificando integridad de firmas', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Verificar firma individual
     */
    private function verificarFirmaIndividual(array $firma): bool
    {
        // En producción, esto debería verificar la firma digital real
        // Por ahora, simulamos la verificación
        return !empty($firma['firma_digital']) && strlen($firma['firma_digital']) === 64;
    }
}
