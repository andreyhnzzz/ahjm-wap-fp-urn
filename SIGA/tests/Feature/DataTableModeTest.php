<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Src\Academic\Group\Presentation\Livewire\GroupComponent;
use Src\Academic\Teacher\Presentation\Livewire\TeacherComponent;
use Tests\TestCase;

/**
 * A CRUD screen's table mode used to be a constant chosen per screen,
 * which meant each screen was betting on a dataset it had never seen.
 * Groups only stopped taking that bet after the screen died at 45,000
 * rows; Teachers, Classrooms, Permissions and Roles were still taking it.
 *
 * These pin the two halves of the fix: small data keeps the round-trip-
 * free client mode, and data past the payload budget falls back to
 * server paging on its own, without anyone editing a property.
 */
class DataTableModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_small_catalog_keeps_client_mode(): void
    {
        $this->actingAs($this->superadmin());
        Teacher::factory()->count(3)->create();

        Livewire::test(TeacherComponent::class)
            ->assertSet('resolvedTableMode', 'client')
            // The rows only ship in client mode, and shipping them is the
            // whole reason client mode exists.
            ->assertViewHas('tableMode', 'client');
    }

    public function test_a_catalog_past_the_payload_budget_falls_back_to_server_paging(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedTeachers(2001);

        Livewire::test(TeacherComponent::class)
            ->assertSet('resolvedTableMode', 'server')
            ->assertViewHas('tableMode', 'server');
    }

    /**
     * The decision costs one COUNT, once — not a fetch of the rows it is
     * deciding about, and not a query per render. Both would defeat the
     * point: the first re-introduces the cost being avoided, the second
     * taxes every Livewire round-trip for an answer that cannot change
     * between them.
     */
    public function test_the_decision_is_made_once_and_never_fetches_what_it_is_deciding_about(): void
    {
        $this->actingAs($this->superadmin());
        $this->seedTeachers(2001);

        $component = Livewire::test(TeacherComponent::class)->assertSet('resolvedTableMode', 'server');

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $component->call('$refresh')->assertSet('resolvedTableMode', 'server');

        // Whatever the re-render costs, it must not include a second
        // count of 2,001 teachers to re-answer a settled question.
        $this->assertLessThanOrEqual(
            6,
            $queries,
            "A re-render issued {$queries} queries; the resolved mode should be read from the snapshot, not recomputed."
        );
    }

    /**
     * Groups declares 'server'. The resolution may never talk a component
     * out of that: erring towards a round-trip is survivable, erring the
     * other way is the failure this whole mechanism exists to prevent.
     */
    public function test_a_component_that_declares_server_mode_stays_there_with_almost_no_rows(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(GroupComponent::class)
            ->assertSet('resolvedTableMode', 'server');
    }

    private function seedTeachers(int $count): void
    {
        $now = now()->toDateTimeString();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'identity_card' => sprintf('9-%04d-%04d', intdiv($i, 10000), $i % 10000),
                'name' => "Docente {$i}",
                'reference_workload' => 1.0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) === 500) {
                Teacher::insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            Teacher::insert($rows);
        }
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
