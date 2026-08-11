<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Application\UseCases;

use Src\Academic\Classroom\Application\DTOs\ClassroomDTO;
use Src\Academic\Classroom\Domain\Contracts\ClassroomRepositoryInterface;
use Src\Academic\Classroom\Domain\Entities\Classroom;

final class CreateClassroomUseCase
{
    public function __construct(
        private readonly ClassroomRepositoryInterface $repository,
    ) {}

    public function handle(ClassroomDTO $dto): Classroom
    {
        $classroom = Classroom::create(name: $dto->name, capacity: $dto->capacity);

        return $this->repository->save($classroom);
    }
}
