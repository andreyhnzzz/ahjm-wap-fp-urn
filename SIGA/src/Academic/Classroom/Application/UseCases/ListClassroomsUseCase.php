<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Application\UseCases;

use Src\Academic\Classroom\Domain\Contracts\ClassroomRepositoryInterface;
use Src\Academic\Classroom\Domain\Entities\Classroom;

final class ListClassroomsUseCase
{
    public function __construct(
        private readonly ClassroomRepositoryInterface $repository,
    ) {}

    /**
     * Full collection — feeds the client-side table, the exports, and the
     * classroom selector inside the Group modal.
     *
     * @return array<int, Classroom>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        return $this->repository->all($search, $sortBy, $sortDir);
    }

    /**
     * @return array{items: array<int, Classroom>, total: int}
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
