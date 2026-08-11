<?php

declare(strict_types=1);

namespace Src\Academic\Group\Domain\ValueObjects;

/**
 * Lifecycle state of a group within its term.
 *
 * `Cancelled` matters beyond bookkeeping: a cancelled group is not part
 * of the live offer, so it is excluded from the risk board (RE-04) —
 * flagging a cancelled group for having no teacher would be noise, not
 * a risk.
 */
enum GroupStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * Whether a group in this state still represents a commitment the
     * campus has to be able to deliver.
     */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
