<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class NumeroSolicitud extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'numero_solicitudes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sequence_value',
        'year',
        'prefix',
        'last_used_at',
        'created_at',
        'updated_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence_value' => 'integer',
            'year' => 'integer',
            'last_used_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Get the MongoDB primary key.
     */
    protected $primaryKey = '_id';

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
    protected $keyType = 'string';

    /**
     * Get or create sequence for current year.
     */
    public static function getCurrent(): self
    {
        $currentYear = (int)date('Y');
        
        $sequence = self::where('year', $currentYear)->first();
        
        if (!$sequence) {
            $sequence = self::create([
                'sequence_value' => 1,
                'year' => $currentYear,
                'prefix' => 'SOL-' . $currentYear . '-',
                'last_used_at' => now()
            ]);
        }
        
        return $sequence;
    }

    /**
     * Generate next solicitud number.
     */
    public static function generateNextNumber(): string
    {
        $sequence = self::getCurrent();
        
        // Increment sequence
        $sequence->increment('sequence_value');
        $sequence->update(['last_used_at' => now()]);
        
        // Format: SOL-YYYY-NNNNNN
        return $sequence->prefix . str_pad($sequence->sequence_value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get current sequence value.
     */
    public static function getCurrentSequence(): int
    {
        return self::getCurrent()->sequence_value;
    }

    /**
     * Reset sequence for year.
     */
    public static function resetForYear(int $year): void
    {
        self::updateOrCreate(
            ['year' => $year],
            [
                'sequence_value' => 1,
                'prefix' => 'SOL-' . $year . '-',
                'last_used_at' => now()
            ]
        );
    }

    /**
     * Get sequence by year.
     */
    public static function getByYear(int $year): ?self
    {
        return self::where('year', $year)->first();
    }

    /**
     * Get formatted number.
     */
    public function getFormattedNumberAttribute(): string
    {
        return $this->prefix . str_pad($this->sequence_value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get next number without incrementing.
     */
    public function getNextNumberAttribute(): string
    {
        return $this->prefix . str_pad($this->sequence_value + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get total numbers generated this year.
     */
    public function getTotalGeneratedAttribute(): int
    {
        return $this->sequence_value;
    }

    /**
     * Get year label.
     */
    public function getYearLabelAttribute(): string
    {
        return $this->year;
    }

    /**
     * Check if sequence is for current year.
     */
    public function isCurrentYear(): bool
    {
        return $this->year === (int)date('Y');
    }

    /**
     * Get days since last used.
     */
    public function getDaysSinceLastUsedAttribute(): int
    {
        return $this->last_used_at ? now()->diffInDays($this->last_used_at) : 0;
    }

    /**
     * Scope by year.
     */
    public function scopeByYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope current year.
     */
    public function scopeCurrentYear($query)
    {
        return $query->where('year', (int)date('Y'));
    }

    /**
     * Get statistics for all years.
     */
    public static function getStatistics(): array
    {
        $sequences = self::orderBy('year', 'desc')->get();
        
        return $sequences->map(function ($sequence) {
            return [
                'year' => $sequence->year,
                'total_generated' => $sequence->total_generated,
                'last_number' => $sequence->formatted_number,
                'last_used_at' => $sequence->last_used_at?->toISOString(),
                'days_since_last_used' => $sequence->days_since_last_used,
                'is_current_year' => $sequence->is_current_year
            ];
        })->toArray();
    }

    /**
     * Validate if number format is correct.
     */
    public static function isValidNumberFormat(string $number): bool
    {
        // Expected format: SOL-YYYY-NNNNNN
        return preg_match('/^SOL-\d{4}-\d{6}$/', $number);
    }

    /**
     * Extract year from number.
     */
    public static function extractYearFromNumber(string $number): ?int
    {
        if (!self::isValidNumberFormat($number)) {
            return null;
        }
        
        $parts = explode('-', $number);
        return (int)($parts[1] ?? null);
    }

    /**
     * Extract sequence from number.
     */
    public static function extractSequenceFromNumber(string $number): ?int
    {
        if (!self::isValidNumberFormat($number)) {
            return null;
        }
        
        $parts = explode('-', $number);
        return (int)($parts[2] ?? null);
    }

    /**
     * Check if number exists.
     */
    public static function numberExists(string $number): bool
    {
        $year = self::extractYearFromNumber($number);
        $sequence = self::extractSequenceFromNumber($number);
        
        if (!$year || !$sequence) {
            return false;
        }
        
        $yearSequence = self::getByYear($year);
        
        return $yearSequence && $sequence <= $yearSequence->sequence_value;
    }

    /**
     * Get next available number for specific year.
     */
    public static function getNextForYear(int $year): string
    {
        $sequence = self::getByYear($year);
        
        if (!$sequence) {
            self::resetForYear($year);
            $sequence = self::getByYear($year);
        }
        
        return $sequence->next_number;
    }

    /**
     * Create backup of current sequence.
     */
    public function createBackup(): array
    {
        return [
            'sequence_value' => $this->sequence_value,
            'year' => $this->year,
            'prefix' => $this->prefix,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'backup_date' => now()->toISOString()
        ];
    }

    /**
     * Restore from backup.
     */
    public function restoreFromBackup(array $backup): bool
    {
        try {
            $this->update([
                'sequence_value' => $backup['sequence_value'],
                'year' => $backup['year'],
                'prefix' => $backup['prefix'],
                'last_used_at' => $backup['last_used_at']
            ]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
