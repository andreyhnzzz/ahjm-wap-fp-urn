<?php

namespace Tests\Feature\Academic;

use App\Models\Classroom;
use App\Models\Group;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Src\Academic\Classroom\Presentation\Livewire\ClassroomComponent;
use Src\Academic\Group\Presentation\Livewire\GroupComponent;
use Src\Academic\Teacher\Presentation\Livewire\TeacherComponent;
use Tests\TestCase;

/**
 * INFRA-01 end to end: the three admin CRUDs, through the real HTTP and
 * Livewire stack, including the authorization layer.
 *
 * The unit tests already cover the business rules in isolation; what
 * these add is the wiring — routes, policies, container bindings, the
 * repository mapping and the Blade views actually rendering.
 */
class AcademicModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_without_permissions_cannot_reach_the_academic_screens(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('academic.teacher.index'))->assertForbidden();
        $this->get(route('academic.classroom.index'))->assertForbidden();
        $this->get(route('academic.group.index'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('academic.group.index'))->assertRedirect(route('login'));
    }

    public function test_the_three_screens_render_for_an_authorized_user(): void
    {
        $this->actingAs($this->superadmin());

        Teacher::factory()->create(['name' => 'Ana Rodríguez']);
        Classroom::factory()->create(['name' => 'Aula 101', 'capacity' => 30]);

        $this->get(route('academic.teacher.index'))->assertOk()->assertSee('Ana Rodríguez');
        $this->get(route('academic.classroom.index'))->assertOk()->assertSee('Aula 101');
        $this->get(route('academic.group.index'))->assertOk();
    }

    public function test_a_teacher_can_be_created_updated_and_deleted(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(TeacherComponent::class)
            ->set('form.identityCard', '1-0234-0567')
            ->set('form.name', 'Carlos Jiménez')
            ->set('form.referenceWorkload', '0.75')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teachers', [
            'identity_card' => '1-0234-0567',
            'name' => 'Carlos Jiménez',
            'reference_workload' => 0.75,
        ]);

        $teacher = Teacher::query()->firstOrFail();

        Livewire::test(TeacherComponent::class)
            ->call('openEditModal', $teacher->id)
            ->assertSet('form.name', 'Carlos Jiménez')
            ->set('form.name', 'Carlos Jiménez Salas')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'name' => 'Carlos Jiménez Salas']);

        Livewire::test(TeacherComponent::class)->call('delete', $teacher->id);

        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
    }

    public function test_a_duplicated_identity_card_is_rejected(): void
    {
        $this->actingAs($this->superadmin());

        Teacher::factory()->create(['identity_card' => '1-0234-0567']);

        Livewire::test(TeacherComponent::class)
            ->set('form.identityCard', '1-0234-0567')
            ->set('form.name', 'Otro docente')
            ->set('form.referenceWorkload', '1.00')
            ->call('save')
            ->assertHasErrors(['form.identityCard']);
    }

    public function test_a_classroom_needs_a_positive_capacity(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(ClassroomComponent::class)
            ->set('form.name', 'Aula 999')
            ->set('form.capacity', '0')
            ->call('save')
            ->assertHasErrors(['form.capacity']);

        $this->assertDatabaseMissing('classrooms', ['name' => 'Aula 999']);
    }

    /**
     * INFRA-01 requires this explicitly: the form must accept a group
     * saved with no teacher and no classroom, because those are the
     * states RE-04 has to detect.
     */
    public function test_a_group_can_be_saved_deliberately_incomplete(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(GroupComponent::class)
            ->set('form.code', 'ISW-521-G09')
            ->set('form.courseCode', 'ISW-521')
            ->set('form.term', '2026-II')
            ->set('form.teacherId', '')
            ->set('form.classroomId', '')
            ->set('form.estimatedEnrollment', '18')
            ->set('form.assignedWorkload', '0.00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('groups', [
            'code' => 'ISW-521-G09',
            'teacher_id' => null,
            'classroom_id' => null,
        ]);
    }

    public function test_a_group_without_a_teacher_cannot_carry_a_workload(): void
    {
        $this->actingAs($this->superadmin());

        Livewire::test(GroupComponent::class)
            ->set('form.code', 'ISW-521-G10')
            ->set('form.courseCode', 'ISW-521')
            ->set('form.term', '2026-II')
            ->set('form.teacherId', '')
            ->set('form.assignedWorkload', '0.50')
            ->call('save')
            ->assertHasErrors(['form.assignedWorkload']);
    }

    /**
     * Clearing the teacher select zeroes the workload in the modal, so
     * the user never has to discover the rule by hitting Save.
     */
    public function test_clearing_the_teacher_zeroes_the_workload_in_the_form(): void
    {
        $this->actingAs($this->superadmin());

        $teacher = Teacher::factory()->create();

        Livewire::test(GroupComponent::class)
            ->set('form.teacherId', (string) $teacher->id)
            ->set('form.assignedWorkload', '0.50')
            ->set('form.teacherId', '')
            ->assertSet('form.assignedWorkload', '0.00');
    }

    /**
     * Deleting a teacher must orphan the group, never delete academic
     * history — that is what turns it into a visible risk instead of a
     * silent data loss.
     */
    public function test_deleting_a_teacher_leaves_the_group_without_a_teacher(): void
    {
        $this->actingAs($this->superadmin());

        $teacher = Teacher::factory()->create();
        $group = Group::factory()->create(['teacher_id' => $teacher->id]);

        Livewire::test(TeacherComponent::class)->call('delete', $teacher->id);

        $this->assertDatabaseHas('groups', ['id' => $group->id, 'teacher_id' => null]);
    }

    /**
     * Client mode's `rows` are read by Alpine exactly once (see
     * InteractsWithDataTable::isFirstRender()) — any render after the
     * first must not re-query the full table just to build data nobody
     * downstream reads. Regression guard for that guard.
     */
    public function test_reopening_the_modal_does_not_requery_the_full_table(): void
    {
        $this->actingAs($this->superadmin());

        Teacher::factory()->count(3)->create();

        $component = Livewire::test(TeacherComponent::class);

        DB::enableQueryLog();
        $component->call('openCreateModal');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertFalse(
            collect($queries)->contains(fn (array $q): bool => str_contains(strtolower((string) $q['query']), 'teachers')),
            'openCreateModal re-fetched the teacher list; it should only be fetched on the first render.',
        );
    }

    /**
     * refreshTable() is a no-op in server mode, but its argument used to be
     * built before the call could decide that — and building it re-fetches
     * every row in the table. Deleting one group re-read the whole offer
     * for a value that was thrown away (1.3 s and 102 MB at 20,000 rows).
     */
    public function test_a_mutation_does_not_re_read_the_whole_table_in_server_mode(): void
    {
        $this->actingAs($this->superadmin());
        $group = Group::factory()->create();
        Group::factory()->count(15)->create();

        $component = Livewire::test(GroupComponent::class);

        DB::enableQueryLog();
        $component->call('delete', $group->id);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // A row-returning read of the offer with no LIMIT. The paginator's
        // own count(*) and the DELETE are bounded work and belong here.
        $unbounded = collect($queries)->filter(function (array $query): bool {
            $sql = strtolower((string) $query['query']);

            return str_starts_with($sql, 'select')
                && str_contains($sql, 'from "groups"')
                && ! str_contains($sql, 'count(*)')
                && ! str_contains($sql, 'limit');
        });

        $this->assertTrue(
            $unbounded->isEmpty(),
            'A mutation read the groups table without a LIMIT; server mode pages, it must never load every row: '.$unbounded->pluck('query')->implode(' | '),
        );
    }

    /**
     * `perPage` is a public Livewire property, so its value comes from the
     * client's payload — the 10/25/50 <select> constrains the UI, not the
     * request. Since /groups pages server-side, an unclamped value becomes
     * the query's LIMIT: asking for every row hydrates the whole table in
     * one request, which is a fatal memory error on a large table and a
     * denial of service any authenticated user could repeat at will.
     */
    public function test_a_crafted_per_page_cannot_make_the_group_table_load_every_row(): void
    {
        $this->actingAs($this->superadmin());
        Group::factory()->count(15)->create();

        $groups = Livewire::test(GroupComponent::class)
            ->set('perPage', 1_000_000)
            ->viewData('groups');

        $this->assertSame(10, $groups->perPage(), 'An out-of-range per-page reached the query; it must fall back to 10.');
        $this->assertCount(10, $groups->items());

        // The offered sizes still work — the clamp is an allowlist, not a cap.
        $this->assertSame(
            25,
            Livewire::test(GroupComponent::class)->set('perPage', 25)->viewData('groups')->perPage(),
        );
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
