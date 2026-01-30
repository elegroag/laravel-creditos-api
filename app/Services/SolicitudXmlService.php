<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use SimpleXMLElement;
use DOMDocument;
use DOMXPath;
use Exception;

class SolicitudXmlService
{
    /**
     * Construir XML de solicitud de crédito
     */
    public function buildSolicitudCreditoXml(array $data): string
    {
        try {
            Log::info('Construyendo XML de solicitud de crédito', [
                'data_keys' => array_keys($data)
            ]);

            // Validar datos de entrada
            $this->validarDatosSolicitud($data);

            // Crear estructura XML
            $xml = new SimpleXMLElement('<solicitud_credito/>');

            // Agregar datos de solicitud
            if (isset($data['solicitud']) && is_array($data['solicitud'])) {
                $solicitudNode = $xml->addChild('solicitud');
                $this->agregarDatosSolicitud($solicitudNode, $data['solicitud']);
            }

            // Agregar datos del solicitante
            if (isset($data['solicitante']) && is_array($data['solicitante'])) {
                $solicitanteNode = $xml->addChild('solicitante');
                $this->agregarDatosSolicitante($solicitanteNode, $data['solicitante']);
            }

            // Agregar datos adicionales
            if (isset($data['adicional']) && is_array($data['adicional'])) {
                $adicionalNode = $xml->addChild('adicional');
                $this->agregarDatosAdicional($adicionalNode, $data['adicional']);
            }

            // Agregar metadatos
            $metadatosNode = $xml->addChild('metadatos');
            $metadatosNode->addChild('fecha_generacion', now()->toISOString());
            $metadatosNode->addChild('version', '1.0');
            $metadatosNode->addChild('sistema', 'comfaca-credito');

            // Formatear XML
            $dom = dom_import_simplexml($xml);
            $dom->ownerDocument->formatOutput = true;
            $xmlString = $dom->ownerDocument->saveXML();

            Log::info('XML de solicitud construido exitosamente', [
                'xml_length' => strlen($xmlString)
            ]);

            return $xmlString;

        } catch (\Exception $e) {
            Log::error('Error al construir XML de solicitud', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception("No fue posible generar el XML: {$e->getMessage()}");
        }
    }

    /**
     * Extraer datos de XML de solicitud de crédito
     */
    public function extractSolicitudCreditoDataFromXml(string $xmlBytes, bool $validate = true): array
    {
        try {
            Log::info('Extrayendo datos de XML de solicitud', [
                'validate' => $validate,
                'xml_length' => strlen($xmlBytes)
            ]);

            // Validar XML si se solicita
            if ($validate) {
                $this->validarEstructuraXml($xmlBytes);
            }

            // Cargar XML
            $xml = simplexml_load_string($xmlBytes);
            
            if (!$xml) {
                throw new \ValueError('XML inválido o malformado');
            }

            $data = [];

            // Extraer datos de solicitud
            if (isset($xml->solicitud)) {
                $data['solicitud'] = $this->extraerDatosSolicitud($xml->solicitud);
            }

            // Extraer datos del solicitante
            if (isset($xml->solicitante)) {
                $data['solicitante'] = $this->extraerDatosSolicitante($xml->solicitante);
            }

            // Extraer datos adicionales
            if (isset($xml->adicional)) {
                $data['adicional'] = $this->extraerDatosAdicional($xml->adicional);
            }

            // Extraer metadatos
            if (isset($xml->metadatos)) {
                $data['metadatos'] = $this->extraerMetadatos($xml->metadatos);
            }

            Log::info('Datos extraídos exitosamente del XML', [
                'data_keys' => array_keys($data)
            ]);

            return $data;

        } catch (\ValueError $e) {
            Log::error('Error de validación en XML', [
                'error' => $e->getMessage(),
                'xml_length' => strlen($xmlBytes)
            ]);

            throw $e;

        } catch (\Exception $e) {
            Log::error('Error al extraer datos de XML', [
                'error' => $e->getMessage(),
                'xml_length' => strlen($xmlBytes),
                'trace' => $e->getTraceAsString()
            ]);

            throw new Exception("No fue posible extraer el XML: {$e->getMessage()}");
        }
    }

    /**
     * Validar estructura XML
     */
    public function validarEstructuraXml(string $xmlBytes): bool
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($xmlBytes);

            if (!$dom) {
                return false;
            }

            // Validar que tenga el elemento raíz correcto
            $root = $dom->documentElement;
            if ($root->nodeName !== 'solicitud_credito') {
                return false;
            }

            // Validar elementos requeridos
            $xpath = new DOMXPath($dom);
            
            // Verificar que exista al menos solicitud o solicitante
            $solicitud = $xpath->query('//solicitud');
            $solicitante = $xpath->query('//solicitante');
            
            if ($solicitud->length === 0 && $solicitante->length === 0) {
                return false;
            }

            Log::info('Estructura XML validada exitosamente');

            return true;

        } catch (\Exception $e) {
            Log::error('Error al validar estructura XML', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Validar datos de solicitud
     */
    private function validarDatosSolicitud(array $data): void
    {
        $validator = Validator::make($data, [
            'solicitud.numero_solicitud' => 'sometimes|string|max:50',
            'solicitud.tipcre' => 'sometimes|string|max:10',
            'solicitud.valor_solicitado' => 'sometimes|numeric|min:0',
            'solicitud.valor_solicitud' => 'sometimes|numeric|min:0',
            'solicitud.plazo_meses' => 'sometimes|integer|min:1|max:360',
            'solicitante.tipo_identificacion' => 'sometimes|string|max:10',
            'solicitante.numero_identificacion' => 'sometimes|string|max:20',
            'solicitante.nombres_apellidos' => 'sometimes|string|max:200',
            'solicitante.email' => 'sometimes|email|max:255',
            'solicitante.telefono_movil' => 'sometimes|string|max:20'
        ]);

        if ($validator->fails()) {
            throw new \Exception('Datos de solicitud inválidos: ' . implode(', ', $validator->errors()->all()));
        }
    }

    /**
     * Agregar datos de solicitud al XML
     */
    private function agregarDatosSolicitud(SimpleXMLElement $node, array $data): void
    {
        $campos = [
            'numero_solicitud' => 'numero_solicitud',
            'tipcre' => 'tipo_credito',
            'valor_solicitado' => 'valor_solicitado',
            'valor_solicitud' => 'valor_solicitud',
            'plazo_meses' => 'plazo_meses',
            'tasa_interes' => 'tasa_interes',
            'cuota_mensual' => 'cuota_mensual',
            'fecha_solicitud' => 'fecha_solicitud'
        ];

        foreach ($campos as $campo => $xmlCampo) {
            if (isset($data[$campo]) && $data[$campo] !== '') {
                $node->addChild($xmlCampo, htmlspecialchars($data[$campo]));
            }
        }
    }

    /**
     * Agregar datos del solicitante al XML
     */
    private function agregarDatosSolicitante(SimpleXMLElement $node, array $data): void
    {
        $campos = [
            'tipo_identificacion' => 'tipo_identificacion',
            'numero_identificacion' => 'numero_identificacion',
            'nombres_apellidos' => 'nombres_apellidos',
            'primer_nombre' => 'primer_nombre',
            'segundo_nombre' => 'segundo_nombre',
            'primer_apellido' => 'primer_apellido',
            'segundo_apellido' => 'segundo_apellido',
            'email' => 'email',
            'telefono_movil' => 'telefono_movil',
            'telefono_fijo' => 'telefono_fijo',
            'direccion' => 'direccion',
            'ciudad' => 'ciudad',
            'departamento' => 'departamento',
            'codigo_postal' => 'codigo_postal'
        ];

        foreach ($campos as $campo => $xmlCampo) {
            if (isset($data[$campo]) && $data[$campo] !== '') {
                $node->addChild($xmlCampo, htmlspecialchars($data[$campo]));
            }
        }
    }

    /**
     * Agregar datos adicionales al XML
     */
    private function agregarDatosAdicional(SimpleXMLElement $node, array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $childNode = $node->addChild($key);
                $this->agregarDatosAdicional($childNode, $value);
            } else {
                $node->addChild($key, htmlspecialchars($value));
            }
        }
    }

    /**
     * Extraer datos de solicitud del XML
     */
    private function extraerDatosSolicitud(SimpleXMLElement $node): array
    {
        $data = [];
        
        foreach ($node->children() as $child) {
            $data[$child->getName()] = (string) $child;
        }
        
        return $data;
    }

    /**
     * Extraer datos del solicitante del XML
     */
    private function extraerDatosSolicitante(SimpleXMLElement $node): array
    {
        $data = [];
        
        foreach ($node->children() as $child) {
            $data[$child->getName()] = (string) $child;
        }
        
        return $data;
    }

    /**
     * Extraer datos adicionales del XML
     */
    private function extraerDatosAdicional(SimpleXMLElement $node): array
    {
        $data = [];
        
        foreach ($node->children() as $child) {
            if ($child->count() > 0) {
                $data[$child->getName()] = $this->extraerDatosAdicional($child);
            } else {
                $data[$child->getName()] = (string) $child;
            }
        }
        
        return $data;
    }

    /**
     * Extraer metadatos del XML
     */
    private function extraerMetadatos(SimpleXMLElement $node): array
    {
        $data = [];
        
        foreach ($node->children() as $child) {
            $data[$child->getName()] = (string) $child;
        }
        
        return $data;
    }

    /**
     * Generar XML de ejemplo para testing
     */
    public function generarXmlEjemplo(): string
    {
        $ejemplo = [
            'solicitud' => [
                'numero_solicitud' => '000001-202501-03',
                'tipcre' => '03',
                'valor_solicitado' => 15000000,
                'plazo_meses' => 60,
                'tasa_interes' => '12%',
                'fecha_solicitud' => now()->format('Y-m-d')
            ],
            'solicitante' => [
                'tipo_identificacion' => 'CC',
                'numero_identificacion' => '12345678',
                'nombres_apellidos' => 'Juan Pérez García',
                'email' => 'juan@ejemplo.com',
                'telefono_movil' => '3001234567',
                'direccion' => 'Calle 123 #45-67',
                'ciudad' => 'Bogotá',
                'departamento' => 'Cundinamarca'
            ],
            'adicional' => [
                'referencias' => [
                    'personal' => 'María López',
                    'laboral' => 'Empresa XYZ'
                ],
                'observaciones' => 'Solicitud para compra de vivienda'
            ]
        ];

        return $this->buildSolicitudCreditoXml($ejemplo);
    }

    /**
     * Validar y limpiar XML
     */
    public function limpiarXml(string $xmlBytes): string
    {
        try {
            // Eliminar espacios en blanco y caracteres no deseados
            $xmlBytes = trim($xmlBytes);
            
            // Eliminar BOM si existe
            $bom = pack('H*', 'EFBBBF');
            if (substr($xmlBytes, 0, 3) === $bom) {
                $xmlBytes = substr($xmlBytes, 3);
            }
            
            // Validar que sea XML válido
            $dom = new DOMDocument();
            $dom->loadXML($xmlBytes);
            
            // Formatear XML
            $dom->formatOutput = true;
            return $dom->saveXML();

        } catch (\Exception $e) {
            Log::error('Error al limpiar XML', [
                'error' => $e->getMessage()
            ]);
            
            throw new Exception('No fue posible limpiar el XML: ' . $e->getMessage());
        }
    }

    /**
     * Comparar dos XMLs
     */
    public function compararXmls(string $xml1, string $xml2): array
    {
        try {
            $data1 = $this->extractSolicitudCreditoDataFromXml($xml1, false);
            $data2 = $this->extractSolicitudCreditoDataFromXml($xml2, false);
            
            $diferencias = $this->encontrarDiferencias($data1, $data2);
            
            return [
                'iguales' => empty($diferencias),
                'diferencias' => $diferencias,
                'data1' => $data1,
                'data2' => $data2
            ];

        } catch (\Exception $e) {
            Log::error('Error al comparar XMLs', [
                'error' => $e->getMessage()
            ]);
            
            throw new Exception('No fue posible comparar los XMLs: ' . $e->getMessage());
        }
    }

    /**
     * Encontrar diferencias entre dos arrays
     */
    private function encontrarDiferencias(array $array1, array $array2, string $prefix = ''): array
    {
        $diferencias = [];
        
        foreach ($array1 as $key => $value) {
            $currentPrefix = $prefix ? $prefix . '.' . $key : $key;
            
            if (!array_key_exists($key, $array2)) {
                $diferencias[$currentPrefix] = [
                    'tipo' => 'eliminado',
                    'valor_anterior' => $value,
                    'valor_nuevo' => null
                ];
            } elseif (is_array($value) && is_array($array2[$key])) {
                $diferencias = array_merge($diferencias, $this->encontrarDiferencias($value, $array2[$key], $currentPrefix));
            } elseif ($value !== $array2[$key]) {
                $diferencias[$currentPrefix] = [
                    'tipo' => 'modificado',
                    'valor_anterior' => $value,
                    'valor_nuevo' => $array2[$key]
                ];
            }
        }
        
        foreach ($array2 as $key => $value) {
            $currentPrefix = $prefix ? $prefix . '.' . $key : $key;
            
            if (!array_key_exists($key, $array1)) {
                $diferencias[$currentPrefix] = [
                    'tipo' => 'agregado',
                    'valor_anterior' => null,
                    'valor_nuevo' => $value
                ];
            }
        }
        
        return $diferencias;
    }
}
