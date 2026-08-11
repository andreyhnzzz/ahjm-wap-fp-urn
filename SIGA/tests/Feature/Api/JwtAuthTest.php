<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * routes/api.php: the JWT-secured JSON API (transversal course
 * requirement, separate from the Livewire UI's session auth).
 */
class JwtAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_bearer_token_for_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'token_type', 'expires_in']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_login_rejects_accounts_with_two_factor_confirmed(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(423);
    }

    public function test_protected_endpoints_reject_requests_without_a_token(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/teachers')->assertUnauthorized();
    }

    public function test_protected_endpoints_reject_a_tampered_token(): void
    {
        $this->getJson('/api/me', ['Authorization' => 'Bearer not-a-real-token'])
            ->assertUnauthorized();
    }

    public function test_a_valid_token_authenticates_and_reaches_authorized_data(): void
    {
        $user = $this->superadmin();
        Teacher::factory()->create(['name' => 'Ana Rodríguez']);

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        $this->getJson('/api/me', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        $this->getJson('/api/teachers', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Ana Rodríguez']);
    }

    public function test_a_token_cannot_reach_data_the_user_lacks_permission_for(): void
    {
        $user = User::factory()->create();

        $token = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('token');

        $this->getJson('/api/teachers', ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    }

    private function superadmin(): User
    {
        $this->seed(PermissionSeeder::class);

        $role = Role::query()->create(['name' => 'Superadmin']);
        $role->permissions()->sync(Permission::query()->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
