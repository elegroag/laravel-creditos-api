<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirmanteSolicitud extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'firmantes_solicitud';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'solicitud_id',
        'orden',
        'tipo',
        'nombre_completo',
        'numero_documento',
        'email',
        'rol'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the solicitud that owns the firmante.
     */
    public function solicitud()
    {
        return $this->belongsTo(SolicitudCredito::class, 'solicitud_id', 'numero_solicitud');
    }

    /**
     * Scope to get firmantes by type.
     */
    public function scopeByType($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope to get firmantes by role.
     */
    public function scopeByRole($query, string $rol)
    {
        return $query->where('rol', $rol);
    }

    /**
     * Scope to get firmantes ordered by order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('orden');
    }

    /**
     * Find firmantes by solicitud.
     */
    public static function findBySolicitud(string $solicitudId): array
    {
        return static::where('solicitud_id', $solicitudId)
            ->ordered()
            ->get()
            ->toArray();
    }

    /**
     * Get primary firmante (orden 1).
     */
    public static function getPrimaryFirmante(string $solicitudId): ?self
    {
        return static::where('solicitud_id', $solicitudId)
            ->where('orden', 1)
            ->first();
    }

    /**
     * Check if firmante is primary.
     */
    public function isPrimary(): bool
    {
        return $this->orden === 1;
    }

    /**
     * Check if firmante has complete data.
     */
    public function hasCompleteData(): bool
    {
        return !empty($this->nombre_completo) && 
               !empty($this->numero_documento) && 
               !empty($this->email);
    }

    /**
     * Get formatted name.
     */
    public function getNombreFormateadoAttribute(): string
    {
        return $this->nombre_completo ?: 'Sin nombre';
    }

    /**
     * Get masked document number.
     */
    public function getDocumentoEnmascaradoAttribute(): string
    {
        if (empty($this->numero_documento)) {
            return 'Sin documento';
        }
        
        $doc = $this->numero_documento;
        $length = strlen($doc);
        
        if ($length <= 4) {
            return $doc;
        }
        
        return substr($doc, 0, 2) . str_repeat('*', $length - 4) . substr($doc, -2);
    }

    /**
     * Get masked email.
     */
    public function getEmailEnmascaradoAttribute(): string
    {
        if (empty($this->email)) {
            return 'Sin email';
        }
        
        $email = $this->email;
        $parts = explode('@', $email);
        
        if (count($parts) !== 2) {
            return $email;
        }
        
        $local = $parts[0];
        $domain = $parts[1];
        
        if (strlen($local) <= 2) {
            return $local . '@' . $domain;
        }
        
        return substr($local, 0, 2) . str_repeat('*', strlen($local) - 2) . '@' . $domain;
    }

    /**
     * Add firmante to solicitud.
     */
    public static function addFirmante(string $solicitudId, array $data): self
    {
        // Get the next order
        $lastOrder = static::where('solicitud_id', $solicitudId)
            ->max('orden') ?? 0;
        
        return static::create(array_merge($data, [
            'solicitud_id' => $solicitudId,
            'orden' => $lastOrder + 1
        ]));
    }

    /**
     * Remove firmante from solicitud.
     */
    public static function removeFirmante(string $solicitudId, int $orden): bool
    {
        $firmante = static::where('solicitud_id', $solicitudId)
            ->where('orden', $orden)
            ->first();
        
        if ($firmante) {
            $firmante->delete();
            
            // Reorder remaining firmantes
            static::where('solicitud_id', $solicitudId)
                ->where('orden', '>', $orden)
                ->decrement('orden');
            
            return true;
        }
        
        return false;
    }

    /**
     * Update firmante order.
     */
    public static function reorderFirmantes(string $solicitudId, array $orders): bool
    {
        try {
            foreach ($orders as $ordenActual => $nuevoOrden) {
                static::where('solicitud_id', $solicitudId)
                    ->where('orden', $ordenActual)
                    ->update(['orden' => $nuevoOrden]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'orden' => $this->orden,
            'tipo' => $this->tipo,
            'nombre_completo' => $this->nombre_completo,
            'numero_documento' => $this->getDocumentoEnmascaradoAttribute(),
            'email' => $this->getEmailEnmascaradoAttribute(),
            'rol' => $this->rol,
            'is_primary' => $this->isPrimary(),
            'has_complete_data' => $this->hasCompleteData(),
            'nombre_formateado' => $this->getNombreFormateadoAttribute(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get all firmantes by solicitud for API.
     */
    public static function getBySolicitudForApi(string $solicitudId): array
    {
        return static::where('solicitud_id', $solicitudId)
            ->ordered()
            ->get()
            ->map(function ($firmante) {
                return $firmante->toApiArray();
            })
            ->toArray();
    }

    /**
     * Get firmantes statistics.
     */
    public static function getStatistics(): array
    {
        $total = static::count();
        $byType = [];
        $byRole = [];
        
        // Group by type
        $tipos = static::distinct('tipo')->pluck('tipo');
        foreach ($tipos as $tipo) {
            $byType[$tipo] = static::where('tipo', $tipo)->count();
        }
        
        // Group by role
        $roles = static::distinct('rol')->pluck('rol');
        foreach ($roles as $rol) {
            $byRole[$rol] = static::where('rol', $rol)->count();
        }
        
        return [
            'total' => $total,
            'by_type' => $byType,
            'by_role' => $byRole,
            'tipos_disponibles' => $tipos,
            'roles_disponibles' => $roles
        ];
    }

    /**
     * Validate firmante data.
     */
    public static function validateFirmanteData(array $data): array
    {
        $errors = [];
        
        if (empty($data['nombre_completo'])) {
            $errors[] = 'El nombre completo es requerido';
        }
        
        if (empty($data['numero_documento'])) {
            $errors[] = 'El número de documento es requerido';
        }
        
        if (empty($data['email'])) {
            $errors[] = 'El email es requerido';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido';
        }
        
        if (empty($data['rol'])) {
            $errors[] = 'El rol es requerido';
        }
        
        return $errors;
    }
}
