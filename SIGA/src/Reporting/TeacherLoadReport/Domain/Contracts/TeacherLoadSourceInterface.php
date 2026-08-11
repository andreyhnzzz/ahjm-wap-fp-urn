<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Domain\Contracts;

use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherLoadRow;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherReference;

/**
 * Read port of the teacher load report.
 *
 * `rowsFor()` returns the rows, never the sum: totalling them is a
 * business rule (RE-02 compares that total against the reference), and
 * the moment a SUM() lands in SQL the rule stops being testable without
 * a database and starts being invisible to anyone reading the domain.
 */
interface TeacherLoadSourceInterface
{
    /**
     * @return array<int, TeacherReference>
     */
    public function availableTeachers(): array;

    public function findTeacher(int $teacherId): ?TeacherReference;

    /**
     * The groups this teacher carries in this term.
     *
     * @return array<int, TeacherLoadRow>
     */
    public function rowsFor(int $teacherId, string $term): array;

    /**
     * @return array<int, string>
     */
    public function availableTerms(): array;
}
