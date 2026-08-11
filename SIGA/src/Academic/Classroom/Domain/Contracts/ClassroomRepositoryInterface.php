<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Domain\Contracts;

use Src\Academic\Classroom\Domain\Entities\Classroom;

/**
 * Port the Infrastructure adapter must implement. Domain and Application
 * depend on this abstraction only.
 */
interface ClassroomRepositoryInterface
{
    public function find(int $id): ?Classroom;

    /**
     * @return array<int, Classroom>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, Classroom>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function save(Classroom $classroom): Classroom;

    public function delete(int $id): void;
}
