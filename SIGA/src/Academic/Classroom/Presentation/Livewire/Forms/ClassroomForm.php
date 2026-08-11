<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Presentation\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;
use Src\Academic\Classroom\Application\DTOs\ClassroomDTO;
use Src\Academic\Classroom\Domain\Entities\Classroom;
use Src\Academic\Classroom\Presentation\Livewire\ClassroomComponent;

/**
 * Input boundary for the Classroom create/edit modal.
 *
 * `$capacity` is a string for the same reason TeacherForm's workload is:
 * clearing a number input sends `''`, and assigning that to a typed int
 * property is a TypeError that aborts the request before validation can
 * report anything useful.
 */
class ClassroomForm extends Form
{
    public string $name = '';

    public string $capacity = '30';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ClassroomComponent $component */
        $component = $this->component;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classrooms', 'name')->ignore($component->editingId),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'name' => __('Name'),
            'capacity' => __('Capacity'),
        ];
    }

    public function fromEntity(Classroom $classroom): void
    {
        $this->name = $classroom->name();
        $this->capacity = (string) $classroom->capacity();
    }

    public function toDto(): ClassroomDTO
    {
        return new ClassroomDTO(name: $this->name, capacity: (int) $this->capacity);
    }
}
