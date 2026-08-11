<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Domain\Contracts;

use Src\Academic\Teacher\Domain\Entities\Teacher;

/**
 * Port (Hexagonal) the Infrastructure adapter must implement. Domain and
 * Application depend on this abstraction only — never on Eloquent, the
 * database, or any concrete driver.
 *
 * Same shape as RoleRepositoryInterface on purpose: `all()` feeds the
 * client-side table and the exports, `paginate()` is there for the day
 * this catalog outgrows a single response.
 */
interface TeacherRepositoryInterface
{
    public function find(int $id): ?Teacher;

    /**
     * @return array<int, Teacher>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, Teacher>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function save(Teacher $teacher): Teacher;

    public function delete(int $id): void;
}
