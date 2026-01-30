<?php

namespace App\Services;

use App\Models\FirmaShareToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FirmaShareTokenService
{
    /**
     * Crear un nuevo token para compartir firmas
     */
    public function crearToken(string $solicitudFilename, ?string $firmasFilename, int $ttlDays = 3): FirmaShareToken
    {
        try {
            $token = $this->generarTokenUnico();
            $expiresAt = Carbon::now()->addDays($ttlDays);

            $tokenObj = FirmaShareToken::create([
                'token' => $token,
                'solicitud_filename' => $solicitudFilename,
                'firmas_filename' => $firmasFilename,
                'expires_at' => $expiresAt,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            Log::info('Token de firma creado', [
                'token' => $token,
                'solicitud_filename' => $solicitudFilename,
                'firmas_filename' => $firmasFilename,
                'expires_at' => $expiresAt
            ]);

            return $tokenObj;
        } catch (\Exception $e) {
            Log::error('Error al crear token de firma', [
                'error' => $e->getMessage(),
                'solicitud_filename' => $solicitudFilename,
                'firmas_filename' => $firmasFilename
            ]);

            throw $e;
        }
    }

    /**
     * Obtener token por su valor
     */
    public function obtenerToken(string $token): ?FirmaShareToken
    {
        try {
            $tokenObj = FirmaShareToken::where('token', $token)->first();

            if (!$tokenObj) {
                Log::warning('Token no encontrado', ['token' => $token]);
                return null;
            }

            // Verificar si ha expirado
            if ($tokenObj->expires_at && $tokenObj->expires_at->isPast()) {
                Log::warning('Token expirado', [
                    'token' => $token,
                    'expires_at' => $tokenObj->expires_at
                ]);
                return null;
            }

            return $tokenObj;
        } catch (\Exception $e) {
            Log::error('Error al obtener token de firma', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Actualizar el nombre del archivo de firmas
     */
    public function actualizarFirmasFilename(string $token, string $firmasFilename): bool
    {
        try {
            $actualizado = FirmaShareToken::where('token', $token)
                ->update([
                    'firmas_filename' => $firmasFilename,
                    'updated_at' => Carbon::now()
                ]);

            if ($actualizado) {
                Log::info('Nombre de archivo de firmas actualizado', [
                    'token' => $token,
                    'firmas_filename' => $firmasFilename
                ]);
            }

            return $actualizado;
        } catch (\Exception $e) {
            Log::error('Error al actualizar nombre de archivo de firmas', [
                'token' => $token,
                'firmas_filename' => $firmasFilename,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Eliminar tokens expirados
     */
    public function limpiarTokensExpirados(): int
    {
        try {
            $eliminados = FirmaShareToken::where('expires_at', '<', Carbon::now())->delete();

            if ($eliminados > 0) {
                Log::info('Tokens expirados eliminados', ['count' => $eliminados]);
            }

            return $eliminados;
        } catch (\Exception $e) {
            Log::error('Error al limpiar tokens expirados', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Generar un token único
     */
    private function generarTokenUnico(): string
    {
        do {
            $token = Str::random(32);
            $existe = FirmaShareToken::where('token', $token)->exists();
        } while ($existe);

        return $token;
    }

    /**
     * Obtener información del token incluyendo si está expirado
     */
    public function obtenerTokenInfo(string $token): ?array
    {
        try {
            $tokenObj = $this->obtenerToken($token);

            if (!$tokenObj) {
                return null;
            }

            return [
                'token' => $tokenObj->token,
                'solicitud_filename' => $tokenObj->solicitud_filename,
                'firmas_filename' => $tokenObj->firmas_filename,
                'expires_at' => $tokenObj->expires_at,
                'is_expired' => $tokenObj->expires_at ? $tokenObj->expires_at->isPast() : false,
                'created_at' => $tokenObj->created_at,
                'updated_at' => $tokenObj->updated_at
            ];
        } catch (\Exception $e) {
            Log::error('Error al obtener información del token', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Revocar un token manualmente
     */
    public function revocarToken(string $token): bool
    {
        try {
            $eliminado = FirmaShareToken::where('token', $token)->delete();

            if ($eliminado) {
                Log::info('Token revocado', ['token' => $token]);
            }

            return $eliminado;
        } catch (\Exception $e) {
            Log::error('Error al revocar token', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Obtener todos los tokens activos
     */
    public function obtenerTokensActivos(): array
    {
        try {
            return FirmaShareToken::where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            })
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($token) {
                    return [
                        'token' => $token->token,
                        'solicitud_filename' => $token->solicitud_filename,
                        'firmas_filename' => $token->firmas_filename,
                        'expires_at' => $token->expires_at,
                        'created_at' => $token->created_at,
                        'updated_at' => $token->updated_at
                    ];
                })
                ->toArray();
        } catch (\Exception $e) {
            Log::error('Error al obtener tokens activos', [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
}
