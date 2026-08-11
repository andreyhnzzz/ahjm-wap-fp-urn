<?php

declare(strict_types=1);

namespace Src\Academic\Group\Application\UseCases;

use Src\Academic\Group\Application\DTOs\GroupDTO;
use Src\Academic\Group\Domain\Contracts\GroupRepositoryInterface;
use Src\Academic\Group\Domain\Entities\Group;

final class CreateGroupUseCase
{
    public function __construct(
        private readonly GroupRepositoryInterface $repository,
    ) {}

    public function handle(GroupDTO $dto): Group
    {
        $group = Group::create(
            code: $dto->code,
            courseCode: $dto->courseCode,
            term: $dto->term,
            teacherId: $dto->teacherId,
            classroomId: $dto->classroomId,
            estimatedEnrollment: $dto->estimatedEnrollment,
            assignedWorkload: $dto->assignedWorkload,
            modality: $dto->modality,
            status: $dto->status,
        );

        return $this->repository->save($group);
    }
}
