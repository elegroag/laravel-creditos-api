<?php

namespace App\Services;

use App\Models\Trabajador;
use App\Models\EmpresaConvenio;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Exceptions\ValidationException;

class TrabajadorService extends BaseService
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
     * Get worker from database or external API.
     */
    public function getWorker(string $cedula): ?Trabajador
    {
        // First try to get from database
        $trabajador = Trabajador::findByCedula($cedula);
        
        if ($trabajador) {
            // Optionally update from external API
            $this->updateFromExternalIfNeeded($trabajador);
            return $trabajador;
        }

        // If not found, try external API
        $externalData = $this->getWorkerData($cedula);
        
        if ($externalData) {
            return $this->createWorkerFromExternal($externalData);
        }

        return null;
    }

    /**
     * Create worker from external API data.
     */
    private function createWorkerFromExternal(array $externalData): Trabajador
    {
        $relevantData = $this->extractRelevantData($externalData);
        
        $trabajador = Trabajador::create([
            'cedula' => $relevantData['cedula'],
            'tipo_documento' => $relevantData['tipo_documento'],
            'primer_nombre' => $relevantData['primer_nombre'],
            'segundo_nombre' => $relevantData['segundo_nombre'],
            'primer_apellido' => $relevantData['primer_apellido'],
            'segundo_apellido' => $relevantData['segundo_apellido'],
            'direccion' => $relevantData['direccion'],
            'ciudad_codigo' => $relevantData['ciudad_codigo'],
            'telefono' => $relevantData['telefono'],
            'email' => $relevantData['email'],
            'salario' => $relevantData['salario'],
            'fecha_salario' => $this->parseDate($relevantData['fecha_salario']),
            'sexo' => $relevantData['sexo'],
            'estado_civil' => $relevantData['estado_civil'],
            'fecha_nacimiento' => $this->parseDate($relevantData['fecha_nacimiento']),
            'ciudad_nacimiento' => $relevantData['ciudad_nacimiento'],
            'nivel_educativo' => $relevantData['nivel_educativo'],
            'codigo_categoria' => $relevantData['codigo_categoria'],
            'estado' => $relevantData['estado'],
            'fecha_afiliacion' => $this->parseDate($relevantData['fecha_afiliacion']),
            'cargo' => $relevantData['cargo'],
            'empresa_nit' => $relevantData['empresa']['nit'] ?? null
        ]);

        // Create or update company
        if (isset($relevantData['empresa']['nit'])) {
            $this->createOrUpdateCompany($relevantData['empresa']);
        }

        $this->log('Worker created from external API', [
            'cedula' => $relevantData['cedula'],
            'nombre' => $trabajador->full_name
        ]);

        return $trabajador;
    }

    /**
     * Update worker from external API if needed.
     */
    private function updateFromExternalIfNeeded(Trabajador $trabajador): void
    {
        // Update if data is older than 24 hours
        if ($trabajador->updated_at->diffInHours(now()) < 24) {
            return;
        }

        $externalData = $this->getWorkerData($trabajador->cedula);
        
        if ($externalData) {
            $trabajador->updateFromExternalData($externalData);
            
            $this->log('Worker updated from external API', [
                'cedula' => $trabajador->cedula,
                'nombre' => $trabajador->full_name
            ]);
        }
    }

    /**
     * Create or update company from external data.
     */
    private function createOrUpdateCompany(array $companyData): void
    {
        EmpresaConvenio::updateOrCreate(
            ['nit' => $companyData['nit']],
            [
                'razon_social' => $companyData['razon_social'],
                'direccion' => $companyData['direccion'],
                'telefono' => $companyData['telefono'],
                'ciudad' => $companyData['ciudad_codigo'],
                'representante_documento' => $companyData['representante_cedula'],
                'representante_nombre' => $companyData['representante_legal'],
                'estado' => $companyData['estado'] ?? 'Activo'
            ]
        );
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
        $trabajador = $this->getWorker($cedula);
        
        if (!$trabajador) {
            return null;
        }

        return $trabajador->toApiArray();
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
     * Get worker eligibility for credit using database.
     */
    public function getWorkerEligibility(string $cedula): array
    {
        $trabajador = $this->getWorker($cedula);
        
        if (!$trabajador) {
            return [
                'eligible' => false,
                'reason' => 'Trabajador no encontrado',
                'worker_data' => null
            ];
        }

        $eligibility = $trabajador->getEligibilityDetails();
        
        return [
            'eligible' => $eligibility['eligible'],
            'reason' => $eligibility['eligible'] ? 'Trabajador elegible para crédito' : implode(', ', $eligibility['reasons']),
            'service_months' => $eligibility['service_months'],
            'required_months' => $eligibility['required_months'],
            'worker_data' => $trabajador->toApiArray()
        ];
    }

    /**
     * Search workers using database with external API fallback.
     */
    public function searchWorkers(array $criteria): array
    {
        // Try database first
        $query = Trabajador::query();

        if (isset($criteria['cedula'])) {
            $query->byDocument($criteria['cedula']);
        }

        if (isset($criteria['nombre'])) {
            $query->byName($criteria['nombre']);
        }

        if (isset($criteria['empresa_nit'])) {
            $query->byCompany($criteria['empresa_nit']);
        }

        if (isset($criteria['estado'])) {
            $query->where('estado', $criteria['estado']);
        }

        $workers = $query->with('empresa')->get();

        // If no results and external search is enabled, try external API
        if ($workers->isEmpty() && !empty($criteria['external_search'])) {
            return $this->searchWorkersExternal($criteria);
        }

        return $workers->map(fn($worker) => $worker->toApiArray())->toArray();
    }

    /**
     * Search workers using external API.
     */
    private function searchWorkersExternal(array $criteria): array
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

            $externalWorkers = $data['data'] ?? [];

            // Store in database and return
            return array_map(function ($worker) {
                $trabajador = $this->createWorkerFromExternal($worker);
                return $trabajador->toApiArray();
            }, $externalWorkers);

        } catch (\Exception $e) {
            $this->logError('Exception searching workers externally', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get worker statistics from database.
     */
    public function getWorkerStatistics(): array
    {
        try {
            return Trabajador::getStatistics();
        } catch (\Exception $e) {
            $this->logError('Exception getting worker statistics', [
                'error' => $e->getMessage()
            ]);
            return [
                'total' => 0,
                'activos' => 0,
                'inactivos' => 0,
                'por_categoria' => [],
                'por_empresa' => []
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
    private function parseDate(?string $date): ?\Carbon\Carbon
    {
        if (!$date) {
            return null;
        }

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

        try {
            return \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }
}
