<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\IdentityAccess\Permission\Application\UseCases\DeletePermissionUseCase;
use Src\IdentityAccess\Permission\Application\UseCases\ListPermissionsUseCase;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;

class PermissionComponent extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public int $perPage = 10;

    public int $page = 1;

    public string $sortKey = 'module';

    public string $sortDir = 'asc';

    public bool $showCreateModal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Permission::class);
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
        $this->authorize('create', Permission::class);
        $this->showCreateModal = true;
    }

    public function delete(int $id, DeletePermissionUseCase $useCase): void
    {
        $this->authorize('delete', Permission::class);

        $useCase->handle($id);

        $this->dispatch('toast', variant: 'success', text: __('Permission deleted.'));
    }

    public function exportPdf(): void
    {
        $this->authorize('exportPdf', Permission::class);
        $this->dispatch('toast', variant: 'info', text: __('Export coming soon.'));
    }

    public function exportExcel(): void
    {
        $this->authorize('exportExcel', Permission::class);
        $this->dispatch('toast', variant: 'info', text: __('Export coming soon.'));
    }

    public function render(ListPermissionsUseCase $useCase): View
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

        return view('identityaccess.permission.livewire.permission-component', [
            'permissions' => $paginator,
        ]);
    }
}
