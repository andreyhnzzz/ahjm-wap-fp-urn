<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\ValueObjects;

/**
 * Severity band a risk item is filed under. RE-04 fixes the three bands
 * and the board groups by them.
 */
enum RiskLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * Descending urgency, so a board can be ordered without the caller
     * hardcoding the sequence of the cases.
     *
     * @return array<int, self>
     */
    public static function byUrgency(): array
    {
        return [self::High, self::Medium, self::Low];
    }
}
