<?php

declare(strict_types=1);

namespace Src\Academic\Group\Application\UseCases;

use Src\Academic\Group\Domain\Contracts\GroupRepositoryInterface;
use Src\Academic\Group\Domain\Entities\Group;
use Src\Academic\Group\Domain\Exceptions\GroupNotFoundException;

final class FindGroupUseCase
{
    public function __construct(
        private readonly GroupRepositoryInterface $repository,
    ) {}

    public function handle(int $id): Group
    {
        return $this->repository->find($id) ?? throw GroupNotFoundException::withId($id);
    }
}
