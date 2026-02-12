<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Postulacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Crear una notificación
     *
     * @param mixed $notifiable Usuario o entidad que recibe la notificación
     * @param string $type Tipo de notificación
     * @param array $data Datos adicionales
     * @return Notification|null
     */
    public function create($notifiable, string $type, array $data): ?Notification
    {
        try {
            // Determinar el tipo y ID del notifiable
            $notifiableType = is_array($notifiable) ? 'User' : get_class($notifiable);
            $notifiableId = is_array($notifiable)
                ? ($notifiable['username'] ?? $notifiable['id'] ?? 'unknown')
                : ($notifiable->username ?? $notifiable->id ?? 'unknown');

            $notification = new Notification([
                'id' => Str::uuid(),
                'type' => $type,
                'notifiable_type' => $notifiableType,
                'notifiable_id' => $notifiableId,
                'data' => $data,
                'read_at' => null
            ]);

            $notification->save();
            Log::info('Notificación creada', [
                'notification_id' => $notification->id,
                'type' => $type,
                'notifiable_id' => $notifiableId,
                'notifiable_type' => $notifiableType
            ]);
            return $notification;
        } catch (\Exception $e) {
            Log::error('Error al crear notificación', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Notificar sobre firma completada
     *
     * @param Postulacion $solicitud
     * @param array $additionalData
     * @return void
     */
    public function notifyFirmaCompletada(Postulacion $solicitud, array $additionalData = []): void
    {
        try {
            $ownerUsername = $solicitud->username;

            if (!$ownerUsername) {
                Log::warning('No se pudo notificar firma completada: owner_username no encontrado', [
                    'solicitud_id' => $solicitud->id
                ]);
                return;
            }

            $user = User::where('username', $ownerUsername)->first();

            if (!$user) {
                Log::warning('Usuario no encontrado para notificación', [
                    'username' => $ownerUsername,
                    'solicitud_id' => $solicitud->id
                ]);
                return;
            }

            $data = array_merge([
                'solicitud_id' => $solicitud->numero_solicitud,
                'titulo' => 'Documento Firmado Exitosamente',
                'mensaje' => 'El proceso de firma digital ha sido completado exitosamente.',
                'solicitante' => $solicitud->solicitante->nombres_apellidos ?? 'Sin nombre',
                'fecha_firma' => now()->toISOString(),
                'url' => "/admin/solicitudes/show/{$solicitud->numero_solicitud}"
            ], $additionalData);

            $this->create($user, 'firma_completada', $data);
        } catch (\Exception $e) {
            Log::error('Error al notificar firma completada', [
                'solicitud_id' => $solicitud->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notificar sobre firma rechazada
     *
     * @param Postulacion $solicitud
     * @param array $additionalData
     * @return void
     */
    public function notifyFirmaRechazada(Postulacion $solicitud, array $additionalData = []): void
    {
        try {
            $ownerUsername = $solicitud->username;

            if (!$ownerUsername) {
                return;
            }

            $user = User::where('username', $ownerUsername)->first();

            if (!$user) {
                return;
            }

            $data = array_merge([
                'solicitud_id' => $solicitud->numero_solicitud,
                'titulo' => 'Firma Rechazada',
                'mensaje' => 'El proceso de firma digital ha sido rechazado por uno o más firmantes.',
                'solicitante' => $solicitud->solicitante->nombres_apellidos ?? 'Sin nombre',
                'fecha' => now()->toISOString(),
                'url' => "/admin/solicitudes/show/{$solicitud->numero_solicitud}"
            ], $additionalData);

            $this->create($user, 'firma_rechazada', $data);
        } catch (\Exception $e) {
            Log::error('Error al notificar firma rechazada', [
                'solicitud_id' => $solicitud->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notificar sobre firma expirada
     *
     * @param Postulacion $solicitud
     * @param array $additionalData
     * @return void
     */
    public function notifyFirmaExpirada(Postulacion $solicitud, array $additionalData = []): void
    {
        try {
            $ownerUsername = $solicitud->username;

            if (!$ownerUsername) {
                return;
            }

            $user = User::where('username', $ownerUsername)->first();

            if (!$user) {
                return;
            }

            $data = array_merge([
                'solicitud_id' => $solicitud->numero_solicitud,
                'titulo' => 'Proceso de Firma Expirado',
                'mensaje' => 'El proceso de firma digital ha expirado sin completarse.',
                'solicitante' => $solicitud->solicitante->nombres_apellidos ?? 'Sin nombre',
                'fecha' => now()->toISOString(),
                'url' => "/admin/solicitudes/show/{$solicitud->numero_solicitud}"
            ], $additionalData);

            $this->create($user, 'firma_expirada', $data);
        } catch (\Exception $e) {
            Log::error('Error al notificar firma expirada', [
                'solicitud_id' => $solicitud->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notificar cambio de estado de solicitud
     *
     * @param Postulacion $solicitud
     * @param string $estadoAnterior
     * @param string $estadoNuevo
     * @return void
     */
    public function notifyEstadoActualizado(Postulacion $solicitud, string $estadoAnterior, string $estadoNuevo): void
    {
        try {
            $ownerUsername = $solicitud->username;

            if (!$ownerUsername) {
                return;
            }

            $user = User::where('username', $ownerUsername)->first();

            if (!$user) {
                return;
            }

            $data = [
                'solicitud_id' => $solicitud->numero_solicitud,
                'titulo' => 'Estado de Solicitud Actualizado',
                'mensaje' => "El estado de la solicitud ha cambiado de {$estadoAnterior} a {$estadoNuevo}.",
                'solicitante' => $solicitud->solicitante->nombres_apellidos ?? 'Sin nombre',
                'estado_anterior' => $estadoAnterior,
                'estado_nuevo' => $estadoNuevo,
                'fecha' => now()->toISOString(),
                'url' => "/admin/solicitudes/show/{$solicitud->numero_solicitud}"
            ];

            $this->create($user, 'estado_actualizado', $data);
        } catch (\Exception $e) {
            Log::error('Error al notificar estado actualizado', [
                'solicitud_id' => $solicitud->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener notificaciones de un usuario
     *
     * @param mixed $user
     * @param bool $onlyUnread
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserNotifications($user, bool $onlyUnread = false, int $limit = 50)
    {
        try {
            $notifiableType = is_array($user) ? 'User' : get_class($user);
            $notifiableId = is_array($user)
                ? ($user['username'] ?? $user['id'] ?? 'unknown')
                : ($user->id ?? $user->username ?? 'unknown');

            $query = Notification::where('notifiable_type', $notifiableType)
                ->where('notifiable_id', $notifiableId)
                ->recent();

            if ($onlyUnread) {
                $query->unread();
            }

            return $query->limit($limit)->get();
        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones de usuario', [
                'user_id' => is_array($user) ? ($user['username'] ?? $user['id'] ?? 'unknown') : ($user->username ?? $user->id ?? 'unknown'),
                'error' => $e->getMessage()
            ]);

            return collect();
        }
    }

    /**
     * Contar notificaciones no leídas de un usuario
     *
     * @param mixed $user
     * @return int
     */
    public function countUnread($user): int
    {
        try {
            $notifiableType = is_array($user) ? 'User' : get_class($user);
            $notifiableId = is_array($user)
                ? ($user['username'] ?? $user['id'] ?? 'unknown')
                : ($user->id ?? $user->username ?? 'unknown');

            return Notification::where('notifiable_type', $notifiableType)
                ->where('notifiable_id', $notifiableId)
                ->unread()
                ->count();
        } catch (\Exception $e) {
            Log::error('Error al contar notificaciones no leídas', [
                'user_id' => is_array($user) ? ($user['username'] ?? $user['id'] ?? 'unknown') : ($user->username ?? $user->id ?? 'unknown'),
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    /**
     * Marcar notificación como leída
     *
     * @param string $notificationId
     * @param mixed $user
     * @return bool
     */
    public function markAsRead(string $notificationId, $user): bool
    {
        try {
            $notifiableType = is_array($user) ? 'User' : get_class($user);
            $notifiableId = is_array($user)
                ? ($user['username'] ?? $user['id'] ?? 'unknown')
                : ($user->id ?? $user->username ?? 'unknown');

            $notification = Notification::where('id', $notificationId)
                ->where('notifiable_type', $notifiableType)
                ->where('notifiable_id', $notifiableId)
                ->first();

            if (!$notification) {
                return false;
            }

            $notification->markAsRead();
            return true;
        } catch (\Exception $e) {
            Log::error('Error al marcar notificación como leída', [
                'notification_id' => $notificationId,
                'user_id' => is_array($user) ? ($user['username'] ?? $user['id'] ?? 'unknown') : ($user->username ?? $user->id ?? 'unknown'),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     *
     * @param mixed $user
     * @return int Número de notificaciones marcadas
     */
    public function markAllAsRead($user): int
    {
        try {
            $notifiableType = is_array($user) ? 'User' : get_class($user);
            $notifiableId = is_array($user)
                ? ($user['username'] ?? $user['id'] ?? 'unknown')
                : ($user->id ?? $user->username ?? 'unknown');

            return Notification::where('notifiable_type', $notifiableType)
                ->where('notifiable_id', $notifiableId)
                ->unread()
                ->update(['read_at' => now()]);
        } catch (\Exception $e) {
            Log::error('Error al marcar todas las notificaciones como leídas', [
                'user_id' => is_array($user) ? ($user['username'] ?? $user['id'] ?? 'unknown') : ($user->username ?? $user->id ?? 'unknown'),
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    /**
     * Eliminar notificación
     *
     * @param string $notificationId
     * @param mixed $user
     * @return bool
     */
    public function delete(string $notificationId, $user): bool
    {
        try {
            $notifiableType = is_array($user) ? 'User' : get_class($user);
            $notifiableId = is_array($user)
                ? ($user['username'] ?? $user['id'] ?? 'unknown')
                : ($user->id ?? $user->username ?? 'unknown');

            $notification = Notification::where('id', $notificationId)
                ->where('notifiable_type', $notifiableType)
                ->where('notifiable_id', $notifiableId)
                ->first();

            if (!$notification) {
                return false;
            }

            $notification->delete();
            return true;
        } catch (\Exception $e) {
            Log::error('Error al eliminar notificación', [
                'notification_id' => $notificationId,
                'user_id' => is_array($user) ? ($user['username'] ?? $user['id'] ?? 'unknown') : ($user->username ?? $user->id ?? 'unknown'),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Eliminar notificaciones antiguas
     *
     * @param int $days Días de antigüedad
     * @return int Número de notificaciones eliminadas
     */
    public function deleteOld(int $days = 30): int
    {
        try {
            $date = now()->subDays($days);

            return Notification::where('created_at', '<', $date)
                ->whereNotNull('read_at')
                ->delete();
        } catch (\Exception $e) {
            Log::error('Error al eliminar notificaciones antiguas', [
                'days' => $days,
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }
}
