<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Role\Application\UseCases;

use Src\IdentityAccess\Role\Domain\Contracts\RoleRepositoryInterface;
use Src\IdentityAccess\Role\Domain\Entities\Role;

final class ListRolesUseCase
{
    public function __construct(
        private readonly RoleRepositoryInterface $repository,
    ) {}

    /**
     * Full, unpaginated collection — used by client-side (Alpine) tables
     * that resolve search/sort/pagination in the browser without any
     * further round-trip to the server. Intended for small catalogs.
     *
     * @return array<int, Role>
     */
    public function all(?string $sortBy = null, string $sortDir = 'asc'): array
    {
        return $this->repository->all($sortBy, $sortDir);
    }

    /**
     * Server-paginated collection — used by server-side tables handling
     * datasets too large to ship to the client in a single response.
     *
     * @return array{items: array<int, Role>, total: int}
     */
    public function paginate(
        ?string $search = null,
        int $perPage = 10,
        int $page = 1,
        ?string $sortBy = null,
        string $sortDir = 'asc',
    ): array {
        return $this->repository->paginate($search, $perPage, $page, $sortBy, $sortDir);
    }
}
