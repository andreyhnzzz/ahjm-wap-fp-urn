<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Src\Academic\Teacher\Application\UseCases\CreateTeacherUseCase;
use Src\Academic\Teacher\Application\UseCases\DeleteTeacherUseCase;
use Src\Academic\Teacher\Application\UseCases\FindTeacherUseCase;
use Src\Academic\Teacher\Application\UseCases\ListTeachersUseCase;
use Src\Academic\Teacher\Application\UseCases\UpdateTeacherUseCase;
use Src\Academic\Teacher\Domain\Entities\Teacher;
use Src\Academic\Teacher\Presentation\Livewire\Forms\TeacherForm;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Primary adapter (Hexagonal) for the Teacher module: it translates user
 * intent into Application use cases and Domain Entities into plain rows
 * for the view. It holds no business rule of its own — every decision it
 * appears to make is either an authorization check or a view concern.
 */
class TeacherComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    /**
     * A campus teaching staff is a small, reference-style catalog (tens,
     * not thousands), so the whole list ships once and Alpine resolves
     * search/sort/pagination in the browser with zero further round-trips.
     */
    protected string $tableMode = 'client';

    public bool $showModal = false;

    /**
     * Null while creating; the row's id while editing. Also read by
     * TeacherForm::rules() to scope the uniqueness check correctly.
     */
    public ?int $editingId = null;

    public TeacherForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Teacher::class);

        // Always assigned here, never as a class property: redeclaring a
        // trait property with a different default is a fatal PHP
        // composition error.
        $this->sortKey = 'name';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Teacher::class);

        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindTeacherUseCase $useCase): void
    {
        $this->authorize('update', Teacher::class);

        $teacher = $useCase->handle($id);

        $this->editingId = $id;
        $this->form->fromEntity($teacher);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(CreateTeacherUseCase $createUseCase, UpdateTeacherUseCase $updateUseCase, ListTeachersUseCase $listUseCase): void
    {
        $this->form->validate();

        if ($this->editingId === null) {
            $this->authorize('create', Teacher::class);
            $createUseCase->handle($this->form->toDto());
        } else {
            $this->authorize('update', Teacher::class);
            $updateUseCase->handle($this->editingId, $this->form->toDto());
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: $this->editingId === null
            ? __('Teacher created.')
            : __('Teacher updated.'));
    }

    /**
     * Method name must stay `delete`: <x-ui.confirm-delete-modal> calls
     * `$wire.delete(id)` blindly — that convention is what makes the modal
     * reusable across every CRUD.
     */
    public function delete(int $id, DeleteTeacherUseCase $useCase, ListTeachersUseCase $listUseCase): void
    {
        $this->authorize('delete', Teacher::class);

        $useCase->handle($id);

        $this->refreshTable($this->freshRows($listUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Teacher deleted.'));
    }

    public function exportPdf(PdfExporterInterface $exporter, ListTeachersUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportPdf', Teacher::class);

        return $this->streamPdf(
            __('Teachers'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Teachers')).'.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(ExcelExporterInterface $exporter, ListTeachersUseCase $useCase, ?string $search = null): StreamedResponse
    {
        $this->authorize('exportExcel', Teacher::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $search),
            Str::slug(__('Teachers')).'.xlsx',
            $exporter,
        );
    }

    public function render(ListTeachersUseCase $useCase): View
    {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase)
            : $this->renderClientMode($useCase, $this->isFirstRender());

        /** @disregard P1013 Livewire registra ->layout() como macro en runtime sobre Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Teachers'),
            'subtitle' => __('Active teaching staff and their academic load'),
        ]);
    }

    private function renderClientMode(ListTeachersUseCase $useCase, bool $firstRender): View
    {
        return view('academic.teacher.livewire.teacher-component', [
            'tableMode' => 'client',
            'rows' => $firstRender ? $this->freshRows($useCase) : [],
        ]);
    }

    private function renderServerMode(ListTeachersUseCase $useCase): View
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

        return view('academic.teacher.livewire.teacher-component', [
            'tableMode' => 'server',
            'teachers' => $paginator,
        ]);
    }

    /**
     * Plain-array projection handed to Alpine as JSON — the Domain Entity
     * never crosses the Presentation boundary.
     *
     * @return array<string, mixed>
     */
    private function toRow(Teacher $teacher): array
    {
        return [
            'id' => $teacher->id(),
            'identityCard' => $teacher->identityCard(),
            'name' => $teacher->name(),
            'referenceWorkload' => number_format($teacher->referenceWorkload(), 2, '.', ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(ListTeachersUseCase $useCase): array
    {
        return array_map($this->toRow(...), $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(ListTeachersUseCase $useCase, ?string $search): array
    {
        return array_map(
            $this->toRow(...),
            $useCase->all(search: $this->authorizedSearch($search), sortBy: $this->sortKey, sortDir: $this->sortDir),
        );
    }

    /**
     * A user without `teachers.search` must not be able to narrow an
     * export by passing a term the UI never offered them.
     */
    private function authorizedSearch(?string $explicit = null): ?string
    {
        if (! Auth::user()->can('search', Teacher::class)) {
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
            ['key' => 'identityCard', 'label' => __('Identity card')],
            ['key' => 'name', 'label' => __('Name')],
            ['key' => 'referenceWorkload', 'label' => __('Estimated workload')],
        ];
    }
}
