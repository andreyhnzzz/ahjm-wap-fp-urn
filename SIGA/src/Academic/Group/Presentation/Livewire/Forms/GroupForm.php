<?php

declare(strict_types=1);

namespace Src\Academic\Group\Presentation\Livewire\Forms;

use Closure;
use Illuminate\Validation\Rule;
use Livewire\Form;
use Src\Academic\Group\Application\DTOs\GroupDTO;
use Src\Academic\Group\Domain\Entities\Group;
use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;
use Src\Academic\Group\Presentation\Livewire\GroupComponent;

/**
 * Input boundary for the Group create/edit modal.
 *
 * Every field is a string because that is what an HTML control actually
 * sends — including the empty string a cleared number input or an
 * unselected `<select>` produces. Typed int/float properties would turn
 * that empty string into a TypeError before validation ever ran; the
 * casts happen once, in toDto().
 *
 * The teacher and classroom selects are allowed to stay empty on
 * purpose: INFRA-01 requires saving deliberately incomplete groups so
 * the risk board has something to detect.
 */
class GroupForm extends Form
{
    public string $code = '';

    public string $courseCode = '';

    public string $term = '';

    public string $teacherId = '';

    public string $classroomId = '';

    public string $estimatedEnrollment = '25';

    public string $assignedWorkload = '0.00';

    public string $modality = 'in_person';

    public string $status = 'open';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var GroupComponent $component */
        $component = $this->component;

        return [
            'code' => [
                'required',
                'string',
                'max:60',
                Rule::unique('groups', 'code')->ignore($component->editingId),
            ],
            'courseCode' => ['required', 'string', 'max:30'],
            'term' => ['required', 'string', 'max:20'],
            // An unselected <select> hands over '', not null, and Laravel's
            // `nullable` only excuses an actual null — `exists` would run
            // anyway and look up the empty string as an id. Skipping the
            // rule set outright when the field is empty is the honest
            // expression of "this relation is optional".
            'teacherId' => $this->teacherId === ''
                ? []
                : ['integer', Rule::exists('teachers', 'id')],
            'classroomId' => $this->classroomId === ''
                ? []
                : ['integer', Rule::exists('classrooms', 'id')],
            'estimatedEnrollment' => ['required', 'integer', 'min:0', 'max:500'],
            'assignedWorkload' => [
                'required',
                'numeric',
                'min:0',
                'max:9.99',
                $this->workloadRequiresATeacher(),
            ],
            'modality' => ['required', Rule::in(Modality::values())],
            'status' => ['required', Rule::in(GroupStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'code' => __('Group code'),
            'courseCode' => __('Course code'),
            'term' => __('Term'),
            'teacherId' => __('Teacher'),
            'classroomId' => __('Classroom'),
            'estimatedEnrollment' => __('Estimated enrollment'),
            'assignedWorkload' => __('Assigned workload'),
            'modality' => __('Modality'),
            'status' => __('Status'),
        ];
    }

    public function fromEntity(Group $group): void
    {
        $this->code = $group->code();
        $this->courseCode = $group->courseCode();
        $this->term = $group->term();
        $this->teacherId = $group->teacherId() === null ? '' : (string) $group->teacherId();
        $this->classroomId = $group->classroomId() === null ? '' : (string) $group->classroomId();
        $this->estimatedEnrollment = (string) $group->estimatedEnrollment();
        // Normalized rather than shown as stored: a group can reach the
        // database with a leftover workload and no teacher when the
        // teacher row was deleted (the FK nulls the reference and leaves
        // the number behind — see Group::reconstitute()). Presenting the
        // stale figure would make the user fix a state they did not
        // create just to be allowed to save.
        $this->assignedWorkload = $group->hasTeacher()
            ? number_format($group->assignedWorkload(), 2, '.', '')
            : '0.00';
        $this->modality = $group->modality()->value;
        $this->status = $group->status()->value;
    }

    public function toDto(): GroupDTO
    {
        return new GroupDTO(
            code: $this->code,
            courseCode: $this->courseCode,
            term: $this->term,
            teacherId: $this->teacherId === '' ? null : (int) $this->teacherId,
            classroomId: $this->classroomId === '' ? null : (int) $this->classroomId,
            estimatedEnrollment: (int) $this->estimatedEnrollment,
            assignedWorkload: (float) $this->assignedWorkload,
            modality: Modality::from($this->modality),
            status: GroupStatus::from($this->status),
        );
    }

    /**
     * Mirrors the Group entity invariant at the edge, so the user gets a
     * field-level message instead of an unhandled domain exception. The
     * entity keeps its own check regardless — this rule protects the UX,
     * the invariant protects the data.
     */
    private function workloadRequiresATeacher(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->teacherId === '' && (float) $value > 0.0) {
                $fail(__('The assigned workload must be 0 while the group has no teacher.'));
            }
        };
    }
}
