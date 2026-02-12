<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful user registration.
     */
    public function test_user_can_register_successfully(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '123456789',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'telefono' => '3001234567',
            'email' => 'juan.perez@example.com',
            'username' => 'juanperez',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'timestamp',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in',
                    'user' => [
                        'id',
                        'username',
                        'email',
                        'full_name',
                        'phone',
                        'tipo_documento',
                        'numero_documento',
                        'roles',
                        'permissions',
                        'is_active',
                        'disabled',
                        'created_at',
                        'updated_at'
                    ],
                    'pin',
                    'verification_required'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'data' => [
                    'token_type' => 'bearer',
                    'user' => [
                        'email' => 'juan.perez@example.com',
                        'full_name' => 'Juan Pérez',
                        'phone' => '3001234567',
                        'tipo_documento' => '1',
                        'numero_documento' => '123456789',
                        'username' => 'juanperez',
                        'roles' => ['user_trabajador'],
                        'is_active' => true,
                        'disabled' => false
                    ],
                    'verification_required' => true
                ]
            ]);

        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'email' => 'juan.perez@example.com',
            'numero_documento' => '123456789',
            'nombres' => 'Juan',
            'apellidos' => 'Pérez',
            'username' => 'juanperez'
        ]);

        // Verify password was hashed
        $user = User::where('email', 'juan.perez@example.com')->first();
        $this->assertTrue(Hash::check('Password123', $user->password_hash));
    }

    /**
     * Test registration with duplicate email fails.
     */
    public function test_registration_fails_with_duplicate_email(): void
    {
        // Create existing user
        User::factory()->create([
            'email' => 'existing@example.com',
            'username' => 'existinguser'
        ]);

        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '987654321',
            'nombres' => 'Maria',
            'apellidos' => 'Gómez',
            'telefono' => '3009876543',
            'email' => 'existing@example.com',
            'username' => 'mariagomez',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration with duplicate document number fails.
     */
    public function test_registration_fails_with_duplicate_document(): void
    {
        // Create existing user
        User::factory()->create([
            'numero_documento' => '123456789',
            'username' => 'existinguser'
        ]);

        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '123456789',
            'nombres' => 'Carlos',
            'apellidos' => 'López',
            'telefono' => '3005555555',
            'email' => 'carlos.lopez@example.com',
            'username' => 'carloslopez',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422); // Validation error
    }

    /**
     * Test registration with invalid email format fails.
     */
    public function test_registration_fails_with_invalid_email(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '111222333',
            'nombres' => 'Ana',
            'apellidos' => 'Martínez',
            'telefono' => '3001111111',
            'email' => 'invalid-email',
            'username' => 'anamartinez',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration with password mismatch passes (no confirmed validation).
     */
    public function test_registration_passes_with_password_mismatch(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '444555666',
            'nombres' => 'Roberto',
            'apellidos' => 'Silva',
            'telefono' => '3002222222',
            'email' => 'roberto.silva@example.com',
            'username' => 'robertosilva',
            'password' => 'password123',
            'confirmar_password' => 'differentpassword'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        // Should pass since there's no 'confirmed' validation
        $response->assertStatus(201);
    }

    /**
     * Test registration with short password fails.
     */
    public function test_registration_fails_with_short_password(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '777888999',
            'nombres' => 'Laura',
            'apellidos' => 'Torres',
            'telefono' => '3003333333',
            'email' => 'laura.torres@example.com',
            'username' => 'lauratorres',
            'password' => '123',
            'confirmar_password' => '123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test registration with missing required fields fails.
     */
    public function test_registration_fails_with_missing_fields(): void
    {
        $userData = [
            'nombres' => 'Pedro',
            'apellidos' => 'Ramírez'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'tipo_documento',
                'numero_documento',
                'telefono',
                'email',
                'username',
                'password',
                'confirmar_password'
            ]);
    }

    /**
     * Test registration generates valid username.
     */
    public function test_registration_generates_valid_username(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '123456789',
            'nombres' => 'Juan Carlos',
            'apellidos' => 'Pérez García',
            'telefono' => '3001234567',
            'email' => 'juan.carlos@example.com',
            'username' => 'juancarlos',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201);

        // Verify username was set correctly
        $user = User::where('email', 'juan.carlos@example.com')->first();
        $this->assertNotNull($user->username);
        $this->assertEquals('juancarlos', $user->username);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]{3,20}$/', $user->username);
    }

    /**
     * Test registration assigns correct default role.
     */
    public function test_registration_assigns_default_role(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '123456789',
            'nombres' => 'Diana',
            'apellidos' => 'Herrera',
            'telefono' => '3004444444',
            'email' => 'diana.herrera@example.com',
            'username' => 'dianaherrera',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201);

        $user = User::where('email', 'diana.herrera@example.com')->first();
        $this->assertContains('user_trabajador', $user->roles);
    }

    /**
     * Test registration creates token successfully.
     */
    public function test_registration_creates_token_successfully(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '123456789',
            'nombres' => 'Gabriel',
            'apellidos' => 'Mendoza',
            'telefono' => '3005555555',
            'email' => 'gabriel.mendoza@example.com',
            'username' => 'gabrielmendoza',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'timestamp',
                'data' => [
                    'access_token',
                    'token_type',
                    'expires_in'
                ]
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data['access_token']);
        $this->assertEquals('bearer', $data['token_type']);
        $this->assertIsNumeric($data['expires_in'] ?? 0);
        $this->assertArrayHasKey('pin', $data);
        $this->assertTrue($data['verification_required']);
    }

    /**
     * Test registration with duplicate username fails.
     */
    public function test_registration_fails_with_duplicate_username(): void
    {
        // Create existing user
        User::factory()->create([
            'username' => 'existinguser',
            'email' => 'existing@example.com'
        ]);

        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '987654321',
            'nombres' => 'Maria',
            'apellidos' => 'Gómez',
            'telefono' => '3009876543',
            'email' => 'maria.gomez@example.com',
            'username' => 'existinguser',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        // Laravel validation catches this first, returning 422
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    /**
     * Test registration with username too long fails.
     */
    public function test_registration_fails_with_username_too_long(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '111222333',
            'nombres' => 'Ana',
            'apellidos' => 'Martínez',
            'telefono' => '3001111111',
            'email' => 'ana.martinez@example.com',
            'username' => 'verylongusernamethatexceedstwentycharacters',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    /**
     * Test registration with invalid username format fails.
     */
    public function test_registration_fails_with_invalid_username_format(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '444555666',
            'nombres' => 'Roberto',
            'apellidos' => 'Silva',
            'telefono' => '3002222222',
            'email' => 'roberto.silva@example.com',
            'username' => 'abc123!', // Contains invalid character '!'
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        // Username with invalid characters should trigger service validation
        $response->assertStatus(400);
    }

    /**
     * Test registration generates PIN successfully.
     */
    public function test_registration_generates_pin(): void
    {
        $userData = [
            'tipo_documento' => '1',
            'numero_documento' => '123456789',
            'nombres' => 'Laura',
            'apellidos' => 'Torres',
            'telefono' => '3003333333',
            'email' => 'laura.torres@example.com',
            'username' => 'lauratorres',
            'password' => 'Password123',
            'confirmar_password' => 'Password123'
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'timestamp',
                'data' => [
                    'pin',
                    'verification_required'
                ]
            ])
            ->assertJson([
                'data' => [
                    'verification_required' => true
                ]
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data['pin']);
        $this->assertIsNumeric($data['pin']);
        $this->assertGreaterThanOrEqual(1000, $data['pin']);
        $this->assertLessThanOrEqual(9999, $data['pin']);
    }
}
