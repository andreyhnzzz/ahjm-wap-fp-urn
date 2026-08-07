<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\IdentityAccess\Role\Application\UseCases\DeleteRoleUseCase;
use Src\IdentityAccess\Role\Application\UseCases\ListRolesUseCase;
use Src\IdentityAccess\Role\Domain\Entities\Role;
use Src\IdentityAccess\Role\Domain\Exceptions\RoleIsProtectedException;

class RoleComponent extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public int $perPage = 10;

    public int $page = 1;

    public string $sortKey = 'name';

    public string $sortDir = 'asc';

    public bool $showCreateModal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    public function updatingSearch(): void
    {
        $this->page = 1;
    }

    public function updatingPerPage(): void
    {
        $this->page = 1;
    }

    public function sort(string $key): void
    {
        $this->sortDir = $this->sortKey === $key && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortKey = $key;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Role::class);
        $this->showCreateModal = true;
    }

    public function delete(int $id, DeleteRoleUseCase $useCase): void
    {
        $this->authorize('delete', Role::class);

        try {
            $useCase->handle($id);
        } catch (RoleIsProtectedException $e) {
            $this->dispatch('toast', variant: 'danger', text: $e->getMessage());

            return;
        }

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

    public function render(ListRolesUseCase $useCase): View
    {
        $result = $useCase->handle(
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
            'roles' => $paginator,
        ]);
    }
}
