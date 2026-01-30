<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NumeroSolicitud extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'numero_solicitudes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'radicado',
        'numeric_secuencia',
        'linea_credito',
        'vigencia'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numeric_secuencia' => 'integer',
            'vigencia' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get next sequence number.
     */
    public function getNextNumber(): int
    {
        return $this->numeric_secuencia + 1;
    }

    /**
     * Update sequence.
     */
    public function updateSequence(): void
    {
        $this->increment('numeric_secuencia');
    }

    /**
     * Reset sequence for new year.
     */
    public function resetForNewYearSequence(): void
    {
        $this->numeric_secuencia = 0;
        $this->save();
    }

    /**
     * Get formatted sequence with prefix.
     */
    public function getFormattedSequenceAttribute(): string
    {
        $year = substr($this->vigencia, 0, 4);
        $sequence = str_pad($this->numeric_secuencia, 4, '0', STR_PAD_LEFT);
        return "{$year}-{$sequence}";
    }

    /**
     * Generate radicado format.
     */
    public function generateRadicado(string $lineaCredito = '03'): string
    {
        $secuencia = str_pad($this->numeric_secuencia, 6, '0', STR_PAD_LEFT);
        $vigencia = $this->vigencia ?? (int) date('Ym');
        return "{$secuencia}-{$vigencia}-{$lineaCredito}";
    }

    /**
     * Get radicado attribute.
     */
    public function getRadicadoAttribute(): string
    {
        return $this->radicado ?? $this->generateRadicado($this->linea_credito ?? '03');
    }

    /**
     * Set radicado and update related fields.
     */
    public function setRadicadoAttribute(string $radicado): void
    {
        $this->attributes['radicado'] = $radicado;

        // Parse radicado format: 000001-202501-03
        $parts = explode('-', $radicado);
        if (count($parts) === 3) {
            $this->numeric_secuencia = (int) $parts[0];
            $this->vigencia = (int) $parts[1];
            $this->linea_credito = $parts[2];
        }
    }

    /**
     * Update sequence and radicado.
     */
    public function updateSequenceWithRadicado(string $lineaCredito = '03'): void
    {
        $this->increment('numeric_secuencia');
        $this->vigencia = (int) date('Ym');
        $this->linea_credito = $lineaCredito;
        $this->radicado = $this->generateRadicado($lineaCredito);
        $this->save();
    }

    /**
     * Create new sequence with radicado.
     */
    public static function createWithRadicado(string $lineaCredito = '03'): self
    {
        $sequence = static::create([
            'numeric_secuencia' => 0,
            'vigencia' => (int) date('Ym'),
            'linea_credito' => $lineaCredito
        ]);

        $sequence->updateSequenceWithRadicado($lineaCredito);

        return $sequence;
    }

    /**
     * Generate next radicado.
     */
    public static function generateNextRadicado(string $lineaCredito = '03'): string
    {
        $sequence = static::firstOrCreate(
            [],
            [
                'numeric_secuencia' => 0,
                'vigencia' => (int) date('Ym'),
                'linea_credito' => $lineaCredito
            ]
        );

        $sequence->updateSequenceWithRadicado($lineaCredito);

        return $sequence->radicado;
    }

    /**
     * Find or create sequence for year.
     */
    public static function findOrCreateForYear(int $year, string $lineaCredito = '03'): self
    {
        return static::firstOrCreate(
            ['vigencia' => $year],
            [
                'numeric_secuencia' => 0,
                'linea_credito' => $lineaCredito
            ]
        );
    }

    /**
     * Get current sequence for year.
     */
    public static function getCurrentSequence(int $year): ?self
    {
        return static::where('vigencia', $year)->first();
    }

    /**
     * Generate next application number.
     */
    public static function generateNextNumber(string $lineaCredito = '03'): string
    {
        $year = now()->year;
        $sequence = static::findOrCreateForYear($year, $lineaCredito);

        $sequence->updateSequence();

        return $sequence->formatted_sequence;
    }

    /**
     * Reset all sequences for new year.
     */
    public static function resetAllForNewYear(int $year): void
    {
        static::where('vigencia', $year)->update(['numeric_secuencia' => 0]);
    }

    /**
     * Get current year sequences.
     */
    public static function getCurrentYearSequences(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('vigencia', now()->format('Ym'))->get();
    }

    /**
     * Get sequence statistics.
     */
    public function getStatistics(): array
    {
        return [
            'vigencia' => $this->vigencia,
            'numeric_secuencia' => $this->numeric_secuencia,
            'siguiente_numero' => $this->getNextNumber(),
            'numero_formateado' => $this->formatted_sequence,
            'linea_credito' => $this->linea_credito,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Transform for API response.
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'radicado' => $this->radicado,
            'numeric_secuencia' => $this->numeric_secuencia,
            'linea_credito' => $this->linea_credito,
            'vigencia' => $this->vigencia,
            'siguiente_numero' => $this->getNextNumber(),
            'numero_formateado' => $this->formatted_sequence,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString()
        ];
    }

    /**
     * Get all sequences for API.
     */
    public static function getAllForApi(): array
    {
        return static::orderBy('anio', 'desc')
            ->get()
            ->map(function ($sequence) {
                return $sequence->toApiArray();
            })
            ->toArray();
    }

    /**
     * Get sequences by year for API.
     */
    public static function getByYearForApi(int $year): array
    {
        return static::where('vigencia', $year)
            ->orderBy('vigencia', 'desc')
            ->get()
            ->map(function ($sequence) {
                return $sequence->toApiArray();
            })
            ->toArray();
    }

    /**
     * Get sequence summary for year.
     */
    public static function getYearSummary(int $year): array
    {
        $sequence = static::getCurrentSequence($year);

        return [
            'vigencia' => $year,
            'ultimo_numero' => $sequence ? $sequence->numeric_secuencia : 0,
            'siguiente_numero' => $sequence ? $sequence->getNextNumber() : 1,
            'numero_actual_formateado' => $sequence ? $sequence->formatted_sequence : null,
            'total_solicitudes_generadas' => $sequence ? $sequence->numeric_secuencia : 0
        ];
    }
}
