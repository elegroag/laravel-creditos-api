<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SolicitudSolicitante extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'solicitud_solicitante';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'solicitud_id',
        'tipo_persona',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'razon_social',
        'nit',
        'fecha_nacimiento',
        'genero',
        'estado_civil',
        'nivel_educativo',
        'profesion',
        'email',
        'telefono',
        'celular',
        'direccion',
        'barrio',
        'ciudad',
        'departamento',
        'cargo',
        'salario',
        'antiguedad_meses',
        'tipo_contrato',
        'sector_economico',
        'codigo_categoria'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'salario' => 'decimal:2',
            'antiguedad_meses' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the solicitud that owns the solicitante data.
     */
    public function solicitud()
    {
        return $this->belongsTo(SolicitudCredito::class, 'solicitud_id', 'numero_solicitud');
    }

    /**
     * Get the empresa if it's a legal person.
     */
    public function empresa()
    {
        return $this->belongsTo(EmpresaConvenio::class, 'empresa_nit', 'nit');
    }

    /**
     * Scope to get natural persons.
     */
    public function scopeNatural($query)
    {
        return $query->where('tipo_persona', 'NATURAL');
    }

    /**
     * Scope to get legal persons.
     */
    public function scopeJuridica($query)
    {
        return $query->where('tipo_persona', 'JURIDICA');
    }

    /**
     * Scope to search by document number.
     */
    public function scopeByDocument($query, string $documentNumber)
    {
        return $query->where('numero_documento', $documentNumber);
    }

    /**
     * Get full name for natural person.
     */
    public function getNombreCompletoAttribute(): string
    {
        if ($this->tipo_persona === 'NATURAL') {
            return trim(($this->nombres ?? '') . ' ' . ($this->apellidos ?? ''));
        }

        return $this->razon_social ?? '';
    }

    /**
     * Get formatted identification.
     */
    public function getIdentificacionFormateadaAttribute(): string
    {
        if ($this->tipo_persona === 'JURIDICA') {
            $nit = $this->nit ? number_format($this->nit, 0, '.', '.') : '';
            $dv = $this->digito_verificacion ? '-' . $this->digito_verificacion : '';
            return $nit . $dv;
        }

        return $this->numero_documento ?? '';
    }

    /**
     * Get age from birth date.
     */
    public function getEdadAttribute(): ?int
    {
        if (!$this->fecha_nacimiento) {
            return null;
        }

        return $this->fecha_nacimiento->age;
    }

    /**
     * Get formatted salary.
     */
    public function getSalarioFormateadoAttribute(): string
    {
        if (!$this->salario) {
            return '$0';
        }

        return '$' . number_format($this->salario, 2, ',', '.');
    }

    /**
     * Get formatted years of service.
     */
    public function getAntiguedadAniosAttribute(): float
    {
        if (!$this->antiguedad_meses) {
            return 0;
        }

        return round($this->antiguedad_meses / 12, 2);
    }

    /**
     * Get full address.
     */
    public function getDireccionCompletaAttribute(): string
    {
        $parts = array_filter([
            $this->direccion,
            $this->barrio,
            $this->ciudad,
            $this->departamento
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get contact information summary.
     */
    public function getContactoResumenAttribute(): array
    {
        return [
            'email' => $this->email,
            'telefono' => $this->telefono,
            'celular' => $this->celular,
            'direccion_completa' => $this->direccion_completa
        ];
    }

    /**
     * Get employment information summary.
     */
    public function getInformacionLaboralAttribute(): array
    {
        return [
            'empresa_nit' => $this->empresa_nit,
            'cargo' => $this->cargo,
            'salario' => $this->salario,
            'salario_formateado' => $this->salario_formateado,
            'antiguedad_meses' => $this->antiguedad_meses,
            'antiguedad_anios' => $this->antiguedad_anios,
            'tipo_contrato' => $this->tipo_contrato,
            'sector_economico' => $this->sector_economico
        ];
    }

    /**
     * Check if person is of legal age.
     */
    public function esMayorEdad(): bool
    {
        return $this->edad >= 18;
    }

    /**
     * Check if has valid email.
     */
    public function tieneEmailValido(): bool
    {
        return !empty($this->email) && filter_var($this->email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Check if has complete personal data.
     */
    public function tieneDatosPersonalesCompletos(): bool
    {
        if ($this->tipo_persona === 'NATURAL') {
            $camposRequeridos = ['nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'fecha_nacimiento'];
        } else {
            $camposRequeridos = ['razon_social', 'nit', 'digito_verificacion'];
        }

        foreach ($camposRequeridos as $campo) {
            if (empty($this->$campo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if has complete contact data.
     */
    public function tieneDatosContactoCompletos(): bool
    {
        return !empty($this->email) && !empty($this->telefono) && !empty($this->direccion);
    }

    /**
     * Check if has complete employment data.
     */
    public function tieneDatosLaboralesCompletos(): bool
    {
        return !empty($this->empresa_nit) && !empty($this->cargo) && !empty($this->salario);
    }

    /**
     * Validate required fields.
     */
    public function validarCampos(): array
    {
        $errores = [];

        if (!$this->tieneDatosPersonalesCompletos()) {
            $errores[] = 'Faltan datos personales obligatorios';
        }

        if (!$this->tieneDatosContactoCompletos()) {
            $errores[] = 'Faltan datos de contacto obligatorios';
        }

        if (!$this->tieneEmailValido()) {
            $errores[] = 'El correo electrónico no es válido';
        }

        if ($this->tipo_persona === 'NATURAL' && !$this->esMayorEdad()) {
            $errores[] = 'El solicitante debe ser mayor de edad';
        }

        return $errores;
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'solicitud_id' => $this->solicitud_id,
            'tipo_persona' => $this->tipo_persona,
            'identificacion' => [
                'tipo_documento' => $this->tipo_documento,
                'numero_documento' => $this->numero_documento,
                'identificacion_formateada' => $this->identificacion_formateada
            ],
            'datos_personales' => [
                'nombre_completo' => $this->nombre_completo,
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'razon_social' => $this->razon_social,
                'nit' => $this->nit,
                'digito_verificacion' => $this->digito_verificacion,
                'fecha_nacimiento' => $this->fecha_nacimiento?->toISOString(),
                'edad' => $this->edad,
                'genero' => $this->genero,
                'estado_civil' => $this->estado_civil,
                'nivel_educativo' => $this->nivel_educativo,
                'profesion' => $this->profesion
            ],
            'contacto' => $this->contacto_resumen,
            'direccion' => [
                'direccion' => $this->direccion,
                'barrio' => $this->barrio,
                'ciudad' => $this->ciudad,
                'departamento' => $this->departamento,
                'codigo_postal' => $this->codigo_postal,
                'direccion_completa' => $this->direccion_completa
            ],
            'informacion_laboral' => $this->informacion_laboral,
            'empresa' => $this->empresa ? [
                'nit' => $this->empresa->nit,
                'razon_social' => $this->empresa->razon_social
            ] : null,
            'validaciones' => [
                'es_mayor_edad' => $this->esMayorEdad(),
                'tiene_email_valido' => $this->tieneEmailValido(),
                'datos_personales_completos' => $this->tieneDatosPersonalesCompletos(),
                'datos_contacto_completos' => $this->tieneDatosContactoCompletos(),
                'datos_laborales_completos' => $this->tieneDatosLaboralesCompletos(),
                'errores_validacion' => $this->validarCampos()
            ],
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Create solicitante data for solicitud.
     */
    public static function crearSolicitante(int $solicitudId, array $datos): self
    {
        return static::create(array_merge($datos, ['solicitud_id' => $solicitudId]));
    }
}
