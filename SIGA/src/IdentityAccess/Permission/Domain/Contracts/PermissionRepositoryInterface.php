<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Domain\Contracts;

use Src\IdentityAccess\Permission\Domain\Entities\Permission;

interface PermissionRepositoryInterface
{
    public function find(int $id): ?Permission;

    /**
     * Full, unpaginated collection — backs client-side (Alpine) tables
     * that resolve search/sort/pagination in the browser. Reserved for
     * datasets small enough to ship to the client in one response.
     *
     * @return array<int, Permission>
     */
    public function all(?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, Permission>, total: int}
     */
    public function paginate(?string $search, ?string $module, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function save(Permission $permission): Permission;

    public function delete(int $id): void;
}
