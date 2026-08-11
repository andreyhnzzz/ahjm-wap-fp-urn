<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Application\UseCases;

use Src\Academic\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\Academic\Teacher\Domain\Entities\Teacher;
use Src\Academic\Teacher\Domain\Exceptions\TeacherNotFoundException;

final class FindTeacherUseCase
{
    public function __construct(
        private readonly TeacherRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Teacher
    {
        return $this->repository->find($id) ?? throw TeacherNotFoundException::withId($id);
    }
}
