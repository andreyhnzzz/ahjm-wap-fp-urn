<?php

declare(strict_types=1);

namespace Src\Academic\Group\Domain\ValueObjects;

/**
 * How a group is delivered. A backed enum rather than a free string, so
 * an impossible modality cannot reach the database or a report in the
 * first place.
 *
 * The backing values are the persisted ones and are English, like every
 * identifier in this codebase; the Spanish labels users read are
 * translation keys resolved at the Presentation boundary (see
 * GroupLabelFormatter), never here — the Domain layer has no opinion on
 * what language the campus speaks.
 */
enum Modality: string
{
    case InPerson = 'in_person';
    case Virtual = 'virtual';
    case Blended = 'blended';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
