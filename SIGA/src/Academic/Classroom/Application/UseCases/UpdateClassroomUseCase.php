<?php

declare(strict_types=1);

namespace Src\Academic\Classroom\Application\UseCases;

use Src\Academic\Classroom\Application\DTOs\ClassroomDTO;
use Src\Academic\Classroom\Domain\Contracts\ClassroomRepositoryInterface;
use Src\Academic\Classroom\Domain\Entities\Classroom;
use Src\Academic\Classroom\Domain\Exceptions\ClassroomNotFoundException;

final class UpdateClassroomUseCase
{
    public function __construct(
        private readonly ClassroomRepositoryInterface $repository,
    ) {}

    public function handle(int $id, ClassroomDTO $dto): Classroom
    {
        $classroom = $this->repository->find($id) ?? throw ClassroomNotFoundException::withId($id);

        $classroom->rename($dto->name);
        $classroom->changeCapacity($dto->capacity);

        return $this->repository->save($classroom);
    }
}
