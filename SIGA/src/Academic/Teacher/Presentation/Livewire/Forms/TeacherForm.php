<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Presentation\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;
use Src\Academic\Teacher\Application\DTOs\TeacherDTO;
use Src\Academic\Teacher\Domain\Entities\Teacher;
use Src\Academic\Teacher\Presentation\Livewire\TeacherComponent;

/**
 * Input boundary for the Teacher create/edit modal.
 *
 * `$referenceWorkload` is typed string, not float, deliberately: a
 * `wire:model` bound to a number input hands over `''` the instant the
 * user clears the field, and assigning `''` to a typed float property is
 * a PHP TypeError that kills the whole Livewire request before any
 * validation rule ever runs. Keeping it a string lets `numeric` produce
 * a proper field error instead, and the cast to float happens once, in
 * toDto(), on the way into the Application layer.
 */
class TeacherForm extends Form
{
    public string $identityCard = '';

    public string $name = '';

    public string $referenceWorkload = '1.00';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TeacherComponent $component */
        $component = $this->component;

        return [
            'identityCard' => [
                'required',
                'string',
                'max:30',
                Rule::unique('teachers', 'identity_card')->ignore($component->editingId),
            ],
            'name' => ['required', 'string', 'max:255'],
            // Upper bound mirrors the decimal(4,2) column; the lower bound
            // mirrors the Teacher entity invariant (a reference workload of
            // zero would make RE-02's 80% comparison meaningless).
            'referenceWorkload' => ['required', 'numeric', 'min:0.01', 'max:9.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'identityCard' => __('Identity card'),
            'name' => __('Name'),
            'referenceWorkload' => __('Estimated workload'),
        ];
    }

    /**
     * Hydrates the form from an existing Teacher for the edit modal.
     * Named `fromEntity()` and not `fill()`: Livewire\Form already
     * declares its own `fill(mixed $values)`, and narrowing that
     * parameter type would be a real LSP violation.
     */
    public function fromEntity(Teacher $teacher): void
    {
        $this->identityCard = $teacher->identityCard();
        $this->name = $teacher->name();
        $this->referenceWorkload = number_format($teacher->referenceWorkload(), 2, '.', '');
    }

    public function toDto(): TeacherDTO
    {
        return new TeacherDTO(
            identityCard: $this->identityCard,
            name: $this->name,
            referenceWorkload: (float) $this->referenceWorkload,
        );
    }
}
