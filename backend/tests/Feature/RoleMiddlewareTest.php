<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'role:ADMIN'])
            ->get('/api/test/admin-only', fn () => response()->json(['data' => ['ok' => true]]));

        Route::middleware(['auth:sanctum', 'role:ADMIN,CASHIER'])
            ->get('/api/test/staff-only', fn () => response()->json(['data' => ['ok' => true]]));
    }

    #[Test]
    public function admin_can_access_admin_only_route(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/test/admin-only')
            ->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    #[Test]
    public function cashier_is_forbidden_from_admin_only_route(): void
    {
        $cashier = User::factory()->create(['role' => 'CASHIER']);

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/test/admin-only')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

    }

    #[Test]
    public function unauthenticated_user_receives_unauthorized_before_role_check(): void
    {
        $this->getJson('/api/test/admin-only')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    #[Test]
    public function middleware_accepts_any_listed_role(): void
    {
        $cashier = User::factory()->create(['role' => 'CASHIER']);
        $member = User::factory()->create(['role' => 'MEMBER']);

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/test/staff-only')
            ->assertOk();

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/test/staff-only')
            ->assertForbidden();
    }
}
