<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Application\UseCases;

use Src\Academic\Classroom\Domain\Contracts\ClassroomRepositoryInterface;
use Src\Academic\Classroom\Domain\Exceptions\ClassroomNotFoundException;

/**
 * Groups referencing the classroom are left in place with a null
 * classroom (the FK is nullOnDelete), which the risk board then reports
 * as "sin aula" — see DeleteTeacherUseCase for the same reasoning.
 */
final class DeleteClassroomUseCase
{
    public function __construct(
        private readonly ClassroomRepositoryInterface $repository,
    ) {}

    public function handle(int $id): void
    {
        $this->repository->find($id) ?? throw ClassroomNotFoundException::withId($id);

        $this->repository->delete($id);
    }
}
