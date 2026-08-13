<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Src\Academic\Classroom\Application\UseCases\CreateClassroomUseCase;
use Src\Academic\Classroom\Application\UseCases\DeleteClassroomUseCase;
use Src\Academic\Classroom\Application\UseCases\FindClassroomUseCase;
use Src\Academic\Classroom\Application\UseCases\ListClassroomsUseCase;
use Src\Academic\Classroom\Application\UseCases\UpdateClassroomUseCase;
use Src\Academic\Classroom\Domain\Entities\Classroom;
use Src\Academic\Classroom\Presentation\Livewire\Forms\ClassroomForm;

/**
 * Primary adapter for the Classroom module — the "lista de aulas y sus
 * capacidades" INFRA-01 asks for.
 */
class ClassroomComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    /** A campus has tens of rooms, not thousands: client-side table. */
    protected string $tableMode = 'client';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ClassroomForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Classroom::class);

        $this->sortKey = 'name';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Classroom::class);

        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindClassroomUseCase $useCase): void
    {
        $this->authorize('update', Classroom::class);

        $classroom = $useCase->handle($id);

        $this->editingId = $id;
        $this->form->fromEntity($classroom);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreateClassroomUseCase $createUseCase, UpdateClassroomUseCase $updateUseCase, ListClassroomsUseCase $listUseCase): void
    {
        $this->form->validate();

        if ($this->editingId === null) {
            $this->authorize('create', Classroom::class);
            $createUseCase->handle($this->form->toDto());
        } else {
            $this->authorize('update', Classroom::class);
            $updateUseCase->handle($this->editingId, $this->form->toDto());
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: $this->editingId === null
            ? __('Classroom created.')
            : __('Classroom updated.'));
    }

    public function delete(int $id, DeleteClassroomUseCase $useCase, ListClassroomsUseCase $listUseCase): void
    {
        $this->authorize('delete', Classroom::class);

        $useCase->handle($id);

        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Classroom deleted.'));
    }

    public function exportPdf(ListClassroomsUseCase $useCase, ?string $search = null): void
    {
        $this->authorize('exportPdf', Classroom::class);

        $this->queuePdfExport(
            __('Classrooms'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Classrooms')).'.pdf',
            paperSize: 'letter',
        );

        // See RoleComponent::exportPdf() — without this, rows stays at
        // the [] every post-first-render commit sends, and the table
        // goes empty until a full reload.
        $this->refreshTable($this->freshRows($useCase));
    }

    public function exportExcel(ListClassroomsUseCase $useCase, ?string $search = null): void
    {
        $this->authorize('exportExcel', Classroom::class);

        $this->queueExcelExport(
            __('Classrooms'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Classrooms')).'.xlsx',
        );

        $this->refreshTable($this->freshRows($useCase));
    }

    public function render(ListClassroomsUseCase $useCase): View
    {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase, $this->isFirstRender());

        /** @disregard P1013 Livewire registers ->layout() as a runtime macro on Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Classrooms'),
            'subtitle' => __('Available physical spaces by campus'),
        ]);
    }

    private function renderClientMode(ListClassroomsUseCase $useCase, bool $firstRender): View
    {
        return view('academic.classroom.livewire.classroom-component', [
            'tableMode' => 'client',
            'rows' => $firstRender ? $this->freshRows($useCase) : [],
        ]);
    }

    private function renderServerMode(ListClassroomsUseCase $useCase): View
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

        return view('academic.classroom.livewire.classroom-component', [
            'tableMode' => 'server',
            'classrooms' => $paginator,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Classroom $classroom): array
    {
        return [
            'id' => $classroom->id(),
            'name' => $classroom->name(),
            'capacity' => $classroom->capacity(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListClassroomsUseCase $useCase): array
    {
        return array_map($this->toRow(...), $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListClassroomsUseCase $useCase, ?string $search): array
    {
        return array_map(
            $this->toRow(...),
            $useCase->all(search: $this->authorizedSearch($search), sortBy: $this->sortKey, sortDir: $this->sortDir),
        );
    }

    private function authorizedSearch(?string $explicit = null): ?string
    {
        if (! Auth::user()->can('search', Classroom::class)) {
            return null;
        }

        $candidate = filled($explicit) ? $explicit : $this->search;

        return $candidate !== '' ? $candidate : null;
    }

    /**
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'capacity', 'label' => __('Capacity')],
        ];
    }
}
