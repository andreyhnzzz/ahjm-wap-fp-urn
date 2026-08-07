<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Application\UseCases;

use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Entities\Role;
use Src\IdentityAccess\Role\Domain\Exceptions\RoleNotFoundException;

final class FindRoleUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Role
    {
        return $this->repository->find($id) ?? throw RoleNotFoundException::withId($id);
    }
}
