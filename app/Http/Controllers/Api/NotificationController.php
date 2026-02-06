<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ErrorResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Obtener todas las notificaciones del usuario autenticado
     *
     * GET /api/notifications
     *
     * Query params:
     * - unread: boolean (opcional) - Solo notificaciones no leídas
     * - limit: int (opcional) - Límite de resultados (default: 50)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser($request);

            $onlyUnread = $request->query('unread', false);
            $limit = min((int) $request->query('limit', 50), 100);

            $notifications = $this->notificationService->getUserNotifications(
                $user,
                filter_var($onlyUnread, FILTER_VALIDATE_BOOLEAN),
                $limit
            );

            $unreadCount = $this->notificationService->countUnread($user);

            return ApiResource::success([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'total' => $notifications->count()
            ], 'Notificaciones obtenidas exitosamente')->response();

        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ErrorResource::serverError('Error al obtener notificaciones', [
                'error' => $e->getMessage()
            ])->response();
        }
    }

    /**
     * Obtener contador de notificaciones no leídas
     *
     * GET /api/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser($request);

            $count = $this->notificationService->countUnread($user);

            return ApiResource::success([
                'unread_count' => $count
            ], 'Contador obtenido')->response();

        } catch (\Exception $e) {
            Log::error('Error al obtener contador de notificaciones', [
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error al obtener contador')->response();
        }
    }

    /**
     * Marcar una notificación como leída
     *
     * PUT /api/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser($request);

            $success = $this->notificationService->markAsRead($id, $user);

            if (!$success) {
                return ErrorResource::notFound('Notificación no encontrada')->response();
            }

            return ApiResource::success([
                'notification_id' => $id,
                'marked_as_read' => true
            ], 'Notificación marcada como leída')->response();

        } catch (\Exception $e) {
            Log::error('Error al marcar notificación como leída', [
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error al marcar notificación')->response();
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     *
     * PUT /api/notifications/mark-all-read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser($request);

            $count = $this->notificationService->markAllAsRead($user);

            return ApiResource::success([
                'marked_count' => $count
            ], "Se marcaron {$count} notificaciones como leídas")->response();

        } catch (\Exception $e) {
            Log::error('Error al marcar todas las notificaciones como leídas', [
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error al marcar notificaciones')->response();
        }
    }

    /**
     * Eliminar una notificación
     *
     * DELETE /api/notifications/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser($request);

            $success = $this->notificationService->delete($id, $user);

            if (!$success) {
                return ErrorResource::notFound('Notificación no encontrada')->response();
            }

            return ApiResource::success([
                'notification_id' => $id,
                'deleted' => true
            ], 'Notificación eliminada')->response();

        } catch (\Exception $e) {
            Log::error('Error al eliminar notificación', [
                'notification_id' => $id,
                'error' => $e->getMessage()
            ]);

            return ErrorResource::serverError('Error al eliminar notificación')->response();
        }
    }

    /**
     * Obtener usuario autenticado desde el request
     */
    private function getAuthenticatedUser(Request $request): array
    {
        $authenticatedUser = $request->get('authenticated_user');
        $userData = $authenticatedUser['user'] ?? [];

        if (!$userData || !isset($userData['username'])) {
            throw new \Exception('Usuario no autenticado');
        }

        return $userData;
    }
}
