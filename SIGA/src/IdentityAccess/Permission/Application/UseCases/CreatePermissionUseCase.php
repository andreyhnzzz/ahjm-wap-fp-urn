<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Application\UseCases;

use Src\IdentityAccess\Permission\Application\DTOs\PermissionDTO;
use Src\IdentityAccess\Permission\Domain\Contracts\PermissionRepositoryInterface;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;

final class CreatePermissionUseCase
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repository,
    ) {}

    public function handle(PermissionDTO $dto): Permission
    {
        $permission = Permission::create(module: $dto->module, action: $dto->action);

        return $this->repository->save($permission);
    }
}
