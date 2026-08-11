<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Application\UseCases;

use Src\Reporting\TeacherLoadReport\Domain\Contracts\TeacherLoadSourceInterface;

final class ListTeacherLoadTermsUseCase
{
    public function __construct(
        private readonly TeacherLoadSourceInterface $source,
    ) {}

    /**
     * @return array<int, string>
     */
    public function handle(): array
    {
        return $this->source->availableTerms();
    }
}
