<?php

declare(strict_types=1);

namespace Src\Academic\Teacher\Application\UseCases;

use Src\Academic\Teacher\Domain\Contracts\TeacherRepositoryInterface;
use Src\Academic\Teacher\Domain\Exceptions\TeacherNotFoundException;

/**
 * Deleting a teacher is allowed even when groups still reference them:
 * the FK is `nullOnDelete`, so those groups become "sin docente" instead
 * of disappearing, and the risk board (RE-04) immediately reports them
 * as High risk. That is the intended, auditable behaviour — silently
 * removing academic history to keep a delete "clean" would be worse.
 */
final class DeleteTeacherUseCase
{
    public function __construct(
        private readonly TeacherRepositoryInterface $repository,
    ) {}

    public function handle(int $id): void
    {
        $this->repository->find($id) ?? throw TeacherNotFoundException::withId($id);

        $this->repository->delete($id);
    }
}
