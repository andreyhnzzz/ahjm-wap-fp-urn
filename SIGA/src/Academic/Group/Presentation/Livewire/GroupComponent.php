<?php

declare(strict_types=1);

namespace Src\Academic\Group\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithAutocomplete;
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
    use InteractsWithAutocomplete;
    use InteractsWithDataTable;
    use InteractsWithExports;

    /**
     * Was 'client' — the whole set shipped once and Alpine filtered it in
     * the browser — on the premise that "a term's offer is in the low
     * hundreds of rows at most". That premise broke: with 45,000 groups
     * loaded, freshRows() hydrates 45,000 Group entities in one request
     * (168 MB measured, over PHP's default 128 MB limit) and /groups dies
     * with a fatal before rendering a single row. The same flip the old
     * comment already prescribed for "several years of history in one
     * table" is the fix — server mode pages the query instead.
     *
     * Note this bounds what the *screen* loads, not what an export
     * loads: exportPdf() still builds every matching row in the request
     * that dispatches the job (192 MB peak at 45,000 rows, 12 MB of job
     * payload), so that path needs a memory_limit well above the default.
     */
    protected string $tableMode = 'server';

    public bool $showModal = false;

    public ?int $editingId = null;

    public GroupForm $form;

    /**
     * Live queries behind the two autocompletes. They hold what the user
     * is TYPING, never the selection itself — that stays on $form, so a
     * half-typed search can never be mistaken for a chosen teacher.
     */
    public string $teacherQuery = '';

    public string $classroomQuery = '';

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
    /**
     * The autocompletes write through these rather than binding straight
     * to $form.teacherId, so choosing goes through the same
     * updatedFormTeacherId() hook a <select> triggered — the workload
     * field is enabled/cleared by that hook, and skipping it would leave
     * a group with a workload and nobody to carry it.
     */
    public function selectTeacher(string $value): void
    {
        $this->form->teacherId = $value;
        $this->teacherQuery = '';
        $this->updatedFormTeacherId($value);
    }

    public function clearTeacher(): void
    {
        $this->selectTeacher('');
    }

    public function selectClassroom(string $value): void
    {
        $this->form->classroomId = $value;
        $this->classroomQuery = '';
    }

    public function clearClassroom(): void
    {
        $this->selectClassroom('');
    }

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
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
        ?string $search = null,
    ): void {
        $this->authorize('exportPdf', Group::class);

        $this->queuePdfExport(
            __('Groups'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $teachersUseCase, $classroomsUseCase, $search),
            Str::slug(__('Groups')).'.pdf',
            paperSize: 'letter',
        );

        // See RoleComponent::exportPdf() — without this, rows stays at
        // the [] every post-first-render commit sends, and the table
        // goes empty until a full reload.
        $this->refreshTable($this->freshRows($useCase, $teachersUseCase, $classroomsUseCase));
    }

    public function exportExcel(
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
        ?string $search = null,
    ): void {
        $this->authorize('exportExcel', Group::class);

        $this->queueExcelExport(
            __('Groups'),
            $this->exportHeaders(),
            $this->exportableRows($useCase, $teachersUseCase, $classroomsUseCase, $search),
            Str::slug(__('Groups')).'.xlsx',
        );

        $this->refreshTable($this->freshRows($useCase, $teachersUseCase, $classroomsUseCase));
    }

    public function render(
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
    ): View {
        $view = $this->isServerMode()
            ? $this->renderServerMode($useCase, $teachersUseCase, $classroomsUseCase)
            : $this->renderClientMode($useCase, $teachersUseCase, $classroomsUseCase, $this->isFirstRender());

        // teacherOptions/classroomOptions are NOT gated like rows: these
        // <select> options are plain server-rendered Blade @foreach
        // output with no Alpine layer at all, so Livewire morphs them
        // fresh on every render — skipping the fetch here would empty
        // both dropdowns the moment any modal reopens after the first load.
        $teacherOptions = $this->teacherOptions($teachersUseCase);
        $classroomOptions = $this->classroomOptions($classroomsUseCase);

        $view = $view->with([
            'teacherOptions' => $teacherOptions,
            'classroomOptions' => $classroomOptions,
            // What the two autocompletes actually show: the full lists
            // narrowed to the current query. Filtering here rather than in
            // the browser keeps the payload proportional to what is
            // displayed instead of to the whole catalogue.
            'teacherSuggestions' => $this->filterOptions($teacherOptions, $this->teacherQuery),
            'classroomSuggestions' => $this->filterOptions($classroomOptions, $this->classroomQuery),
            'teacherSelectedLabel' => $this->labelFor($teacherOptions, $this->form->teacherId),
            'classroomSelectedLabel' => $this->labelFor($classroomOptions, $this->form->classroomId),
            'modalityOptions' => GroupLabelFormatter::modalityOptions(),
            'statusOptions' => GroupLabelFormatter::statusOptions(),
        ]);

        /** @disregard P1013 Livewire registers ->layout() as a runtime macro on Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Groups'),
            'subtitle' => __('Academic offer groups, their teacher and their classroom'),
        ]);
    }

    private function renderClientMode(
        ListGroupsUseCase $useCase,
        ListTeachersUseCase $teachersUseCase,
        ListClassroomsUseCase $classroomsUseCase,
        bool $firstRender,
    ): View {
        return view('academic.group.livewire.group-component', [
            'tableMode' => 'client',
            'rows' => $firstRender ? $this->freshRows($useCase, $teachersUseCase, $classroomsUseCase) : [],
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

        // Both catalogs are resolved BEFORE the array_map, not inside it:
        // teacherNames()/classroomNames() each hit the database, so calling
        // them per row was the very N+1 freshRows() already avoids. Measured
        // at perPage=10: 20 queries / 78 ms per render against 2 queries /
        // 4 ms — and it grew linearly with the "show N records" selector.
        $teacherNames = $this->teacherNames($teachersUseCase);
        $classroomNames = $this->classroomNames($classroomsUseCase);

        $paginator = new LengthAwarePaginator(
            items: array_map(
                fn (Group $group): array => $this->toRow($group, $teacherNames, $classroomNames),
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
