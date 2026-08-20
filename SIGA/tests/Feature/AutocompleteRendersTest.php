<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\AcademicDataSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Src\Academic\Group\Presentation\Livewire\GroupComponent;
use Tests\TestCase;

/**
 * The autocomplete replaced two <select>s in the group modal. A unit test
 * of the filtering cannot see a Blade that fails to compile or a prop
 * that never arrives, so this renders the real component and drives it.
 */
class AutocompleteRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_group_modal_renders_the_autocompletes_and_selects_through_them(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AcademicDataSeeder::class);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'Superadmin')->first());

        $teacher = Teacher::query()->first();

        Livewire::actingAs($user)->test(GroupComponent::class)
            ->assertOk()
            ->call('openCreateModal')
            ->assertSee('groupTeacherId', escape: false)
            ->assertSee('groupClassroomId', escape: false)
            // Typing narrows the list server-side...
            ->set('teacherQuery', mb_substr($teacher->name, 0, 4))
            ->assertSee($teacher->name)
            // ...and choosing writes through to the form, not to the query.
            ->call('selectTeacher', (string) $teacher->id)
            ->assertSet('form.teacherId', (string) $teacher->id)
            ->assertSet('teacherQuery', '')
            ->call('clearTeacher')
            ->assertSet('form.teacherId', '');
    }

    public function test_a_forged_teacher_id_is_rejected_on_save(): void
    {
        // selectTeacher() is client-callable: replacing a <select> with an
        // autocomplete must not turn "pick from the list the server rendered"
        // into "send any id you like". The guard is the same one the <select>
        // relied on — GroupForm's Rule::exists — and this pins that the new
        // entry point still goes through it.
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(AcademicDataSeeder::class);

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'Superadmin')->first());

        Livewire::actingAs($user)->test(GroupComponent::class)
            ->call('openCreateModal')
            ->call('selectTeacher', '999999')
            ->set('form.code', 'SEC-001')
            ->set('form.courseCode', 'ISW-999')
            ->set('form.term', '2026-II')
            ->set('form.estimatedEnrollment', 10)
            ->call('save')
            ->assertHasErrors(['form.teacherId']);

        $this->assertDatabaseMissing('groups', ['code' => 'SEC-001']);
    }
}
