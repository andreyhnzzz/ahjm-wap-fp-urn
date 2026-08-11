<?php

declare(strict_types=1);

namespace Src\Academic\Group\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithDataTable;
use App\Livewire\Concerns\InteractsWithExports;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Src\Academic\Classroom\Application\UseCases\ListClassroomsUseCase;
use Src\Academic\Classroom\Domain\Entities\Classroom;
use Src\Academic\Group\Application\UseCases\CreateGroupUseCase;
use Src\Academic\Group\Application\UseCases\DeleteGroupUseCase;
use Src\Academic\Group\Application\UseCases\FindGroupUseCase;
use Src\Academic\Group\Application\UseCases\ListGroupsUseCase;
use Src\Academic\Group\Application\UseCases\UpdateGroupUseCase;
use Src\Academic\Group\Domain\Entities\Group;
use Src\Academic\Group\Presentation\Livewire\Forms\GroupForm;
use Src\Academic\Group\Presentation\Support\GroupLabelFormatter;
use Src\Academic\Teacher\Application\UseCases\ListTeachersUseCase;
use Src\Academic\Teacher\Domain\Entities\Teacher;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Primary adapter for the Group module.
 *
 * It reads two sibling modules' catalogs — Teacher and Classroom —
 * through their public Application use cases, never through their
 * repositories or their Eloquent models. That is the same documented,
 * accepted cross-module read RoleComponent already performs against
 * ListPermissionsUseCase: both sides live inside one bounded context
 * (Academic), speak one ubiquitous language, and change together. The
 * important part is the direction of the dependency — Group depends on
 * Teacher's *use case*, so Teacher's persistence can change entirely
 * without this file noticing.
 */
class GroupComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithDataTable;
    use InteractsWithExports;

    /**
     * A term's offer is in the low hundreds of rows at most, so the whole
     * set ships once and Alpine filters it in the browser. Flip to
     * 'server' if this ever accumulates several years of history in one
     * table.
     */
    protected string $tableMode = 'client';

    public bool $showModal = false;

    public ?int $editingId = null;

    public GroupForm $form;

    public function mount(): void
    {
        $this->authorize('viewAny', Group::class);

        $this->sortKey = 'code';
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Group::class);

        $this->editingId = null;
        $this->form->reset();
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEditModal(int $id, FindGroupUseCase $useCase): void
    {
        $this->authorize('update', Group::class);

        $group = $useCase->handle($id);

        $this->editingId = $id;
        $this->form->fromEntity($group);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    /**
     * Unstaffing a group zeroes its workload right there in the modal.
     *
     * The Group entity refuses the "no teacher but some workload" pair
     * and GroupForm re-states it as a validation rule, but making the
     * user discover that by pressing Save and reading an error is poor
     * design when the correct value is never ambiguous. This is a UI
     * convenience over an already-enforced rule, not a second copy of it.
     */
    public function updatedFormTeacherId(string $value): void
    {
        if ($value === '') {
            $this->form->assignedWorkload = '0.00';
        }
    }

    public function save(
        CreateGroupUseCase $createUseCase,
        UpdateGroupUseCase $updateUseCase,
        ListGroupsUseCase $listUseCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
    ): void {
        $this->form->validate();

        if ($this->editingId === null) {
            $this->authorize('create', Group::class);
            $createUseCase->handle($this->form->toDto());
        } else {
            $this->authorize('update', Group::class);
            $updateUseCase->handle($this->editingId, $this->form->toDto());
        }

        $this->showModal = false;
        $this->refreshTable($this->freshRows($listUseCase, $teachersUseCase, $classroomsUseCase));
        $this->dispatch('toast', variant: 'success', text: $this->editingId === null
            ? __('Group created.')
            : __('Group updated.'));
    }

    public function delete(
        int $id,
        DeleteGroupUseCase $useCase,
        ListGroupsUseCase $listUseCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
    ): void {
        $this->authorize('delete', Group::class);

        $useCase->handle($id);

        $this->refreshTable($this->freshRows($listUseCase, $teachersUseCase, $classroomsUseCase));
        $this->dispatch('toast', variant: 'success', text: __('Group deleted.'));
    }

    public function exportPdf(
        PdfExporterInterface $exporter,
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
        ?string $search = null,
    ): StreamedResponse {
        $this->authorize('exportPdf', Group::class);

        return $this->streamPdf(
            __('Groups'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $teachersUseCase, $classroomsUseCase, $search),
            Str::slug(__('Groups')).'.pdf',
            $exporter,
            paperSize: 'letter',
        );
    }

    public function exportExcel(
        ExcelExporterInterface $exporter,
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
        ?string $search = null,
    ): StreamedResponse {
        $this->authorize('exportExcel', Group::class);

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->exportableRows($useCase, $teachersUseCase, $classroomsUseCase, $search),
            Str::slug(__('Groups')).'.xlsx',
            $exporter,
        );
    }

    public function render(
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
    ): View {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase, $teachersUseCase, $classroomsUseCase)
            : $this->renderClientMode($useCase, $teachersUseCase, $classroomsUseCase);

        $view = $view->with([
            'teacherOptions' => $this->teacherOptions($teachersUseCase),
            'classroomOptions' => $this->classroomOptions($classroomsUseCase),
            'modalityOptions' => GroupLabelFormatter::modalityOptions(),
            'statusOptions' => GroupLabelFormatter::statusOptions(),
        ]);

        /** @disregard P1013 Livewire registra ->layout() como macro en runtime sobre Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Groups'),
            'subtitle' => __('Academic offer groups, their teacher and their classroom'),
        ]);
    }

    private function renderClientMode(
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
    ): View {
        return view('academic.group.livewire.group-component', [
            'tableMode' => 'client',
            'rows' => $this->freshRows($useCase, $teachersUseCase, $classroomsUseCase),
        ]);
    }

    private function renderServerMode(
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
    ): View {
        $result = $useCase->paginate(
            search: $this->authorizedSearch(),
            perPage: $this->perPage,
            page: $this->page,
            sortBy: $this->sortKey,
            sortDir: $this->sortDir,
        );

        $paginator = new LengthAwarePaginator(
            items: array_map(
                fn (Group $group): array => $this->toRow(
                    $group,
                    $this->teacherNames($teachersUseCase),
                    $this->classroomNames($classroomsUseCase),
                ),
                $result['items'],
            ),
            total: $result['total'],
            perPage: $this->perPage,
            currentPage: $this->page,
        );

        return view('academic.group.livewire.group-component', [
            'tableMode' => 'server',
            'groups' => $paginator,
        ]);
    }

    /**
     * Plain-array projection: the teacher and classroom ids the Domain
     * Entity carries are resolved into names here, at the very edge, so
     * neither the entity nor the view has to know how to do it.
     *
     * @param  array<int, string>  $teacherNames
     * @param  array<int, string>  $classroomNames
     * @return array<string, mixed>
     */
    private function toRow(Group $group, array $teacherNames, array $classroomNames): array
    {
        $teacherId = $group->teacherId();
        $classroomId = $group->classroomId();

        return [
            'id' => $group->id(),
            'code' => $group->code(),
            'courseCode' => $group->courseCode(),
            'term' => $group->term(),
            'teacher' => $teacherId !== null ? ($teacherNames[$teacherId] ?? __('Unassigned')) : __('Unassigned'),
            'hasTeacher' => $group->hasTeacher(),
            'classroom' => $classroomId !== null ? ($classroomNames[$classroomId] ?? __('Unassigned')) : __('Unassigned'),
            'hasClassroom' => $group->hasClassroom(),
            'estimatedEnrollment' => $group->estimatedEnrollment(),
            'assignedWorkload' => number_format($group->assignedWorkload(), 2, '.', ''),
            'modality' => GroupLabelFormatter::modality($group->modality()),
            'status' => GroupLabelFormatter::status($group->status()),
            'statusVariant' => GroupLabelFormatter::statusVariant($group->status()),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function freshRows(
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
    ): array {
        $teacherNames = $this->teacherNames($teachersUseCase);
        $classroomNames = $this->classroomNames($classroomsUseCase);

        return array_map(
            fn (Group $group): array => $this->toRow($group, $teacherNames, $classroomNames),
            $useCase->all(sortBy: $this->sortKey, sortDir: $this->sortDir),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportableRows(
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
        ?string $search,
    ): array {
        $teacherNames = $this->teacherNames($teachersUseCase);
        $classroomNames = $this->classroomNames($classroomsUseCase);

        return array_map(
            fn (Group $group): array => $this->toRow($group, $teacherNames, $classroomNames),
            $useCase->all(search: $this->authorizedSearch($search), sortBy: $this->sortKey, sortDir: $this->sortDir),
        );
    }

    /**
     * Id → name lookup built once per request. One query for the whole
     * catalog beats one query per row: this is the N+1 the eager-loaded
     * `with()` in a repository would normally solve, solved here instead
     * because the Domain Entity references a teacher by id, not by
     * object, and that is a deliberate modelling choice.
     *
     * @return array<int, string>
     */
    private function teacherNames(ListTeachersUseCase $useCase): array
    {
        $names = [];

        foreach ($useCase->all(sortBy: 'name') as $teacher) {
            $id = $teacher->id();

            if ($id !== null) {
                $names[$id] = $teacher->name();
            }
        }

        return $names;
    }

    /**
     * @return array<int, string>
     */
    private function classroomNames(ListClassroomsUseCase $useCase): array
    {
        $names = [];

        foreach ($useCase->all(sortBy: 'name') as $classroom) {
            $id = $classroom->id();

            if ($id !== null) {
                $names[$id] = $classroom->name();
            }
        }

        return $names;
    }

    /**
     * Options for the teacher `<select>`. Empty stays a legal choice —
     * INFRA-01 needs groups that can be saved with nobody assigned.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function teacherOptions(ListTeachersUseCase $useCase): array
    {
        return array_map(
            static fn (Teacher $teacher): array => [
                'value' => (string) $teacher->id(),
                'label' => $teacher->name(),
            ],
            $useCase->all(sortBy: 'name'),
        );
    }

    /**
     * The capacity travels in the label on purpose: choosing a room for a
     * group of 40 is the moment a coordinator needs to know the room
     * seats 25, not one screen later.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function classroomOptions(ListClassroomsUseCase $useCase): array
    {
        return array_map(
            static fn (Classroom $classroom): array => [
                'value' => (string) $classroom->id(),
                'label' => $classroom->name().' ('.$classroom->capacity().')',
            ],
            $useCase->all(sortBy: 'name'),
        );
    }

    private function authorizedSearch(?string $explicit = null): ?string
    {
        if (! Auth::user()->can('search', Group::class)) {
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
            ['key' => 'code', 'label' => __('Group code')],
            ['key' => 'courseCode', 'label' => __('Course code')],
            ['key' => 'term', 'label' => __('Term')],
            ['key' => 'teacher', 'label' => __('Teacher')],
            ['key' => 'classroom', 'label' => __('Classroom')],
            ['key' => 'estimatedEnrollment', 'label' => __('Estimated enrollment')],
            ['key' => 'assignedWorkload', 'label' => __('Assigned workload')],
            ['key' => 'modality', 'label' => __('Modality')],
            ['key' => 'status', 'label' => __('Status')],
        ];
    }
}
