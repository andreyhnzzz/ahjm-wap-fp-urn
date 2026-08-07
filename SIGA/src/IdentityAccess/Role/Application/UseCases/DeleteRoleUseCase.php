<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Application\UseCases;

use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Exceptions\RoleIsProtectedException;
use Src\IdentityAccess\Role\Domain\Exceptions\RoleNotFoundException;

final class DeleteRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
    ) {}

    public function handle(int $id): void
    {
        $role = $this->repository->find($id) ?? throw RoleNotFoundException::withId($id);

        if ($role->isProtected()) {
            throw RoleIsProtectedException::forName($role->name());
        }

        $this->repository->delete($id);
    }
}
