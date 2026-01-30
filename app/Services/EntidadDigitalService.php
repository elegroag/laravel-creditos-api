<?php

namespace App\Services;

use App\Models\EntidadDigital;
use App\Repositories\EntidadDigitalRepository;
use App\Validators\SolicitudValidators;
use App\Exceptions\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EntidadDigitalService extends BaseService
{
    /**
     * Create or update entidad digital.
     */
    public function createOrUpdate(array $data): EntidadDigital
    {
        // Validate data
        $validator = SolicitudValidators::validateEntidadDigital($data);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        $validated = $validator->validated();

        try {
            // Check if entidad already exists
            $existing = $this->repository->findByDocument(
                $validated['tipo_identificacion'],
                $validated['numero_identificacion']
            );

            if ($existing && !($validated['overwrite'] ?? false)) {
                throw new ValidationException('La entidad digital ya existe para este documento');
            }

            // Process documents if provided
            $documentos = [];
            if (isset($validated['documentos'])) {
                $documentos = $this->processDocuments($validated);
            }

            // Process selfie if provided
            $selfiePath = null;
            if (isset($validated['selfie']) && !empty($validated['selfie'])) {
                $selfiePath = $this->processSelfie($validated);
            }

            // Create or update entidad
            $entidadData = [
                'username' => $validated['username'],
                'tipo_identificacion' => $validated['tipo_identificacion'],
                'numero_identificacion' => $validated['numero_identificacion'],
                'documentos' => $documentos,
                'selfie' => $selfiePath,
                'clave_firma_hash' => hash('sha256', $validated['clave_firma']),
                'estado' => 'activa',
                'metadata' => [
                    'directorio' => $this->generateDirectoryPath($validated['tipo_identificacion'], $validated['numero_identificacion'])
                ],
                'validaciones' => [],
                'created_at' => now(),
                'updated_at' => now(),
                'last_validation_at' => now()
            ];

            if ($existing) {
                $entidad = $this->repository->update($existing->id, $entidadData) ? $this->repository->findById($existing->id) : null;
            } else {
                $entidad = $this->repository->create($entidadData);
            }

            $this->log('Entidad digital created/updated successfully', [
                'username' => $validated['username'],
                'tipo_identificacion' => $validated['tipo_identificacion'],
                'numero_identificacion' => $validated['numero_identificacion']
            ]);

            return $entidad;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error creating/updating entidad digital', ['error' => $e->getMessage()]);
            throw new \Exception('Error al crear/actualizar entidad digital: ' . $e->getMessage());
        }
    }

    /**
     * Check if entidad exists by document.
     */
    public function existsByDocument(string $tipoIdentificacion, string $numeroIdentificacion): bool
    {
        try {
            return $this->repository->existsByDocument($tipoIdentificacion, $numeroIdentificacion);
        } catch (\Exception $e) {
            $this->logError('Error checking entidad existence', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get entidad by username.
     */
    public function getByUsername(string $username): ?EntidadDigital
    {
        try {
            return $this->repository->findByUsername($username);
        } catch (\Exception $e) {
            $this->logError('Error getting entidad by username', ['username' => $username, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get entidad by document.
     */
    public function getByDocument(string $tipoIdentificacion, string $numeroIdentificacion): ?EntidadDigital
    {
        try {
            return $this->repository->findByDocument($tipoIdentificacion, $numeroIdentificacion);
        } catch (\Exception $e) {
            $this->logError('Error getting entidad by document', [
                'tipo_identificacion' => $tipoIdentificacion,
                'numero_identificacion' => $numeroIdentificacion,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Add validation to entidad.
     */
    public function addValidation(string $username, string $tipoValidacion, string $resultado, array $detalles = []): bool
    {
        try {
            $entidad = $this->getByUsername($username);

            if (!$entidad) {
                throw new ValidationException('Entidad digital no encontrada');
            }

            $validationData = [
                'tipo' => $tipoValidacion,
                'resultado' => $resultado,
                'detalles' => $detalles,
                'timestamp' => now()->toISOString()
            ];

            $this->repository->addValidation($entidad->id, $validationData);

            $this->log('Validation added to entidad', [
                'username' => $username,
                'tipo_validacion' => $tipoValidacion,
                'resultado' => $resultado
            ]);

            return true;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error adding validation to entidad', ['username' => $username, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Save documents for entidad.
     */
    public function saveDocuments(string $username, array $documentos): array
    {
        try {
            $entidad = $this->getByUsername($username);

            if (!$entidad) {
                throw new ValidationException('Entidad digital no encontrada');
            }

            $savedDocuments = [];
            $directory = $entidad->metadata['directorio'] ?? $this->generateDirectoryPath(
                $entidad->tipo_identificacion,
                $entidad->numero_identificacion
            );

            foreach ($documentos as $tipo => $contenido) {
                if ($this->isValidBase64($contenido)) {
                    $filename = $this->generateDocumentFilename($tipo);
                    $path = "{$directory}/{$filename}";

                    // Decode and save base64 content
                    $decodedContent = base64_decode($contenido);
                    Storage::disk('public')->put($path, $decodedContent);

                    $savedDocuments[$tipo] = $path;

                    // Update entidad documents
                    $currentDocumentos = $entidad->documentos ?? [];
                    $currentDocumentos[$tipo] = $path;
                    $this->repository->update($entidad->id, ['documentos' => $currentDocumentos]);
                }
            }

            $this->log('Documents saved for entidad', [
                'username' => $username,
                'documents_count' => count($savedDocuments)
            ]);

            return $savedDocuments;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error saving documents for entidad', ['username' => $username, 'error' => $e->getMessage()]);
            throw new \Exception('Error al guardar documentos: ' . $e->getMessage());
        }
    }

    /**
     * Save selfie for entidad.
     */
    public function saveSelfie(string $username, string $selfieBase64): string
    {
        try {
            $entidad = $this->getByUsername($username);

            if (!$entidad) {
                throw new ValidationException('Entidad digital no encontrada');
            }

            if (!$this->isValidBase64($selfieBase64)) {
                throw new ValidationException('Formato de selfie inválido');
            }

            $directory = $entidad->metadata['directorio'] ?? $this->generateDirectoryPath(
                $entidad->tipo_identificacion,
                $entidad->numero_identificacion
            );

            $filename = 'selfie_' . now()->format('YmdHis') . '.jpg';
            $path = "{$directory}/{$filename}";

            // Decode and save base64 content
            $decodedContent = base64_decode($selfieBase64);
            Storage::disk('public')->put($path, $decodedContent);

            // Update entidad selfie
            $this->repository->update($entidad->id, ['selfie' => $path]);

            $this->log('Selfie saved for entidad', [
                'username' => $username,
                'path' => $path
            ]);

            return $path;
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error saving selfie for entidad', ['username' => $username, 'error' => $e->getMessage()]);
            throw new \Exception('Error al guardar selfie: ' . $e->getMessage());
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
                'has_complete_documents' => $entidad->hasCompleteDocuments(),
                'has_selfie' => $entidad->hasSelfie(),
                'is_complete' => $entidad->isComplete()
            ];
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError('Error getting documents for entidad', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
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
            $this->logError('Error deleting documents for entidad', ['username' => $username, 'error' => $e->getMessage()]);
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
            $this->logError('Error generating QR for entidad', [
                'tipo_identificacion' => $tipoIdentificacion,
                'numero_identificacion' => $numeroIdentificacion,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Error al generar QR: ' . $e->getMessage());
        }
    }

    /**
     * Get entidad statistics.
     */
    public function getStatistics(): array
    {
        try {
            return $this->repository->getStatistics();
        } catch (\Exception $e) {
            $this->logError('Error getting entidad statistics', ['error' => $e->getMessage()]);
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
     * Transform entidad for API response.
     */
    public function transformForApi($entidad): array
    {
        return [
            'id' => $entidad->id,
            'username' => $entidad->username,
            'tipo_identificacion' => $entidad->tipo_identificacion,
            'numero_identificacion' => $entidad->numero_identificacion,
            'full_identification' => $entidad->full_identification,
            'documentos' => $entidad->documentos,
            'selfie' => $entidad->selfie,
            'selfie_url' => $entidad->selfie_url,
            'estado' => $entidad->estado,
            'estado_label' => $entidad->estado_label,
            'is_active' => $entidad->isActive(),
            'is_complete' => $entidad->isComplete(),
            'has_complete_documents' => $entidad->hasCompleteDocuments(),
            'has_selfie' => $entidad->hasSelfie(),
            'document_count' => $entidad->document_count,
            'validation_count' => $entidad->validation_count,
            'successful_validations' => $entidad->successful_validations_count,
            'failed_validations' => $entidad->failed_validations_count,
            'latest_validation' => $entidad->latest_validation,
            'created_at' => $entidad->created_at->toISOString(),
            'updated_at' => $entidad->updated_at->toISOString(),
            'last_validation_at' => $entidad->last_validation_at?->toISOString()
        ];
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
     * Generate document filename.
     */
    private function generateDocumentFilename(string $tipo): string
    {
        $timestamp = now()->format('YmdHis');
        return "documento_{$tipo}_{$timestamp}.jpg";
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
