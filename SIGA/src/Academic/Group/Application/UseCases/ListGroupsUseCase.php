<?php

declare(strict_types=1);

namespace Src\Academic\Group\Application\UseCases;

use Src\Academic\Group\Domain\Contracts\GroupRepositoryInterface;
use Src\Academic\Group\Domain\Entities\Group;

final class ListGroupsUseCase
{
    public function __construct(
        private readonly GroupRepositoryInterface $repository,
    ) {}

    /**
     * @return array<int, Group>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        return $this->repository->all($search, $sortBy, $sortDir);
    }

    /**
     * @return array{items: array<int, Group>, total: int}
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
