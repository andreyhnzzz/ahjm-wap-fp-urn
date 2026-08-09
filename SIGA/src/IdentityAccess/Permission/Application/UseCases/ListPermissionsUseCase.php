<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Application\UseCases;

use Src\IdentityAccess\Permission\Domain\Contracts\PermissionRepositoryInterface;
use Src\IdentityAccess\Permission\Domain\Entities\Permission;

final class ListPermissionsUseCase
{
    public function __construct(
        private readonly PermissionRepositoryInterface $repository,
    ) {}

    /**
     * Full, unpaginated collection — used by client-side (Alpine) tables
     * that resolve search/sort/pagination in the browser without any
     * further round-trip to the server. Intended for small catalogs.
     *
     * $search: see PermissionRepositoryInterface::all() — lets
     * exportPdf()/exportExcel() honor whatever's currently in the
     * search box.
     *
     * @return array<int, Permission>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        return $this->repository->all($search, $sortBy, $sortDir);
    }

    /**
     * Server-paginated collection — used by server-side tables handling
     * datasets too large to ship to the client in a single response.
     *
     * @return array{items: array<int, Permission>, total: int}
     */
    public function paginate(
        ?string $search = null,
        ?string $module = null,
        int $perPage = 10,
        int $page = 1,
        ?string $sortBy = null,
        string $sortDir = 'asc',
    ): array {
        return $this->repository->paginate($search, $module, $perPage, $page, $sortBy, $sortDir);
    }
}
