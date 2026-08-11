<?php

declare(strict_types=1);

namespace Src\Academic\Group\Domain\Contracts;

use Src\Academic\Group\Domain\Entities\Group;

/**
 * Port the Infrastructure adapter must implement.
 *
 * Note what is NOT here: no "groups without a teacher", no "workload per
 * teacher". Those are questions the risk board and the reports ask, and
 * each of those contexts owns its own read port for them
 * (RiskSourceInterface, OfferSourceInterface, TeacherLoadSourceInterface).
 * Keeping analytics queries out of the write-side repository is what
 * stops this interface from slowly turning into the application's
 * god-object.
 */
interface GroupRepositoryInterface
{
    public function find(int $id): ?Group;

    /**
     * @return array<int, Group>
     */
    public function all(?string $search = null, ?string $sortBy = null, string $sortDir = 'asc'): array;

    /**
     * @return array{items: array<int, Group>, total: int}
     */
    public function paginate(?string $search, int $perPage, int $page, ?string $sortBy = null, string $sortDir = 'asc'): array;

    public function save(Group $group): Group;

    public function delete(int $id): void;
}
