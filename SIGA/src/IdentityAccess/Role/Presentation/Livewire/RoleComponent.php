<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Src\IdentityAccess\Permission\Application\UseCases\ListPermissionsUseCase;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;
use Src\IdentityAccess\Permission\Presentation\Support\PermissionLabelFormatter;
use Src\IdentityAccess\Role\Application\UseCases\CreateRoleUseCase;
use Src\IdentityAccess\Role\Application\UseCases\DeleteRoleUseCase;
use Src\IdentityAccess\Role\Application\UseCases\FindRoleUseCase;
use Src\IdentityAccess\Role\Application\UseCases\ListRolesUseCase;
use Src\IdentityAccess\Role\Application\UseCases\UpdateRoleUseCase;
use Src\IdentityAccess\Role\Domain\Entities\Role;
use Src\IdentityAccess\Role\Domain\Exceptions\RoleIsProtectedException;
use Src\IdentityAccess\Role\Presentation\Livewire\Forms\RoleForm;

class RoleComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;

    /**
     * Roles are a small, reference-style catalog (typically a handful to
     * a few dozen records) — client-side is the right default: the whole
     * list ships once and Alpine resolves search/sort/pagination with
     * zero further server round-trips. Flip to 'server' only if this
     * catalog is ever expected to grow into the hundreds/thousands.
     */
    protected string $tableMode = 'client';

    public bool $showModal = false;

    /**
     * Null while creating; the row's id while editing. Also read by
     * RoleForm::rules() to scope the uniqueness check correctly.
     */
    public ?int $editingId = null;

    public RoleForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
        $this->sortKey = 'name';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Role::class);

        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindRoleUseCase $useCase): void
    {
        $this->authorize('update', Role::class);

        $role = $useCase->handle($id);

        $this->editingId = $id;
        $this->form->fromEntity($role);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreateRoleUseCase $createUseCase, UpdateRoleUseCase $updateUseCase, ListRolesUseCase $listUseCase): void
    {
        $this->form->validate();

        try {
            if ($this->editingId === null) {
                $this->authorize('create', Role::class);
                $createUseCase->handle($this->form->toDto());
            } else {
                $this->authorize('update', Role::class);
                $updateUseCase->handle($this->editingId, $this->form->toDto());
            }
        } catch (RoleIsProtectedException $e) {
            $this->dispatch('toast', variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: $this->editingId === null
            ? __('Role created.')
            : __('Role updated.'));
    }

    public function delete(int $id, DeleteRoleUseCase $useCase, ListRolesUseCase $listUseCase): void
    {
        $this->authorize('delete', Role::class);

        try {
            $useCase->handle($id);
        } catch (RoleIsProtectedException $e) {
            $this->dispatch('toast', variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Role deleted.'));
    }

    public function exportPdf(): void
    {
        $this->authorize('exportPdf', Role::class);
        $this->dispatch('toast', variant: 'info', text: __('Export coming soon.'));
    }

    public function exportExcel(): void
    {
        $this->authorize('exportExcel', Role::class);
        $this->dispatch('toast', variant: 'info', text: __('Export coming soon.'));
    }

    public function render(ListRolesUseCase $useCase, ListPermissionsUseCase $permissionsUseCase): View
    {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase);

        $view = $view->with('permissionCatalog', $this->permissionCatalog($permissionsUseCase));

        /** @disregard P1013 Livewire registra ->layout() como macro en runtime sobre Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Roles'),
            'subtitle' => __('System user roles management'),
        ]);
    }

    /**
     * Client mode: the full, unpaginated collection is fetched once and
     * projected into plain arrays for Alpine (resources/js/data-table.js).
     * No Domain Entity ever reaches the browser.
     */
    private function renderClientMode(ListRolesUseCase $useCase): View
    {
        return view('identityaccess.role.livewire.role-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase),
        ]);
    }

    /**
     * Server mode: unchanged Livewire-driven pagination, preserved for
     * any future catalog too large to ship to the client in one response.
     */
    private function renderServerMode(ListRolesUseCase $useCase): View
    {
        $result = $useCase->paginate(
            search: $this->search !== '' ? $this->search : null,
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

        return view('identityaccess.role.livewire.role-component', [
            'tableMode' => 'server',
            'roles' => $paginator,
        ]);
    }

    /**
     * Plain-array projection handed to Alpine as JSON — keeps the Domain
     * Entity from ever leaking past the Presentation boundary.
     *
     * @return array<string, mixed>
     */
    private function toRow(Role $role): array
    {
        return [
            'id' => $role->id(),
            'name' => $role->name(),
            'permissionsCount' => count($role->permissions()),
            'protected' => $role->isProtected(),
        ];
    }

    /**
     * Fetches and projects the full collection in one call — shared by
     * renderClientMode() (initial/normal render) and save()/delete()
     * (post-mutation refreshTable() dispatch), so there's exactly one
     * place that knows how to build a row array for this entity.
     *
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListRolesUseCase $useCase): array
    {
        return array_map($this->toRow(...), $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir));
    }

    /**
     * Read-only catalog for the permissions checklist inside the modal.
     * Cross-context read (Role → Permission) kept deliberately thin: a
     * plain array projection, never a Domain Entity or Eloquent Model
     * leaking across the boundary. Both modules live under the same
     * IdentityAccess bounded context, so this pragmatic coupling is
     * acceptable without a dedicated anti-corruption layer.
     *
     * @return array<int, array{name: string, label: string}>
     */
    private function permissionCatalog(ListPermissionsUseCase $useCase): array
    {
        return array_map(
            static fn(Permission $permission) => [
                'name' => $permission->name(),
                'label' => PermissionLabelFormatter::forHumans($permission->module(), $permission->action()),
            ],
            $useCase->all(sortBy: 'module'),
        );
    }
}
