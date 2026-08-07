<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Application\UseCases;

use Src\IdentityAccess\Permission\Domain\Contracts\PermissionRepositoryInterface;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;
use Src\IdentityAccess\Permission\Domain\Exceptions\PermissionNotFoundException;

final class FindPermissionUseCase
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Permission
    {
        return $this->repository->find($id) ?? throw PermissionNotFoundException::withId($id);
    }
}
