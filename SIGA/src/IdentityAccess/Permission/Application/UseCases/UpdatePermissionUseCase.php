<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Application\UseCases;

use Src\IdentityAccess\Permission\Application\DTOs\PermissionDTO;
use Src\IdentityAccess\Permission\Domain\Contracts\PermissionRepositoryInterface;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;
use Src\IdentityAccess\Permission\Domain\Exceptions\PermissionNotFoundException;

final class UpdatePermissionUseCase
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repository,
    ) {}

    public function handle(int $id, PermissionDTO $dto): Permission
    {
        $permission = $this->repository->find($id) ?? throw PermissionNotFoundException::withId($id);

        $permission->redefine($dto->module, $dto->action);

        return $this->repository->save($permission);
    }
}
