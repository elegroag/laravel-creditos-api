<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'notifications';

    protected $fillable = [
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at'
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime'
        ];
    }

    /**
     * Relación polimórfica con el usuario que recibe la notificación
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Marcar notificación como leída
     */
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * Marcar notificación como no leída
     */
    public function markAsUnread(): void
    {
        if (!is_null($this->read_at)) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    /**
     * Verificar si la notificación ha sido leída
     */
    public function read(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Verificar si la notificación no ha sido leída
     */
    public function unread(): bool
    {
        return is_null($this->read_at);
    }

    /**
     * Scope para obtener solo notificaciones no leídas
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope para obtener solo notificaciones leídas
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para ordenar por más recientes
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Tipos de notificaciones disponibles
     */
    public static function getAvailableTypes(): array
    {
        return [
            'firma_completada' => 'Proceso de firma completado',
            'firma_rechazada' => 'Proceso de firma rechazado',
            'firma_expirada' => 'Proceso de firma expirado',
            'solicitud_aprobada' => 'Solicitud aprobada',
            'solicitud_rechazada' => 'Solicitud rechazada',
            'documento_requerido' => 'Documento adicional requerido',
            'estado_actualizado' => 'Estado de solicitud actualizado',
            'comentario_nuevo' => 'Nuevo comentario en solicitud',
            'recordatorio' => 'Recordatorio de acción pendiente'
        ];
    }

    /**
     * Obtener nombre descriptivo del tipo
     */
    public function getTypeNameAttribute(): string
    {
        $types = self::getAvailableTypes();
        return $types[$this->type] ?? $this->type;
    }
}
