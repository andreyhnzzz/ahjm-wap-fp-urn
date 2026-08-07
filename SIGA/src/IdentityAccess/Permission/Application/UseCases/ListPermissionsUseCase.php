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
     * @return array{items: array<int, Permission>, total: int}
     */
    public function handle(?string $search = null, ?string $module = null, int $perPage = 10, int $page = 1): array
    {
        return $this->repository->paginate($search, $module, $perPage, $page);
    }
}
