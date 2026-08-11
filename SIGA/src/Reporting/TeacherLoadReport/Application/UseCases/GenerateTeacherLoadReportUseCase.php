<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Application\UseCases;

use DateTimeImmutable;
use Src\Reporting\TeacherLoadReport\Domain\Contracts\TeacherLoadSourceInterface;
use Src\Reporting\TeacherLoadReport\Domain\Entities\TeacherLoadReport;
use Src\Reporting\TeacherLoadReport\Domain\Exceptions\TeacherNotInDirectoryException;

/**
 * Assembles one teacher's load report for one term.
 *
 * `$underLoadRatio` arrives through the constructor rather than being
 * read from config here: the Application layer stays framework-free, and
 * the composition root (DomainServiceProvider) is the single place that
 * translates configuration into a domain parameter. It also means a test
 * can pin the ratio to anything it likes without touching config.
 */
final class GenerateTeacherLoadReportUseCase
{
    public function __construct(
        private readonly TeacherLoadSourceInterface $source,
        private readonly float $underLoadRatio,
    ) {}

    public function handle(int $teacherId, string $term): TeacherLoadReport
    {
        $teacher = $this->source->findTeacher($teacherId)
            ?? throw TeacherNotInDirectoryException::withId($teacherId);

        return TeacherLoadReport::assemble(
            teacher: $teacher,
            term: $term,
            rows: $this->source->rowsFor($teacherId, $term),
            underLoadRatio: $this->underLoadRatio,
            generatedAt: new DateTimeImmutable,
        );
    }
}
