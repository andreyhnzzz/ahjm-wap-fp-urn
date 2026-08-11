<?php

declare(strict_types=1);

namespace Src\Academic\Group\Application\UseCases;

use Src\Academic\Group\Domain\Contracts\GroupRepositoryInterface;
use Src\Academic\Group\Domain\Exceptions\GroupNotFoundException;

final class DeleteGroupUseCase
{
    public function __construct(
        private readonly GroupRepositoryInterface $repository,
    ) {}

    public function handle(int $id): void
    {
        $this->repository->find($id) ?? throw GroupNotFoundException::withId($id);

        $this->repository->delete($id);
    }
}
