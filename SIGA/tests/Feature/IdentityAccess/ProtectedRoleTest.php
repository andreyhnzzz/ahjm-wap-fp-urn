<?php

namespace Tests\Feature\IdentityAccess;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;
use Tests\TestCase;

/**
 * System roles are rejected up front by RoleComponent rather than by
 * catching the use case's exception, so these cover the guard on the
 * screen and confirm the domain rule still holds underneath it.
 */
class ProtectedRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_system_role_cannot_be_renamed_from_the_screen(): void
    {
        $this->actingAs($this->superadmin());
        $protected = Role::query()->where('name', 'Superadmin')->firstOrFail();

        Livewire::test(RoleComponent::class)
            ->call('openEditModal', $protected->id)
            ->set('form.name', 'Renamed')
            ->call('save')
            ->assertDispatched('toast', variant: 'danger');

        $this->assertSame('Superadmin', $protected->fresh()->name);
    }

    public function test_a_system_role_cannot_be_deleted_from_the_screen(): void
    {
        $this->actingAs($this->superadmin());
        $protected = Role::query()->where('name', 'Superadmin')->firstOrFail();

        Livewire::test(RoleComponent::class)
            ->call('delete', $protected->id)
            ->assertDispatched('toast', variant: 'danger');

        $this->assertNotNull($protected->fresh(), 'The system role was deleted.');
    }

    public function test_a_custom_role_can_still_be_renamed_and_deleted(): void
    {
        $this->actingAs($this->superadmin());
        $custom = Role::query()->create(['name' => 'Coordinator']);

        Livewire::test(RoleComponent::class)
            ->call('openEditModal', $custom->id)
            ->set('form.name', 'Coordination')
            ->call('save')
            ->assertDispatched('toast', variant: 'success');

        $this->assertSame('Coordination', $custom->fresh()->name);

        Livewire::test(RoleComponent::class)
            ->call('delete', $custom->id)
            ->assertDispatched('toast', variant: 'success');

        $this->assertNull($custom->fresh());
    }

    /**
     * The permission checklist reads its labels through __(), so a
     * Spanish locale must reach the view rather than the English keys.
     */
    public function test_the_permission_checklist_is_localised(): void
    {
        $this->actingAs($this->superadmin());
        app()->setLocale('es');

        Livewire::test(RoleComponent::class)->assertSee('Crear aulas');
    }

    /**
     * Every @can in the sidebar and every row action resolves through
     * HasRolesAndPermissions. Loading it per check used to cost one query
     * per role; this pins the flattened lookup so a regression that
     * reintroduces lazy loading fails here instead of in production.
     *
     * The roles hold DISJOINT permission sets on purpose: when every role
     * carries every permission, the old implementation short-circuited on
     * the first one and the N+1 stayed invisible. A permission the user
     * does not hold is the true worst case — it can only be answered
     * after every role has been consulted.
     */
    public function test_permission_checks_do_not_scale_with_the_number_of_roles(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();

        $chunks = Permission::query()->pluck('id')->chunk(5);

        foreach ($chunks as $i => $chunk) {
            $role = Role::query()->create(['name' => "Role {$i}"]);
            $role->permissions()->sync($chunk);
            $user->roles()->attach($role->id);
        }

        $this->assertGreaterThan(3, $chunks->count(), 'Needs several roles to be a meaningful check.');

        $lastRolePermission = Permission::query()->whereIn('id', $chunks->last())->value('name');

        $user = $user->fresh();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        // Held only by the final role, then one that no role grants:
        // both force the lookup past every role.
        $this->assertTrue($user->hasPermissionTo($lastRolePermission));
        $this->assertFalse($user->hasPermissionTo('nothing.granted'));

        $this->assertLessThanOrEqual(
            3,
            $queries,
            "Permission checks issued {$queries} queries; the flattened lookup should need at most 3 regardless of role count."
        );
    }

    private function superadmin(): User
    {
        $this->seed(PermissionSeeder::class);

        $role = Role::query()->create(['name' => 'Superadmin', 'protected' => true]);
        $role->permissions()->sync(Permission::query()->pluck('id'));

        $user = User::factory()->create();
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
