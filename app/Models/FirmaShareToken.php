<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FirmaShareToken extends Model
{
    use HasFactory;

    protected $table = 'firma_share_tokens';

    protected $fillable = [
        'token',
        'solicitud_filename',
        'firmas_filename',
        'expires_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Verificar si el token está expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Verificar si el token es válido (no expirado)
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Obtener URL para compartir
     */
    public function getShareUrlAttribute(): string
    {
        return url('/firmas-share/' . $this->token);
    }

    /**
     * Scope para tokens activos (no expirados)
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope para tokens expirados
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }
}
