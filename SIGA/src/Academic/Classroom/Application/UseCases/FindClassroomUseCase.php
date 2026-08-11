<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Application\UseCases;

use Src\Academic\Classroom\Domain\Contracts\ClassroomRepositoryInterface;
use Src\Academic\Classroom\Domain\Entities\Classroom;
use Src\Academic\Classroom\Domain\Exceptions\ClassroomNotFoundException;

final class FindClassroomUseCase
{
    public function __construct(
        private readonly ClassroomRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Classroom
    {
        return $this->repository->find($id) ?? throw ClassroomNotFoundException::withId($id);
    }
}
