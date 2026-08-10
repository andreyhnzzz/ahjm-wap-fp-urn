<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Src\IdentityAccess\Permission\Application\UseCases\CreatePermissionUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\DeletePermissionUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\FindPermissionUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\ListPermissionsUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\UpdatePermissionUseCase;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;
use Src\IdentityAccess\Permission\Presentation\Livewire\Forms\PermissionForm;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PermissionComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    /**
     * Permissions are `modules x actions` — a small, reference-style
     * catalog. Same reasoning as RoleComponent: client-side by default.
     */
    protected string $tableMode = 'client';

    public bool $showModal = false;

    public ?int $editingId = null;

    public PermissionForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Permission::class);
        $this->sortKey = 'module';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Permission::class);

        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindPermissionUseCase $useCase): void
    {
        $permission = $useCase->handle($id);
        $this->authorize('update', $permission);

        $this->editingId = $id;
        $this->form->fromEntity($permission);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreatePermissionUseCase $createUseCase, UpdatePermissionUseCase $updateUseCase, ListPermissionsUseCase $listUseCase, FindPermissionUseCase $findUseCase): void
    {
        $this->form->validate();

        if ($this->editingId === null) {
            $this->authorize('create', Permission::class);
            $createUseCase->handle($this->form->toDto());
        } else {
            // PermissionPolicy::update() needs an actual Permission
            // instance as its 2nd parameter, same reasoning as
            // RoleComponent::save() — Permission::class alone isn't
            // enough for Laravel to call it.
            $this->authorize('update', $findUseCase->handle($this->editingId));
            $updateUseCase->handle($this->editingId, $this->form->toDto());
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: $this->editingId === null
            ? __('Permission created.')
            : __('Permission updated.'));
    }

    public function delete(int $id, DeletePermissionUseCase $useCase, ListPermissionsUseCase $listUseCase, FindPermissionUseCase $findUseCase): void
    {
        // Same reasoning as save()'s update branch: PermissionPolicy::delete()
        // requires an actual Permission instance, not the class string.
        $this->authorize('delete', $findUseCase->handle($id));

        $useCase->handle($id);

        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Permission deleted.'));
    }

    public function exportPdf(PdfExporterInterface $exporter, ListPermissionsUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportPdf', Permission::class);

        return $this->streamPdf(
            __('Permissions'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Permissions')) . '.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListPermissionsUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', Permission::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Permissions')) . '.xlsx',
            $exporter,
        );
    }

    public function render(ListPermissionsUseCase $useCase): View
    {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase);

        /** @disregard P1013 Livewire registra ->layout() como macro en runtime sobre Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Permissions'),
            'subtitle' => __('Permissions management assigned to each role'),
        ]);
    }

    private function renderClientMode(ListPermissionsUseCase $useCase): View
    {
        return view('identityaccess.permission.livewire.permission-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase),
        ]);
    }

    private function renderServerMode(ListPermissionsUseCase $useCase): View
    {
        $result = $useCase->paginate(
            search: $this->authorizedSearch(),
            module: null,
            perPage: $this->perPage,
            page: $this->page,
            sortBy: $this->sortKey,
            sortDir: $this->sortDir,
        );

        $paginator = new LengthAwarePaginator(
            items: $result['items'],
            total: $result['total'],
            perPage: $this->perPage,
            currentPage: $this->page,
        );

        return view('identityaccess.permission.livewire.permission-component', [
            'tableMode' => 'server',
            'permissions' => $paginator,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Permission $permission): array
    {
        return [
            'id' => $permission->id(),
            'module' => $permission->module(),
            'action' => $permission->action(),
            'name' => $permission->name(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListPermissionsUseCase $useCase): array
    {
        return array_map($this->toRow(...), $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListPermissionsUseCase $useCase, ?string $search): array
    {
        return array_map(
            $this->toRow(...),
            $useCase->all(search: $this->authorizedSearch($search), sortBy: $this->sortKey, sortDir: $this->sortDir),
        );
    }

    private function authorizedSearch(?string $explicit = null): ?string
    {
        if (! Auth::user()->can('search', Permission::class)) {
            return null;
        }

        $candidate = filled($explicit) ? $explicit : $this->search;

        return $candidate !== '' ? $candidate : null;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'module', 'label' => __('Module')],
            ['key' => 'action', 'label' => __('Action')],
            ['key' => 'name', 'label' => __('Name')],
        ];
    }
}
