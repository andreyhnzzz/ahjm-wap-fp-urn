<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Application\UseCases;

use Src\Academic\Teacher\Application\DTOs\TeacherDTO;
use Src\Academic\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\Academic\Teacher\Domain\Entities\Teacher;
use Src\Academic\Teacher\Domain\Exceptions\TeacherNotFoundException;

final class UpdateTeacherUseCase
{
    public function __construct(
        private readonly TeacherRepositoryInterface $repository,
    ) {}

    public function handle(int $id, TeacherDTO $dto): Teacher
    {
        $teacher = $this->repository->find($id) ?? throw TeacherNotFoundException::withId($id);

        $teacher->changeIdentityCard($dto->identityCard);
        $teacher->rename($dto->name);
        $teacher->changeReferenceWorkload($dto->referenceWorkload);

        return $this->repository->save($teacher);
    }
}
