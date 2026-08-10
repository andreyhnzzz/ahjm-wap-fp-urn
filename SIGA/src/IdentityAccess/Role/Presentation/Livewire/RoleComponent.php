<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoleComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

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
        $role = $useCase->handle($id);
        $this->authorize('update', $role);

        $this->editingId = $id;
        $this->form->fromEntity($role);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreateRoleUseCase $createUseCase, UpdateRoleUseCase $updateUseCase, ListRolesUseCase $listUseCase, FindRoleUseCase $findUseCase): void
    {
        $this->form->validate();

        try {
            if ($this->editingId === null) {
                $this->authorize('create', Role::class);
                $createUseCase->handle($this->form->toDto());
            } else {
                // RolePolicy::update() needs an actual Role instance as
                // its 2nd parameter (it checks per-row rules, not just
                // "can this user edit roles in general") — Role::class
                // alone isn't enough for Laravel to call it, hence
                // fetching the entity first instead of authorizing
                // against the bare class string.
                $this->authorize('update', $findUseCase->handle($this->editingId));
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

    public function delete(int $id, DeleteRoleUseCase $useCase, ListRolesUseCase $listUseCase, FindRoleUseCase $findUseCase): void
    {
        // Same reasoning as save()'s update branch: RolePolicy::delete()
        // requires an actual Role instance, not the class string.
        $this->authorize('delete', $findUseCase->handle($id));

        try {
            $useCase->handle($id);
        } catch (RoleIsProtectedException $e) {
            $this->dispatch('toast', variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Role deleted.'));
    }

    public function exportPdf(PdfExporterInterface $exporter, ListRolesUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportPdf', Role::class);

        return $this->streamPdf(
            __('Roles'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Roles')) . '.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListRolesUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', Role::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Roles')) . '.xlsx',
            $exporter,
        );
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
            search: $this->authorizedSearch(),
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
     *
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListRolesUseCase $useCase, ?string $search): array
    {
        return array_map(
            $this->toRow(...),
            $useCase->all(search: $this->authorizedSearch($search), sortBy: $this->sortKey, sortDir: $this->sortDir),
        );
    }


    private function authorizedSearch(?string $explicit = null): ?string
    {
        if (! Auth::user()->can('search', Role::class)) {
            return null;
        }

        $candidate = filled($explicit) ? $explicit : $this->search;

        return $candidate !== '' ? $candidate : null;
    }

    /**
     *
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'permissionsCount', 'label' => __('Permissions')],
            ['key' => 'protected', 'label' => __('Type'), 'format' => fn(bool $protected): string => $protected ? __('System') : __('Custom')],
        ];
    }

    /**
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
