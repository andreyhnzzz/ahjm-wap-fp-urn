<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Domain\Contracts;

use Src\IdentityAccess\Permission\Domain\Entities\Permission;

interface PermissionRepositoryInterface
{
    public function find(int $id): ?Permission;

    /**
     * @return array{items: array<int, Permission>, total: int}
     */
    public function paginate(?string $search, ?string $module, int $perPage, int $page): array;

    public function save(Permission $permission): Permission;

    public function delete(int $id): void;
}
