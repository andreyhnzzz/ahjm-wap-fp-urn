<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Presentation\Livewire\Forms;

use Illuminate\Validation\Rule;
use Livewire\Form;
use Src\IdentityAccess\Role\Application\DTOs\RoleDTO;
use Src\IdentityAccess\Role\Domain\Entities\Role;
use Src\IdentityAccess\Role\Presentation\Livewire\RoleComponent;

/**
 * Livewire Form Object — the input boundary for the Role create/edit
 * modal. Now that the HTTP adapter (Controller/FormRequest) is opt-in
 * only (see DDDStructure's --api flag), this replaces the old
 * RoleRequest: same job, scoped to the one primary adapter this app
 * actually uses.
 */
class RoleForm extends Form
{
    public string $name = '';

    /** @var array<int, string> */
    public array $permissions = [];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var RoleComponent $component */
        $component = $this->component;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($component->editingId),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];
    }

    /**
     * Hydrates the form from an existing Role for the edit modal. Named
     * `fromEntity()` rather than `fill()` deliberately — `Livewire\Form`
     * already declares its own `fill(mixed $values)` for bulk-filling
     * from an array/model, and overriding it with a narrower parameter
     * type is a real LSP violation PHPStan rightly refuses to let pass.
     */
    public function fromEntity(Role $role): void
    {
        $this->name = $role->name();
        $this->permissions = $role->permissions();
    }

    public function toDto(): RoleDTO
    {
        return new RoleDTO(name: $this->name, permissions: $this->permissions);
    }
}
