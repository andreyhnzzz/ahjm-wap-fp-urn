<?php

declare(strict_types=1);

namespace Src\Academic\Group\Presentation\Support;

use Src\Academic\Group\Domain\ValueObjects\GroupStatus;
use Src\Academic\Group\Domain\ValueObjects\Modality;
use Src\IdentityAccess\Permission\Presentation\Support\PermissionLabelFormatter;

/**
 * Turns the Group domain enums into the strings a user reads, and into
 * the CSS modifier that colours their badge.
 *
 * This lives in Presentation, not on the enums themselves, for the rule
 * the whole project is built on: the Domain layer must not know that a
 * UI, a language or a stylesheet exist. Put a `label()` method on
 * Modality and the enum suddenly needs `__()`, which means Laravel,
 * which means the domain no longer loads without the framework.
 *
 * Same reason the translation keys here are English: the key is code,
 * the Spanish value lives in lang/es.json.
 *
 * @see PermissionLabelFormatter
 */
final class GroupLabelFormatter
{
    public static function modality(Modality $modality): string
    {
        return match ($modality) {
            Modality::InPerson => __('In person'),
            Modality::Virtual => __('Virtual'),
            Modality::Blended => __('Blended'),
        };
    }

    public static function status(GroupStatus $status): string
    {
        return match ($status) {
            GroupStatus::Open => __('Open'),
            GroupStatus::Closed => __('Closed'),
            GroupStatus::Cancelled => __('Cancelled'),
        };
    }

    /**
     * CSS modifier appended to `.status-badge` so each state keeps the
     * same colour everywhere it appears (table, risk board, reports).
     */
    public static function statusVariant(GroupStatus $status): string
    {
        return match ($status) {
            GroupStatus::Open => 'open',
            GroupStatus::Closed => 'closed',
            GroupStatus::Cancelled => 'cancelled',
        };
    }

    /**
     * Options for the modality `<select>`, ready to iterate in Blade.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function modalityOptions(): array
    {
        return array_map(
            static fn (Modality $modality): array => [
                'value' => $modality->value,
                'label' => self::modality($modality),
            ],
            Modality::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function statusOptions(): array
    {
        return array_map(
            static fn (GroupStatus $status): array => [
                'value' => $status->value,
                'label' => self::status($status),
            ],
            GroupStatus::cases(),
        );
    }
}
