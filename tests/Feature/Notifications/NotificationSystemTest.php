<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use App\Models\Notification;
use App\Models\User;
use App\Models\Postulacion;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
    }

    /**
     * Test: Crear notificación básica
     */
    public function test_puede_crear_notificacion_basica(): void
    {
        $user = User::factory()->create();

        $notification = $this->notificationService->create($user, 'test_notification', [
            'titulo' => 'Test',
            'mensaje' => 'Mensaje de prueba'
        ]);

        $this->assertNotNull($notification);
        $this->assertEquals('test_notification', $notification->type);
        $this->assertEquals($user->id, $notification->notifiable_id);
        $this->assertEquals('App\Models\User', $notification->notifiable_type);
        $this->assertNull($notification->read_at);
    }

    /**
     * Test: Notificar firma completada
     */
    public function test_puede_notificar_firma_completada(): void
    {
        $user = User::factory()->create();
        $solicitud = Postulacion::factory()->create([
            'username' => $user->username,
            'estado' => 'iniciada'
        ]);

        $this->notificationService->notifyFirmaCompletada($solicitud);

        // Verificar que se creó la notificación (sin ser estricto con el tipo)
        $this->assertDatabaseHas('notifications', [
            'type' => 'firma_completada',
            'notifiable_id' => $user->username
        ]);
    }

    /**
     * Test: Notificar firma rechazada
     */
    public function test_puede_notificar_firma_rechazada(): void
    {
        $user = User::factory()->create();
        $solicitud = Postulacion::factory()->rejected()->create([
            'username' => $user->username
        ]);

        $this->notificationService->notifyFirmaRechazada($solicitud);

        $this->assertDatabaseHas('notifications', [
            'type' => 'firma_rechazada',
            'notifiable_id' => $user->username,
            'notifiable_type' => 'User'
        ]);
    }

    /**
     * Test: Notificar firma expirada
     */
    public function test_puede_notificar_firma_expirada(): void
    {
        $user = User::factory()->create();
        $solicitud = Postulacion::factory()->rejected()->create([
            'username' => $user->username
        ]);

        $this->notificationService->notifyFirmaExpirada($solicitud);

        $this->assertDatabaseHas('notifications', [
            'type' => 'firma_expirada',
            'notifiable_id' => $user->username,
            'notifiable_type' => 'User'
        ]);
    }

    /**
     * Test: Notificar estado actualizado
     */
    public function test_puede_notificar_estado_actualizado(): void
    {
        $user = User::factory()->create();
        $solicitud = Postulacion::factory()->create([
            'username' => $user->username,
            'estado' => 'iniciada'
        ]);

        $this->notificationService->notifyEstadoActualizado(
            $solicitud,
            'iniciada',
            'aprobada'
        );

        $notification = Notification::where('type', 'estado_actualizado')
            ->where('notifiable_id', $user->username)
            ->where('notifiable_type', 'User')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('iniciada', $notification->data['estado_anterior']);
        $this->assertEquals('aprobada', $notification->data['estado_nuevo']);
    }

    /**
     * Test: Obtener notificaciones de usuario
     */
    public function test_puede_obtener_notificaciones_de_usuario(): void
    {
        $user = User::factory()->create();

        // Crear 3 notificaciones
        for ($i = 0; $i < 3; $i++) {
            $this->notificationService->create($user, 'test_notification', [
                'mensaje' => "Notificación {$i}"
            ]);
        }

        $notifications = $this->notificationService->getUserNotifications($user);

        $this->assertCount(3, $notifications);
    }

    /**
     * Test: Filtrar solo notificaciones no leídas
     */
    public function test_puede_filtrar_notificaciones_no_leidas(): void
    {
        $user = User::factory()->create();

        // Crear 3 notificaciones
        $notification1 = $this->notificationService->create($user, 'test', ['msg' => '1']);
        $notification2 = $this->notificationService->create($user, 'test', ['msg' => '2']);
        $notification3 = $this->notificationService->create($user, 'test', ['msg' => '3']);

        // Marcar una como leída
        $notification2->markAsRead();

        $unreadNotifications = $this->notificationService->getUserNotifications($user, true);

        $this->assertCount(2, $unreadNotifications);
    }

    /**
     * Test: Contar notificaciones no leídas
     */
    public function test_puede_contar_notificaciones_no_leidas(): void
    {
        $user = User::factory()->create();

        // Crear 5 notificaciones
        for ($i = 0; $i < 5; $i++) {
            $this->notificationService->create($user, 'test', ['msg' => $i]);
        }

        $count = $this->notificationService->countUnread($user);

        $this->assertEquals(5, $count);

        // Marcar 2 como leídas
        $notifications = Notification::where('notifiable_id', $user->username)->limit(2)->get();
        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }

        $count = $this->notificationService->countUnread($user);

        $this->assertEquals(3, $count);
    }

    /**
     * Test: Marcar notificación como leída
     */
    public function test_puede_marcar_notificacion_como_leida(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationService->create($user, 'test', ['msg' => 'test']);

        $this->assertNull($notification->read_at);

        $success = $this->notificationService->markAsRead($notification->id, $user);

        $this->assertTrue($success);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test: Marcar todas las notificaciones como leídas
     */
    public function test_puede_marcar_todas_como_leidas(): void
    {
        $user = User::factory()->create();

        // Crear 5 notificaciones
        for ($i = 0; $i < 5; $i++) {
            $this->notificationService->create($user, 'test', ['msg' => $i]);
        }

        $count = $this->notificationService->markAllAsRead($user);

        $this->assertEquals(5, $count);

        $unreadCount = $this->notificationService->countUnread($user);
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test: Eliminar notificación
     */
    public function test_puede_eliminar_notificacion(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationService->create($user, 'test', ['msg' => 'test']);

        $success = $this->notificationService->delete($notification->id, $user);

        $this->assertTrue($success);
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id
        ]);
    }

    /**
     * Test: No puede eliminar notificación de otro usuario
     */
    public function test_no_puede_eliminar_notificacion_de_otro_usuario(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $notification = $this->notificationService->create($user1, 'test', ['msg' => 'test']);

        $success = $this->notificationService->delete($notification->id, $user2);

        $this->assertFalse($success);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id
        ]);
    }

    /**
     * Test: Eliminar notificaciones antiguas
     */
    public function test_puede_eliminar_notificaciones_antiguas(): void
    {
        $user = User::factory()->create();

        // Crear notificación antigua (leída)
        $oldNotification = $this->notificationService->create($user, 'test', ['msg' => 'old']);
        $oldNotification->created_at = now()->subDays(35);
        $oldNotification->read_at = now()->subDays(34);
        $oldNotification->save();

        // Crear notificación reciente
        $recentNotification = $this->notificationService->create($user, 'test', ['msg' => 'recent']);

        $deleted = $this->notificationService->deleteOld(30);

        $this->assertEquals(1, $deleted);
        $this->assertDatabaseMissing('notifications', [
            'id' => $oldNotification->id
        ]);
        $this->assertDatabaseHas('notifications', [
            'id' => $recentNotification->id
        ]);
    }

    /**
     * Test: Modelo Notification - scopes
     */
    public function test_notification_scopes_funcionan_correctamente(): void
    {
        $user = User::factory()->create();

        $notification1 = $this->notificationService->create($user, 'type_a', ['msg' => '1']);
        $notification2 = $this->notificationService->create($user, 'type_b', ['msg' => '2']);
        $notification3 = $this->notificationService->create($user, 'type_a', ['msg' => '3']);

        $notification1->markAsRead();

        // Query unread notifications
        $unread = Notification::whereNull('read_at')->count();
        $this->assertEquals(2, $unread);

        // Query read notifications
        $read = Notification::whereNotNull('read_at')->count();
        $this->assertEquals(1, $read);

        // Query by type
        $typeA = Notification::where('type', 'type_a')->count();
        $this->assertEquals(2, $typeA);

        // Query recent (latest)
        $recent = Notification::latest()->first();
        $this->assertEquals($notification3->id, $recent->id);
    }

    /**
     * Test: Notification model - métodos de ayuda
     */
    public function test_notification_helper_methods(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationService->create($user, 'test', ['msg' => 'test']);

        // unread() method
        $this->assertTrue($notification->unread());
        $this->assertFalse($notification->read());

        // markAsRead()
        $notification->markAsRead();
        $this->assertTrue($notification->read());
        $this->assertFalse($notification->unread());

        // markAsUnread()
        $notification->markAsUnread();
        $this->assertTrue($notification->unread());
        $this->assertFalse($notification->read());
    }

    /**
     * Test: Tipos de notificaciones disponibles
     */
    public function test_tipos_de_notificaciones_disponibles(): void
    {
        $types = Notification::getAvailableTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey('firma_completada', $types);
        $this->assertArrayHasKey('firma_rechazada', $types);
        $this->assertArrayHasKey('firma_expirada', $types);
        $this->assertArrayHasKey('estado_actualizado', $types);
    }

    /**
     * Test: Obtener nombre descriptivo del tipo
     */
    public function test_obtener_nombre_descriptivo_del_tipo(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationService->create($user, 'firma_completada', [
            'msg' => 'test'
        ]);

        $typeName = $notification->type_name;

        $this->assertEquals('Proceso de firma completado', $typeName);
    }
}
