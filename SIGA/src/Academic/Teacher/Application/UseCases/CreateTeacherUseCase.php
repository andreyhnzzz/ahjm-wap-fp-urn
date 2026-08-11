<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Application\UseCases;

use Src\Academic\Teacher\Application\DTOs\TeacherDTO;
use Src\Academic\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\Academic\Teacher\Domain\Entities\Teacher;

/**
 * Single-purpose orchestrator (SRP): turns a TeacherDTO into a persisted
 * Teacher. Depends on the repository port only — the concrete
 * EloquentTeacherRepository is wired in by the container (see
 * App\Providers\DomainServiceProvider) and never instantiated here.
 */
final class CreateTeacherUseCase
{
    public function __construct(
        private readonly TeacherRepositoryInterface $repository,
    ) {}

    public function handle(TeacherDTO $dto): Teacher
    {
        $teacher = Teacher::create(
            identityCard: $dto->identityCard,
            name: $dto->name,
            referenceWorkload: $dto->referenceWorkload,
        );

        return $this->repository->save($teacher);
    }
}
