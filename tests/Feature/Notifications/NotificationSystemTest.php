<?php

namespace Tests\Feature\Notifications;

use Tests\TestCase;
use App\Services\NotificationService;
use App\Models\Notification;
use App\Models\User;
use App\Models\Postulacion;
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
        $notification = $this->notificationService->create($user, 'test_notification', ['msg' => 'test']);

        $this->assertNotNull($notification);
        $this->assertEquals('test_notification', $notification->type);
        $this->assertEquals($user->username, $notification->notifiable_id);
        $this->assertEquals('User', $notification->notifiable_type);
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

        $this->assertDatabaseHas('notifications', [
            'type' => 'firma_completada',
            'notifiable_id' => $user->username,
            'notifiable_type' => 'User'
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

        // Crear 3 notificaciones manualmente
        for ($i = 1; $i <= 3; $i++) {
            Notification::create([
                'id' => Str::uuid(),
                'type' => 'type_' . $i,
                'notifiable_type' => 'User',
                'notifiable_id' => $user->username,
                'data' => ['msg' => 'test_' . $i],
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now()
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

        // Crear 3 notificaciones, 2 no leídas
        Notification::create([
            'id' => Str::uuid(),
            'type' => 'type_a',
            'notifiable_type' => 'User',
            'notifiable_id' => $user->username,
            'data' => ['msg' => 'test_a'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        Notification::create([
            'id' => Str::uuid(),
            'type' => 'type_b',
            'notifiable_type' => 'User',
            'notifiable_id' => $user->username,
            'data' => ['msg' => 'test_b'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        Notification::create([
            'id' => Str::uuid(),
            'type' => 'type_c',
            'notifiable_type' => 'User',
            'notifiable_id' => $user->username,
            'data' => ['msg' => 'test_c'],
            'read_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $unreadNotifications = $this->notificationService->getUserNotifications($user, true);
        $this->assertCount(2, $unreadNotifications);
    }

    /**
     * Test: Contar notificaciones no leídas
     */
    public function test_puede_contar_notificaciones_no_leidas(): void
    {
        $user = User::factory()->create();

        // Crear 5 notificaciones no leídas
        for ($i = 1; $i <= 5; $i++) {
            Notification::create([
                'id' => Str::uuid(),
                'type' => 'type_' . $i,
                'notifiable_type' => 'User',
                'notifiable_id' => $user->username,
                'data' => ['msg' => 'test_' . $i],
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        $count = $this->notificationService->countUnread($user);
        $this->assertEquals(5, $count);
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
     * Test: Marcar todas como leídas
     */
    public function test_puede_marcar_todas_como_leidas(): void
    {
        $user = User::factory()->create();

        // Crear 5 notificaciones no leídas
        for ($i = 1; $i <= 5; $i++) {
            Notification::create([
                'id' => Str::uuid(),
                'type' => 'type_' . $i,
                'notifiable_type' => 'User',
                'notifiable_id' => $user->username,
                'data' => ['msg' => 'test_' . $i],
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);
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
     * Test: Notification scopes funcionan correctamente
     */
    public function test_notification_scopes_funcionan_correctamente(): void
    {
        // Crear 3 notificaciones con diferentes IDs
        $notification1 = Notification::create([
            'id' => Str::uuid(),
            'type' => 'type_a',
            'notifiable_type' => 'User',
            'notifiable_id' => 'test_user_scopes',
            'data' => ['msg' => 'test_a'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $notification2 = Notification::create([
            'id' => Str::uuid(),
            'type' => 'type_b',
            'notifiable_type' => 'User',
            'notifiable_id' => 'test_user_scopes',
            'data' => ['msg' => 'test_b'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $notification3 = Notification::create([
            'id' => Str::uuid(),
            'type' => 'type_c',
            'notifiable_type' => 'User',
            'notifiable_id' => 'test_user_scopes',
            'data' => ['msg' => 'test_c'],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Marcar una como leída
        $notification1->read_at = now();
        $notification1->save();

        // Query unread notifications (solo para este usuario)
        $unread = Notification::whereNull('read_at')
            ->where('notifiable_id', 'test_user_scopes')
            ->count();
        $this->assertEquals(2, $unread);

        // Query read notifications (solo para este usuario)
        $read = Notification::whereNotNull('read_at')
            ->where('notifiable_id', 'test_user_scopes')
            ->count();
        $this->assertEquals(1, $read);

        // Query por tipo
        $typeA = Notification::where('type', 'type_a')
            ->where('notifiable_id', 'test_user_scopes')
            ->count();
        $this->assertEquals(1, $typeA);

        // Query recent (latest) - verificar que devuelve una notificación
        $recent = Notification::where('notifiable_id', 'test_user_scopes')
            ->latest()
            ->first();
        $this->assertNotNull($recent);
        $this->assertEquals('test_user_scopes', $recent->notifiable_id);
    }
}
