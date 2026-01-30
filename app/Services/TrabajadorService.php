<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Exceptions\ValidationException;

class TrabajadorService
{
    private string $externalApiUrl;
    private int $timeout;

    public function __construct()
    {
        $this->externalApiUrl = config('services.external_api.url', 'https://api.example.com');
        $this->timeout = config('services.external_api.timeout', 8);
    }

    /**
     * Extract relevant data from full worker data.
     */
    public function extractRelevantData(array $trabajadorFullData): ?array
    {
        if (empty($trabajadorFullData)) {
            return null;
        }

        return [
            'cedula' => $trabajadorFullData['cedtra'] ?? null,
            'tipo_documento' => $trabajadorFullData['coddoc'] ?? null,
            'primer_apellido' => $trabajadorFullData['priape'] ?? null,
            'segundo_apellido' => $trabajadorFullData['segape'] ?? null,
            'primer_nombre' => $trabajadorFullData['prinom'] ?? null,
            'segundo_nombre' => $trabajadorFullData['segnom'] ?? null,
            'direccion' => $trabajadorFullData['direccion'] ?? null,
            'ciudad_codigo' => $trabajadorFullData['codciu'] ?? null,
            'telefono' => $trabajadorFullData['telefono'] ?? null,
            'email' => $trabajadorFullData['email'] ?? null,
            'salario' => $trabajadorFullData['salario'] ?? null,
            'fecha_salario' => $trabajadorFullData['fecsal'] ?? null,
            'sexo' => $trabajadorFullData['sexo'] ?? null,
            'estado_civil' => $trabajadorFullData['estciv'] ?? null,
            'fecha_nacimiento' => $trabajadorFullData['fecnac'] ?? null,
            'ciudad_nacimiento' => $trabajadorFullData['ciunac'] ?? null,
            'nivel_educativo' => $trabajadorFullData['nivedu'] ?? null,
            'codigo_categoria' => $trabajadorFullData['codcat'] ?? null,
            'empresa' => [
                'nit' => $trabajadorFullData['nit'] ?? null,
                'razon_social' => $trabajadorFullData['empresa_razsoc'] ?? null,
                'direccion' => $trabajadorFullData['empresa_direccion'] ?? null,
                'telefono' => $trabajadorFullData['empresa_telefono'] ?? null,
                'ciudad_codigo' => $trabajadorFullData['empresa_codciu'] ?? null,
                'representante_legal' => $trabajadorFullData['empresa_repleg'] ?? null,
                'representante_cedula' => $trabajadorFullData['empresa_cedrep'] ?? null,
                'estado' => $trabajadorFullData['empresa_estado'] ?? null
            ],
            'estado' => $trabajadorFullData['estado'] ?? null,
            'fecha_afiliacion' => $trabajadorFullData['fecafi'] ?? null,
            'cargo' => $trabajadorFullData['cargo'] ?? null
        ];
    }

    /**
     * Get worker data from external API.
     */
    public function getWorkerData(string $cedula): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->externalApiUrl}/company/informacion_trabajador", [
                    'cedtra' => $cedula
                ]);

            if (!$response->successful()) {
                $this->logError('External API error', [
                    'cedula' => $cedula,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();

            if (!$data['success'] ?? false) {
                $this->logError('External API returned error', [
                    'cedula' => $cedula,
                    'error' => $data['error'] ?? 'Unknown error'
                ]);
                return null;
            }

            $this->log('Worker data retrieved successfully', [
                'cedula' => $cedula
            ]);

            return $data['data'] ?? null;

        } catch (\Exception $e) {
            $this->logError('Exception getting worker data', [
                'cedula' => $cedula,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get worker with relevant data extraction.
     */
    public function getWorkerRelevantData(string $cedula): ?array
    {
        $fullData = $this->getWorkerData($cedula);
        
        if (!$fullData) {
            return null;
        }

        return $this->extractRelevantData($fullData);
    }

    /**
     * Validate worker data.
     */
    public function validateWorkerData(array $workerData): array
    {
        $errors = [];

        // Required fields validation
        $requiredFields = ['cedula', 'primer_nombre', 'primer_apellido', 'estado'];
        
        foreach ($requiredFields as $field) {
            if (empty($workerData[$field])) {
                $errors[$field] = "El campo {$field} es requerido";
            }
        }

        // State validation
        if (!empty($workerData['estado']) && $workerData['estado'] !== 'A') {
            $errors['estado'] = 'El trabajador debe estar en estado Activo (A)';
        }

        // Document validation
        if (!empty($workerData['cedula']) && !$this->isValidDocument($workerData['cedula'])) {
            $errors['cedula'] = 'El número de cédula no es válido';
        }

        // Email validation
        if (!empty($workerData['email']) && !filter_var($workerData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'El correo electrónico no es válido';
        }

        // Phone validation
        if (!empty($workerData['telefono']) && !$this->isValidPhone($workerData['telefono'])) {
            $errors['telefono'] = 'El número de teléfono no es válido';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Calculate service time in months.
     */
    public function calculateServiceMonths(string $fechaAfiliacion): int
    {
        try {
            $fechaAfi = $this->parseDate($fechaAfiliacion);
            
            if (!$fechaAfi) {
                return 0;
            }

            $fechaActual = now();
            $diferencia = $fechaActual->diffInMonths($fechaAfi);
            
            return max(0, $diferencia);

        } catch (\Exception $e) {
            $this->logError('Error calculating service months', [
                'fecha_afiliacion' => $fechaAfiliacion,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Build full name from worker data.
     */
    public function buildFullName(array $workerData): string
    {
        $names = [
            $workerData['primer_nombre'] ?? '',
            $workerData['segundo_nombre'] ?? '',
            $workerData['primer_apellido'] ?? '',
            $workerData['segundo_apellido'] ?? ''
        ];

        return trim(implode(' ', array_filter($names)));
    }

    /**
     * Get worker eligibility for credit.
     */
    public function getWorkerEligibility(string $cedula): array
    {
        $workerData = $this->getWorkerRelevantData($cedula);
        
        if (!$workerData) {
            return [
                'eligible' => false,
                'reason' => 'Trabajador no encontrado',
                'worker_data' => null
            ];
        }

        $validation = $this->validateWorkerData($workerData);
        
        if (!$validation['valid']) {
            return [
                'eligible' => false,
                'reason' => 'Datos del trabajador inválidos',
                'errors' => $validation['errors'],
                'worker_data' => $workerData
            ];
        }

        // Check service time (minimum 6 months)
        $serviceMonths = $this->calculateServiceMonths($workerData['fecha_afiliacion'] ?? '');
        
        if ($serviceMonths < 6) {
            return [
                'eligible' => false,
                'reason' => 'Tiempo de servicio insuficiente',
                'service_months' => $serviceMonths,
                'required_months' => 6,
                'worker_data' => $workerData
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'Trabajador elegible para crédito',
            'service_months' => $serviceMonths,
            'worker_data' => $workerData
        ];
    }

    /**
     * Search workers by criteria.
     */
    public function searchWorkers(array $criteria): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->externalApiUrl}/company/buscar_trabajadores", $criteria);

            if (!$response->successful()) {
                $this->logError('Search workers API error', [
                    'criteria' => $criteria,
                    'status' => $response->status()
                ]);
                return [];
            }

            $data = $response->json();

            if (!$data['success'] ?? false) {
                return [];
            }

            $workers = $data['data'] ?? [];

            // Extract relevant data for each worker
            return array_map(function ($worker) {
                return $this->extractRelevantData($worker);
            }, $workers);

        } catch (\Exception $e) {
            $this->logError('Exception searching workers', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get worker statistics.
     */
    public function getWorkerStatistics(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->externalApiUrl}/company/estadisticas_trabajadores");

            if (!$response->successful()) {
                return [
                    'total' => 0,
                    'activos' => 0,
                    'inactivos' => 0,
                    'por_categoria' => []
                ];
            }

            $data = $response->json();

            return [
                'total' => $data['total'] ?? 0,
                'activos' => $data['activos'] ?? 0,
                'inactivos' => $data['inactivos'] ?? 0,
                'por_categoria' => $data['por_categoria'] ?? [],
                'por_empresa' => $data['por_empresa'] ?? []
            ];

        } catch (\Exception $e) {
            $this->logError('Exception getting worker statistics', [
                'error' => $e->getMessage()
            ]);
            return [
                'total' => 0,
                'activos' => 0,
                'inactivos' => 0,
                'por_categoria' => []
            ];
        }
    }

    /**
     * Validate document number.
     */
    private function isValidDocument(string $document): bool
    {
        // Basic validation for Colombian ID
        return preg_match('/^[0-9]{6,10}$/', $document);
    }

    /**
     * Validate phone number.
     */
    private function isValidPhone(string $phone): bool
    {
        // Basic validation for Colombian phone numbers
        return preg_match('/^[0-9]{7,10}$/', preg_replace('/[^0-9]/', '', $phone));
    }

    /**
     * Parse date from various formats.
     */
    private function parseDate(string $date): ?\Carbon\Carbon
    {
        $formats = [
            'Y-m-d',
            'Y/m/d',
            'd/m/Y',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sZ'
        ];

        foreach ($formats as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $date);
            } catch (\Exception $e) {
                continue;
            }
        }

        // Try ISO format as last resort
        try {
            return \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log error with context.
     */
    private function logError(string $message, array $context = []): void
    {
        Log::error("TrabajadorService: {$message}", array_merge([
            'service' => static::class,
            'timestamp' => now()->toISOString()
        ], $context));
    }

    /**
     * Log info with context.
     */
    private function log(string $message, array $context = []): void
    {
        Log::info("TrabajadorService: {$message}", array_merge([
            'service' => static::class,
            'timestamp' => now()->toISOString()
        ], $context));
    }
}
