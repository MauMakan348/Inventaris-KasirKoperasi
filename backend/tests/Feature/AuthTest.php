<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function register_creates_member_account(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Anggota Baru',
            'email' => 'anggota.baru@example.test',
            'password' => 'rahasia123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'MEMBER')
            ->assertJsonPath('data.email', 'anggota.baru@example.test')
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', [
            'email' => 'anggota.baru@example.test',
            'role' => 'MEMBER',
        ]);
    }

    #[Test]
    public function registration_ignores_role_from_request(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Calon Admin',
            'email' => 'calon.admin@example.test',
            'password' => 'rahasia123',
            'role' => 'ADMIN',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'MEMBER');
        $this->assertDatabaseHas('users', [
            'email' => 'calon.admin@example.test',
            'role' => 'MEMBER',
        ]);
    }

    #[Test]
    public function registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplikat@example.test']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplikat',
            'email' => 'duplikat@example.test',
            'password' => 'rahasia123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['email']]]);
    }

    #[Test]
    public function login_returns_token_and_user(): void
    {
        User::factory()->create([
            'email' => 'kasir@example.test',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'kasir@example.test',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'kasir@example.test')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role']]]);

        $this->assertIsString($response->json('data.token'));
    }

    #[Test]
    public function login_uses_same_generic_message_for_unknown_email_and_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'terdaftar@example.test',
            'password' => 'password',
        ]);

        $unknown = $this->postJson('/api/auth/login', [
            'email' => 'tidak.terdaftar@example.test',
            'password' => 'password',
        ]);

        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => 'terdaftar@example.test',
            'password' => 'password-salah',
        ]);

        $unknown->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $wrongPassword->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $this->assertSame(
            $unknown->json('error.message'),
            $wrongPassword->json('error.message'),
        );
    }

    #[Test]
    public function login_is_rate_limited_to_five_attempts_per_minute(): void
    {
        User::factory()->create([
            'email' => 'limit@example.test',
            'password' => 'password',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'limit@example.test',
                'password' => 'password-salah',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'limit@example.test',
            'password' => 'password-salah',
        ])->assertTooManyRequests();
    }

    #[Test]
    public function me_requires_token_and_returns_json_without_accept_header(): void
    {
        $response = $this->get('/api/auth/me');

        $response->assertUnauthorized()
            ->assertHeader('content-type', 'application/json')
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', $user->role->value);
    }

    #[Test]
    public function logout_revokes_only_the_active_token(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.test',
            'password' => 'password',
        ]);

        $activeToken = $this->postJson('/api/auth/login', [
            'email' => 'logout@example.test',
            'password' => 'password',
        ])->json('data.token');

        $otherToken = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$activeToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$activeToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$otherToken)
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertSame(480, config('sanctum.expiration'));
    }

    #[Test]
    public function unknown_api_route_returns_not_found_envelope(): void
    {
        $this->get('/api/route-tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
