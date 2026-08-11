<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Application\UseCases;

use Src\Reporting\TeacherLoadReport\Domain\Contracts\TeacherLoadSourceInterface;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherReference;

/**
 * The teachers a load report can be produced for — the options of the
 * selector, and the allow-list the request is validated against.
 */
final class ListReportTeachersUseCase
{
    public function __construct(
        private readonly TeacherLoadSourceInterface $source,
    ) {}

    /**
     * @return array<int, TeacherReference>
     */
    public function handle(): array
    {
        return $this->source->availableTeachers();
    }
}
