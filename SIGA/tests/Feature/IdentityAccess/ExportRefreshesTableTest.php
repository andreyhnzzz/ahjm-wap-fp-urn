<?php

namespace Tests\Feature\IdentityAccess;

use App\Jobs\GenerateReportExportJob;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;
use Tests\TestCase;

/**
 * Regression for the "empty list after exporting twice" bug: every render
 * after a component's first sends rows: [] and relies on refreshTable() to
 * repair Alpine's live state (see InteractsWithDataTable::isFirstRender()).
 * exportPdf()/exportExcel() queue a GenerateReportExportJob now instead of
 * rendering inline (see InteractsWithExports::queueExport()), but still go
 * through the same render cycle and still need that repair.
 *
 * Queue::fake() keeps this fast and Chrome-independent: the job's actual
 * render is covered by the async export flow itself, not this test.
 */
class ExportRefreshesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_exporting_pdf_dispatches_a_table_refresh_with_the_current_rows(): void
    {
        Queue::fake();
        $this->actingAs($this->superadmin());
        $role = Role::query()->create(['name' => 'Coordinator']);

        Livewire::test(RoleComponent::class)
            ->call('exportPdf', '')
            ->call('exportPdf', '')
            ->assertDispatched('data-table-refresh', function (string $name, array $params) use ($role): bool {
                return collect($params['rows'])->contains('name', $role->name);
            });

        Queue::assertPushed(GenerateReportExportJob::class, 2);
    }

    public function test_exporting_excel_dispatches_a_table_refresh_with_the_current_rows(): void
    {
        Queue::fake();
        $this->actingAs($this->superadmin());
        $role = Role::query()->create(['name' => 'Coordinator']);

        Livewire::test(RoleComponent::class)
            ->call('exportExcel', '')
            ->call('exportExcel', '')
            ->assertDispatched('data-table-refresh', function (string $name, array $params) use ($role): bool {
                return collect($params['rows'])->contains('name', $role->name);
            });

        Queue::assertPushed(GenerateReportExportJob::class, 2);
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
