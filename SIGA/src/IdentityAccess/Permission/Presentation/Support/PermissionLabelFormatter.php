<?php

declare(strict_types=1);

namespace Src\IdentityAccess\Permission\Presentation\Support;

/**
 * Turns a raw (module, action) pair into a human-readable label for the
 * Role modal's permissions checklist — e.g. ('roles', 'edit') -> "Edit
 * roles". Pure presentation formatting, no business rule lives here.
 *
 * Both halves go through __() so the displayed language follows the
 * app locale instead of being frozen into this class; the keys are the
 * English wording, translated in lang/es.json like everywhere else.
 * Unknown actions/modules fall back to a readable default, so a newly
 * seeded permission never renders blank.
 */
final class PermissionLabelFormatter
{
    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'create' => 'Create',
        'view' => 'View',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'search' => 'Search',
        'export_pdf' => 'Export PDF of',
        'export_excel' => 'Export Excel of',
    ];

    /** @var array<string, string> */
    private const MODULE_LABELS = [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'teachers' => 'teachers',
        'classrooms' => 'classrooms',
        'groups' => 'academic groups',
        'offer_reports' => 'academic offer reports',
        'teacher_load_reports' => 'teacher load reports',
        'risk_board' => 'risk board',
    ];

    public static function forHumans(string $module, string $action): string
    {
        $actionLabel = isset(self::ACTION_LABELS[$action])
            ? __(self::ACTION_LABELS[$action])
            : ucfirst(str_replace('_', ' ', $action));

        $moduleLabel = isset(self::MODULE_LABELS[$module])
            ? __(self::MODULE_LABELS[$module])
            : $module;

        return "{$actionLabel} {$moduleLabel}";
    }
}
