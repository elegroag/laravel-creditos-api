<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trabajador extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'trabajadores';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cedula',
        'tipo_documento',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'direccion',
        'ciudad_codigo',
        'telefono',
        'email',
        'salario',
        'fecha_salario',
        'sexo',
        'estado_civil',
        'fecha_nacimiento',
        'ciudad_nacimiento',
        'nivel_educativo',
        'codigo_categoria',
        'estado',
        'fecha_afiliacion',
        'cargo',
        'empresa_nit',
        'antiguedad_meses',
        'tipo_contrato',
        'sector_economico'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cedula' => 'integer',
            'salario' => 'decimal:2',
            'fecha_salario' => 'date',
            'fecha_nacimiento' => 'date',
            'fecha_afiliacion' => 'date',
            'antiguedad_meses' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the empresa that employs the trabajador.
     */
    public function empresa()
    {
        return $this->belongsTo(EmpresaConvenio::class, 'empresa_nit', 'nit');
    }

    /**
     * Get the solicitudes for the trabajador.
     */
    public function solicitudes()
    {
        return $this->hasMany(SolicitudSolicitante::class, 'numero_documento', 'cedula');
    }

    /**
     * Scope to get only active workers.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 'A');
    }

    /**
     * Scope to get workers by document.
     */
    public function scopeByDocument($query, string $cedula)
    {
        return $query->where('cedula', $cedula);
    }

    /**
     * Scope to get workers by company.
     */
    public function scopeByCompany($query, int $nit)
    {
        return $query->where('empresa_nit', $nit);
    }

    /**
     * Scope to search by name.
     */
    public function scopeByName($query, string $name)
    {
        return $query->where(function ($q) use ($name) {
            $q->where('primer_nombre', 'like', "%{$name}%")
              ->orWhere('segundo_nombre', 'like', "%{$name}%")
              ->orWhere('primer_apellido', 'like', "%{$name}%")
              ->orWhere('segundo_apellido', 'like', "%{$name}%");
        });
    }

    /**
     * Find worker by cedula.
     */
    public static function findByCedula(string $cedula): ?self
    {
        return static::where('cedula', $cedula)->first();
    }

    /**
     * Get full name.
     */
    public function getFullNameAttribute(): string
    {
        $names = [
            $this->primer_nombre,
            $this->segundo_nombre,
            $this->primer_apellido,
            $this->segundo_apellido
        ];

        return trim(implode(' ', array_filter($names)));
    }

    /**
     * Get formatted salary.
     */
    public function getSalarioFormattedAttribute(): string
    {
        return '$' . number_format($this->salario, 2, ',', '.');
    }

    /**
     * Get formatted cedula.
     */
    public function getCedulaFormattedAttribute(): string
    {
        return number_format($this->cedula, 0, '.', '.');
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
     * Get service time in months.
     */
    public function getTiempoServicioAttribute(): int
    {
        if (!$this->fecha_afiliacion) {
            return 0;
        }

        return max(0, now()->diffInMonths($this->fecha_afiliacion));
    }

    /**
     * Check if worker is eligible for credit.
     */
    public function isEligibleForCredit(): bool
    {
        return $this->estado === 'A' && 
               $this->tiempo_servicio >= 6 && 
               $this->salario > 0 &&
               $this->empresa && 
               $this->empresa->isActive();
    }

    /**
     * Get eligibility details.
     */
    public function getEligibilityDetails(): array
    {
        $reasons = [];
        $eligible = true;

        if ($this->estado !== 'A') {
            $eligible = false;
            $reasons[] = 'Trabajador no está activo';
        }

        if ($this->tiempo_servicio < 6) {
            $eligible = false;
            $reasons[] = 'Tiempo de servicio insuficiente (mínimo 6 meses)';
        }

        if (!$this->salario || $this->salario <= 0) {
            $eligible = false;
            $reasons[] = 'Salario no válido';
        }

        if (!$this->empresa) {
            $eligible = false;
            $reasons[] = 'Empresa no encontrada';
        } elseif (!$this->empresa->isActive()) {
            $eligible = false;
            $reasons[] = 'Empresa no está activa';
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'service_months' => $this->tiempo_servicio,
            'required_months' => 6,
            'salary' => $this->salario,
            'company_status' => $this->empresa?->estado
        ];
    }

    /**
     * Update from external API data.
     */
    public function updateFromExternalData(array $externalData): void
    {
        $this->update([
            'tipo_documento' => $externalData['coddoc'] ?? $this->tipo_documento,
            'primer_nombre' => $externalData['prinom'] ?? $this->primer_nombre,
            'segundo_nombre' => $externalData['segnom'] ?? $this->segundo_nombre,
            'primer_apellido' => $externalData['priape'] ?? $this->primer_apellido,
            'segundo_apellido' => $externalData['segape'] ?? $this->segundo_apellido,
            'direccion' => $externalData['direccion'] ?? $this->direccion,
            'ciudad_codigo' => $externalData['codciu'] ?? $this->ciudad_codigo,
            'telefono' => $externalData['telefono'] ?? $this->telefono,
            'email' => $externalData['email'] ?? $this->email,
            'salario' => $externalData['salario'] ?? $this->salario,
            'fecha_salario' => $this->parseDate($externalData['fecsal'] ?? null),
            'sexo' => $externalData['sexo'] ?? $this->sexo,
            'estado_civil' => $externalData['estciv'] ?? $this->estado_civil,
            'fecha_nacimiento' => $this->parseDate($externalData['fecnac'] ?? null),
            'ciudad_nacimiento' => $externalData['ciunac'] ?? $this->ciudad_nacimiento,
            'nivel_educativo' => $externalData['nivedu'] ?? $this->nivel_educativo,
            'codigo_categoria' => $externalData['codcat'] ?? $this->codigo_categoria,
            'estado' => $externalData['estado'] ?? $this->estado,
            'fecha_afiliacion' => $this->parseDate($externalData['fecafi'] ?? null),
            'cargo' => $externalData['cargo'] ?? $this->cargo,
            'empresa_nit' => $externalData['nit'] ?? $this->empresa_nit
        ]);

        // Update or create company if needed
        if (isset($externalData['nit'])) {
            $this->updateOrCreateCompany($externalData);
        }
    }

    /**
     * Update or create company from external data.
     */
    private function updateOrCreateCompany(array $externalData): void
    {
        $companyData = [
            'nit' => $externalData['nit'],
            'razon_social' => $externalData['empresa_razsoc'] ?? null,
            'direccion' => $externalData['empresa_direccion'] ?? null,
            'telefono' => $externalData['empresa_telefono'] ?? null,
            'ciudad' => $externalData['empresa_codciu'] ?? null,
            'representante_documento' => $externalData['empresa_cedrep'] ?? null,
            'representante_nombre' => $externalData['empresa_repleg'] ?? null,
            'estado' => $externalData['empresa_estado'] ?? 'Activo'
        ];

        EmpresaConvenio::updateOrCreate(['nit' => $externalData['nit']], array_filter($companyData));
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

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'cedula' => $this->cedula,
            'cedula_formatted' => $this->cedula_formatted,
            'tipo_documento' => $this->tipo_documento,
            'nombre_completo' => $this->full_name,
            'nombres' => [
                'primer_nombre' => $this->primer_nombre,
                'segundo_nombre' => $this->segundo_nombre,
                'primer_apellido' => $this->primer_apellido,
                'segundo_apellido' => $this->segundo_apellido
            ],
            'contacto' => [
                'direccion' => $this->direccion,
                'ciudad_codigo' => $this->ciudad_codigo,
                'telefono' => $this->telefono,
                'email' => $this->email
            ],
            'laboral' => [
                'salario' => $this->salario,
                'salario_formatted' => $this->salario_formatted,
                'fecha_salario' => $this->fecha_salario?->toISOString(),
                'cargo' => $this->cargo,
                'codigo_categoria' => $this->codigo_categoria,
                'antiguedad_meses' => $this->antiguedad_meses,
                'tiempo_servicio' => $this->tiempo_servicio,
                'tipo_contrato' => $this->tipo_contrato,
                'sector_economico' => $this->sector_economico
            ],
            'personal' => [
                'sexo' => $this->sexo,
                'estado_civil' => $this->estado_civil,
                'fecha_nacimiento' => $this->fecha_nacimiento?->toISOString(),
                'edad' => $this->edad,
                'ciudad_nacimiento' => $this->ciudad_nacimiento,
                'nivel_educativo' => $this->nivel_educativo
            ],
            'afiliacion' => [
                'fecha_afiliacion' => $this->fecha_afiliacion?->toISOString(),
                'estado' => $this->estado
            ],
            'empresa' => $this->empresa ? [
                'nit' => $this->empresa->nit,
                'razon_social' => $this->empresa->razon_social,
                'estado' => $this->empresa->estado
            ] : null,
            'elegibilidad' => $this->getEligibilityDetails(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get worker statistics.
     */
    public static function getStatistics(): array
    {
        $total = static::count();
        $activos = static::where('estado', 'A')->count();
        $inactivos = $total - $activos;

        $porCategoria = static::join('empresas_convenio', 'trabajadores.empresa_nit', '=', 'empresas_convenio.nit')
            ->groupBy('trabajadores.codigo_categoria')
            ->selectRaw('trabajadores.codigo_categoria as categoria, COUNT(*) as cantidad')
            ->orderBy('cantidad', 'desc')
            ->pluck('cantidad', 'categoria')
            ->toArray();

        $porEmpresa = static::join('empresas_convenio', 'trabajadores.empresa_nit', '=', 'empresas_convenio.nit')
            ->groupBy('trabajadores.empresa_nit', 'empresas_convenio.razon_social')
            ->selectRaw('trabajadores.empresa_nit as nit, empresas_convenio.razon_social, COUNT(*) as cantidad')
            ->orderBy('cantidad', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'nit' => $item->nit,
                    'razon_social' => $item->razon_social,
                    'cantidad' => $item->cantidad
                ];
            })
            ->toArray();

        return [
            'total' => $total,
            'activos' => $activos,
            'inactivos' => $inactivos,
            'por_categoria' => $porCategoria,
            'por_empresa' => $porEmpresa
        ];
    }
}
