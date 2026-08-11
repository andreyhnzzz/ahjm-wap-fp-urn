<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Application\UseCases;

use Src\Academic\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\Academic\Teacher\Domain\Entities\Teacher;

final class ListTeachersUseCase
{
    public function __construct(
        private readonly TeacherRepositoryInterface $repository,
    ) {}

    /**
     * Full, unpaginated collection — used by the client-side (Alpine)
     * table, by the exports, and by the Group modal's teacher selector.
     *
     * @return array<int, Teacher>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array
    {
        return $this->repository->all($search, $sortBy, $sortDir);
    }

    /**
     * Server-paginated collection — kept for the day this catalog grows
     * past what a single response should carry.
     *
     * @return array{items: array<int, Teacher>, total: int}
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
