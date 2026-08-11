<?php

declare(strict_types=1);

namespace Src\Academic\Group\Application\UseCases;

use Src\Academic\Group\Application\DTOs\GroupDTO;
use Src\Academic\Group\Domain\Contracts\GroupRepositoryInterface;
use Src\Academic\Group\Domain\Entities\Group;
use Src\Academic\Group\Domain\Exceptions\GroupNotFoundException;

/**
 * Applies the edit as a sequence of intention-revealing domain
 * operations rather than a bulk setter dump. Each call is a sentence a
 * coordinator would recognise ("assign this teacher", "move it to this
 * classroom"), and each one re-checks the invariants that operation can
 * break — which a single `->fill($array)` would silently skip.
 */
final class UpdateGroupUseCase
{
    public function __construct(
        private readonly GroupRepositoryInterface $repository,
    ) {}

    public function handle(int $id, GroupDTO $dto): Group
    {
        $group = $this->repository->find($id) ?? throw GroupNotFoundException::withId($id);

        $group->relabel($dto->code, $dto->courseCode);
        $group->reschedule($dto->term);
        $group->assignTeacher($dto->teacherId, $dto->assignedWorkload);
        $group->assignClassroom($dto->classroomId);
        $group->estimateEnrollment($dto->estimatedEnrollment);
        $group->changeModality($dto->modality);
        $group->changeStatus($dto->status);

        return $this->repository->save($group);
    }
}
