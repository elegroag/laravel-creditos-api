<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TipoDocumento extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tipo_documentos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tipo',
        'detalle',
        'orden',
        'activo'
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
            'activo' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Scope para obtener solo tipos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para ordenar por campo orden
     */
    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden', 'asc');
    }

    /**
     * Obtener documentos de este tipo
     */
    public function documentosPostulantes()
    {
        return $this->hasMany(DocumentoPostulante::class, 'tipo_documento_id');
    }

    /**
     * Obtener tipo por key (tipo)
     */
    public static function porTipo(string $tipo): ?self
    {
        return static::where('tipo', $tipo)->first();
    }

    /**
     * Obtener todos los tipos activos ordenados
     */
    public static function activosOrdenados()
    {
        return static::activos()->ordenados()->get();
    }
}
