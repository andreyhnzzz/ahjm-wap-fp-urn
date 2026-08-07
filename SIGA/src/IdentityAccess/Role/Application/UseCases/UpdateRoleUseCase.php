<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Application\UseCases;

use Src\IdentityAccess\Role\Application\DTOs\RoleDTO;
use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Entities\Role;
use Src\IdentityAccess\Role\Domain\Exceptions\RoleNotFoundException;

final class UpdateRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
    ) {}

    public function handle(int $id, RoleDTO $dto): Role
    {
        $role = $this->repository->find($id) ?? throw RoleNotFoundException::withId($id);

        $role->rename($dto->name);
        $role->syncPermissions($dto->permissions);

        return $this->repository->save($role);
    }
}
