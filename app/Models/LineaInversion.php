<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class LineaInversion extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'lineas_inversion';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'linea_credito',
        'monto_maximo_pesos',
        'plazo_maximo',
        'tasas_interes_anual',
        'requisitos',
        'categoria',
        'descripcion',
        'estado'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'monto_maximo_pesos' => 'integer',
            'plazo_maximo' => 'string',
            'tasas_interes_anual' => 'array',
            'requisitos' => 'array',
            'categoria' => 'string',
            'descripcion' => 'string',
            'estado' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the MongoDB primary key.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Categorías posibles
     */
    const CATEGORIAS = [
        'A' => 'Categoría A',
        'B' => 'Categoría B', 
        'C' => 'Categoría C'
    ];

    /**
     * Estados posibles
     */
    const ESTADOS = [
        'activa' => 'Activa',
        'inactiva' => 'Inactiva',
        'suspendida' => 'Suspendida'
    ];

    /**
     * Find linea by ID.
     */
    public static function findById(int $id): ?self
    {
        return static::where('id', $id)->first();
    }

    /**
     * Find by categoria.
     */
    public static function findByCategoria(string $categoria): array
    {
        return static::where('categoria', $categoria)->get()->all();
    }

    /**
     * Get active lines.
     */
    public static function getActive(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('estado', 'activa')->get();
    }

    /**
     * Get formatted monto maximo.
     */
    public function getMontoMaximoFormattedAttribute(): string
    {
        return '$' . number_format($this->monto_maximo_pesos, 0, ',', '.');
    }

    /**
     * Get tasa interes for categoria.
     */
    public function getTasaInteresByCategoria(string $categoria): ?string
    {
        $tasas = $this->tasas_interes_anual ?? [];
        
        return match($categoria) {
            'A' => $tasas['categoria_a'] ?? null,
            'B' => $tasas['categoria_b'] ?? null,
            'C' => $tasas['categoria_c'] ?? null,
            default => null
        };
    }

    /**
     * Get tasa interes categoria A.
     */
    public function getTasaCategoriaAAttribute(): ?string
    {
        return $this->getTasaInteresByCategoria('A');
    }

    /**
     * Get tasa interes categoria B.
     */
    public function getTasaCategoriaBAttribute(): ?string
    {
        return $this->getTasaInteresByCategoria('B');
    }

    /**
     * Get tasa interes categoria C.
     */
    public function getTasaCategoriaCAttribute(): ?string
    {
        return $this->getTasaInteresByCategoria('C');
    }

    /**
     * Get categoria label.
     */
    public function getCategoriaLabelAttribute(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? $this->categoria;
    }

    /**
     * Get estado label.
     */
    public function getEstadoLabelAttribute(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * Scope by categoria.
     */
    public function scopeByCategoria($query, string $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    /**
     * Scope active.
     */
    public function scopeActive($query)
    {
        return $query->where('estado', 'activa');
    }

    /**
     * Check if linea is active.
     */
    public function isActive(): bool
    {
        return $this->estado === 'activa';
    }

    /**
     * Get requisitos as formatted list.
     */
    public function getRequisitosFormattedAttribute(): array
    {
        return $this->requisitos ?? [];
    }

    /**
     * Get all tasas as array.
     */
    public function getTasasInteresArrayAttribute(): array
    {
        return [
            'A' => $this->tasa_categoria_a,
            'B' => $this->tasa_categoria_b,
            'C' => $this->tasa_categoria_c
        ];
    }

    /**
     * Create from array data.
     */
    public static function createFromArray(array $data): self
    {
        return static::create([
            'id' => $data['id'],
            'linea_credito' => $data['linea_credito'],
            'monto_maximo_pesos' => $data['monto_maximo_pesos'],
            'plazo_maximo' => $data['plazo_maximo'],
            'tasas_interes_anual' => $data['tasas_interes_anual'],
            'requisitos' => $data['requisitos'] ?? [],
            'categoria' => $data['categoria'] ?? 'A',
            'descripcion' => $data['descripcion'] ?? null,
            'estado' => $data['estado'] ?? 'activa'
        ]);
    }

    /**
     * Update from array data.
     */
    public function updateFromArray(array $data): bool
    {
        $updateData = [];
        
        foreach (['linea_credito', 'monto_maximo_pesos', 'plazo_maximo', 'tasas_interes_anual', 'requisitos', 'categoria', 'descripcion', 'estado'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        return $this->update($updateData);
    }

    /**
     * Get max plazo in months.
     */
    public function getPlazoMaximoMesesAttribute(): int
    {
        // Extract number from string like "24 meses" or "2 años"
        $plazo = strtolower($this->plazo_maximo);
        
        if (strpos($plazo, 'mes') !== false) {
            return (int) preg_replace('/[^0-9]/', '', $plazo);
        } elseif (strpos($plazo, 'año') !== false) {
            $years = (int) preg_replace('/[^0-9]/', '', $plazo);
            return $years * 12;
        }
        
        return 0;
    }

    /**
     * Check if monto is within limit.
     */
    public function isMontoWithinLimit(float $monto): bool
    {
        return $monto <= $this->monto_maximo_pesos;
    }

    /**
     * Check if plazo is within limit.
     */
    public function isPlazoWithinLimit(int $plazoMeses): bool
    {
        return $plazoMeses <= $this->plazo_maximo_meses;
    }
}
