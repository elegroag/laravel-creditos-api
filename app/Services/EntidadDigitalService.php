<?php

namespace App\Services;

use App\Models\EntidadDigital;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class EntidadDigitalService extends EloquentService
{
    /**
     * Create or update entidad digital.
     */
    public function createOrUpdate(array $data): EntidadDigital
    {
        // Validate data
        $validator = Validator::make($data, [
            'tipo_identificacion' => 'required|string|max:50',
            'numero_identificacion' => 'required|string|max:50',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'username' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:500',
            'ciudad' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'pais' => 'nullable|string|max:100',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|string|max:20',
            'estado_civil' => 'nullable|string|max:50',
            'actividad_economica' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:255',
            'ingresos_mensuales' => 'nullable|numeric|min:0',
            'activo' => 'nullable|boolean',
            'overwrite' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        $validated = $validator->validated();

        try {
            // Check if entidad already exists
            $existing = EntidadDigital::where('tipo_identificacion', $validated['tipo_identificacion'])
                ->where('numero_identificacion', $validated['numero_identificacion'])
                ->first();

            if ($existing && !($validated['overwrite'] ?? false)) {
                throw new ValidationException('La entidad digital ya existe para este documento');
            }

            // Prepare entidad data
            $entidadData = [
                'tipo_identificacion' => $validated['tipo_identificacion'],
                'numero_identificacion' => $validated['numero_identificacion'],
                'nombres' => $validated['nombres'] ?? '',
                'apellidos' => $validated['apellidos'] ?? '',
                'username' => $validated['username'] ?? null,
                'email' => $validated['email'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'ciudad' => $validated['ciudad'] ?? null,
                'departamento' => $validated['departamento'] ?? null,
                'pais' => $validated['pais'] ?? 'Colombia',
                'fecha_nacimiento' => $validated['fecha_nacimiento'] ?? null,
                'genero' => $validated['genero'] ?? null,
                'estado_civil' => $validated['estado_civil'] ?? null,
                'actividad_economica' => $validated['actividad_economica'] ?? null,
                'cargo' => $validated['cargo'] ?? null,
                'ingresos_mensuales' => $validated['ingresos_mensuales'] ?? null,
                'activo' => $validated['activo'] ?? true,
                'validado' => false,
                'fecha_validacion' => null,
                'documentos' => $validated['documentos'] ?? [],
                'selfie' => $validated['selfie'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ];

            if ($existing) {
                $entidad = tap($existing)->update($entidadData);
            } else {
                $entidad = EntidadDigital::create($entidadData);
            }

            $this->log('Entidad digital created/updated successfully', [
                'tipo_identificacion' => $validated['tipo_identificacion'],
                'numero_identificacion' => $validated['numero_identificacion'],
                'username' => $validated['username'] ?? null
            ]);

            return $entidad;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'creación/actualización de entidad digital');
            throw new \Exception('Error al procesar entidad digital: ' . $e->getMessage());
        }
    }

    /**
     * Check if entidad exists by document.
     */
    public function existsByDocument(string $tipoIdentificacion, string $numeroIdentificacion): bool
    {
        try {
            return EntidadDigital::where('tipo_identificacion', $tipoIdentificacion)
                ->where('numero_identificacion', $numeroIdentificacion)
                ->exists();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'verificación de existencia de entidad');
            return false;
        }
    }

    /**
     * Get entidad by username.
     */
    public function getByUsername(string $username): ?EntidadDigital
    {
        try {
            return EntidadDigital::where('username', $username)->first();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de entidad por username');
            return null;
        }
    }

    /**
     * Get entidad by document.
     */
    public function getByDocument(string $tipoIdentificacion, string $numeroIdentificacion): ?EntidadDigital
    {
        try {
            return EntidadDigital::where('tipo_identificacion', $tipoIdentificacion)
                ->where('numero_identificacion', $numeroIdentificacion)
                ->first();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de entidad por documento');
            return null;
        }
    }

    /**
     * Get entidad by ID.
     */
    public function findById(int $id): ?EntidadDigital
    {
        try {
            return EntidadDigital::find($id);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'búsqueda de entidad por ID');
            return null;
        }
    }

    /**
     * Update entidad.
     */
    public function update(int $id, array $data): bool
    {
        try {
            $entidad = EntidadDigital::find($id);
            if (!$entidad) {
                return false;
            }
            return $entidad->update($data);
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'actualización de entidad');
            return false;
        }
    }

    /**
     * Delete entidad.
     */
    public function delete(int $id): bool
    {
        try {
            $entidad = EntidadDigital::find($id);
            if (!$entidad) {
                return false;
            }
            return $entidad->delete();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación de entidad');
            return false;
        }
    }

    /**
     * Get all entidades.
     */
    public function getAll(): \Illuminate\Support\Collection
    {
        try {
            return EntidadDigital::orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de entidades');
            return collect([]);
        }
    }

    /**
     * Get active entidades.
     */
    public function getActive(): \Illuminate\Support\Collection
    {
        try {
            return EntidadDigital::where('activo', true)
                ->orderBy('created_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de entidades activas');
            return collect([]);
        }
    }

    /**
     * Get statistics.
     */
    public function getStatistics(): array
    {
        try {
            $total = EntidadDigital::count();
            $complete = EntidadDigital::where('estado', 'activa')->count();
            $incomplete = $total - $complete;

            // Get by estado
            $byEstado = EntidadDigital::selectRaw('estado, COUNT(*) as count')
                ->groupBy('estado')
                ->pluck('count', 'estado')
                ->toArray();

            $completionRate = $total > 0 ? round(($complete / $total) * 100, 2) : 0;

            return [
                'total' => $total,
                'complete' => $complete,
                'incomplete' => $incomplete,
                'by_estado' => $byEstado,
                'completion_rate' => $completionRate
            ];
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de estadísticas de entidades');
            return [
                'total' => 0,
                'complete' => 0,
                'incomplete' => 0,
                'by_estado' => [],
                'completion_rate' => 0
            ];
        }
    }

    /**
     * Get documents for entidad.
     */
    public function getDocuments(string $username, string $tipoIdentificacion, string $numeroIdentificacion): array
    {
        try {
            $entidad = $this->getByDocument($tipoIdentificacion, $numeroIdentificacion);

            if (!$entidad) {
                throw new ValidationException('Entidad digital no encontrada');
            }

            $documents = [];
            $base64Documents = [];

            // Get document URLs and convert to base64 if needed
            foreach ($entidad->documentos ?? [] as $tipo => $path) {
                if (Storage::disk('public')->exists($path)) {
                    $content = Storage::disk('public')->get($path);
                    $base64Content = base64_encode($content);

                    $documents[$tipo] = $path;
                    $base64Documents[$tipo] = $base64Content;
                }
            }

            return [
                'documents' => $documents,
                'base64_documents' => $base64Documents,
                'has_complete_documents' => $this->hasCompleteDocuments($entidad),
                'has_selfie' => $this->hasSelfie($entidad),
                'is_complete' => $this->isComplete($entidad)
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'obtención de documentos de entidad');
            throw new \Exception('Error al obtener documentos: ' . $e->getMessage());
        }
    }

    /**
     * Delete documents for entidad.
     */
    public function deleteDocuments(string $username, string $tipoIdentificacion, string $numeroIdentificacion): bool
    {
        try {
            $entidad = $this->getByDocument($tipoIdentificacion, $numeroIdentificacion);

            if (!$entidad) {
                throw new ValidationException('Entidad digital no encontrada');
            }

            // Delete files from storage
            foreach ($entidad->documentos ?? [] as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Clear documents from entidad
            $entidad->update(['documentos' => []]);

            $this->log('Documents deleted for entidad', [
                'username' => $username
            ]);

            return true;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'eliminación de documentos de entidad');
            return false;
        }
    }

    /**
     * Generate QR code for entidad.
     */
    public function generateQR(string $tipoIdentificacion, string $numeroIdentificacion): string
    {
        try {
            $entidad = $this->getByDocument($tipoIdentificacion, $numeroIdentificacion);

            if (!$entidad) {
                throw new ValidationException('Entidad digital no encontrada');
            }

            // Generate QR data
            $qrData = json_encode([
                'tipo_identificacion' => $entidad->tipo_identificacion,
                'numero_identificacion' => $entidad->numero_identificacion,
                'username' => $entidad->username,
                'estado' => $entidad->estado,
                'generated_at' => now()->toISOString()
            ]);

            // Generate QR code (you might want to use a QR library here)
            $qrCode = base64_encode($qrData); // Simplified for now

            $this->log('QR code generated for entidad', [
                'tipo_identificacion' => $tipoIdentificacion,
                'numero_identificacion' => $numeroIdentificacion
            ]);

            return $qrCode;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->handleDatabaseError($e, 'generación de QR para entidad');
            throw new \Exception('Error al generar QR: ' . $e->getMessage());
        }
    }

    /**
     * Transform entidad for API response.
     */
    public function transformForApi($entidad): array
    {
        return [
            'id' => $entidad->id,
            'username' => $entidad->username,
            'tipo_identificacion' => $entidad->tipo_identificacion,
            'numero_identificacion' => $entidad->numero_identificacion,
            'full_identification' => $entidad->tipo_identificacion . ' ' . $entidad->numero_identificacion,
            'documentos' => $entidad->documentos,
            'selfie' => $entidad->selfie,
            'selfie_url' => $entidad->selfie ? asset('storage/' . $entidad->selfie) : null,
            'estado' => $entidad->estado,
            'estado_label' => ucfirst($entidad->estado),
            'is_active' => $entidad->estado === 'activa',
            'is_complete' => $this->isComplete($entidad),
            'has_complete_documents' => $this->hasCompleteDocuments($entidad),
            'has_selfie' => $this->hasSelfie($entidad),
            'document_count' => count($entidad->documentos ?? []),
            'validation_count' => 0, // TODO: Implement validation tracking
            'successful_validations' => 0,
            'failed_validations' => 0,
            'latest_validation' => null,
            'created_at' => $entidad->created_at->toISOString(),
            'updated_at' => $entidad->updated_at->toISOString(),
            'last_validation_at' => $entidad->updated_at->toISOString()
        ];
    }

    /**
     * Check if entidad has complete documents.
     */
    private function hasCompleteDocuments($entidad): bool
    {
        $requiredDocuments = ['frente', 'reverso'];
        $entidadDocuments = array_keys($entidad->documentos ?? []);

        return count(array_intersect($requiredDocuments, $entidadDocuments)) >= 2;
    }

    /**
     * Check if entidad has selfie.
     */
    private function hasSelfie($entidad): bool
    {
        return !empty($entidad->selfie);
    }

    /**
     * Check if entidad is complete.
     */
    private function isComplete($entidad): bool
    {
        return $this->hasCompleteDocuments($entidad) && $this->hasSelfie($entidad);
    }

    /**
     * Process documents from validated data.
     */
    private function processDocuments(array $data): array
    {
        $documents = [];

        if (isset($data['documentos']['frente'])) {
            $documents['frente'] = $data['documentos']['frente'];
        }

        if (isset($data['documentos']['reverso'])) {
            $documents['reverso'] = $data['documentos']['reverso'];
        }

        return $documents;
    }

    /**
     * Process selfie from validated data.
     */
    private function processSelfie(array $data): ?string
    {
        return $data['selfie'] ?? null;
    }

    /**
     * Generate directory path for documents.
     */
    private function generateDirectoryPath(string $tipo, string $numero): string
    {
        $tipoSafe = Str::slug($tipo);
        $numeroSafe = Str::slug($numero);
        return "documentos/{$tipoSafe}-{$numeroSafe}";
    }

    /**
     * Check if string is valid base64.
     */
    private function isValidBase64(string $string): bool
    {
        $decoded = base64_decode($string, true);
        return $decoded !== false;
    }
}
