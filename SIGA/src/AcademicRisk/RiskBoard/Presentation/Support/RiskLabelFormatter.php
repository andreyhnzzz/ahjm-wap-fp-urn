<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Presentation\Support;

use Src\Academic\Group\Presentation\Support\GroupLabelFormatter;
use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskItem;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskLevel;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskSubject;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskType;

/**
 * Everything about a risk item that only makes sense in front of a
 * human: its wording, its colour, and the URL of the record it is about.
 *
 * All three are kept out of the domain for the same reason. A `title()`
 * on RiskType would drag `__()` — and therefore Laravel — into the
 * domain layer; a `url()` on RiskItem would make the domain aware that
 * this application has routes at all. RiskItem carries the facts
 * (which type, which record, which numbers); this class is the only
 * place that turns them into a sentence.
 *
 * @see GroupLabelFormatter
 */
final class RiskLabelFormatter
{
    public static function title(RiskType $type): string
    {
        return match ($type) {
            RiskType::GroupWithoutTeacher => __('Group without an assigned teacher'),
            RiskType::GroupWithoutClassroom => __('Group without an assigned classroom'),
            RiskType::GroupBelowEnrollmentThreshold => __('Group below the enrollment threshold'),
            RiskType::TeacherWorkloadConflict => __('Teacher with a workload conflict'),
        };
    }

    /**
     * The one-line explanation under the title, with the numbers that
     * justify the alert filled in.
     */
    public static function description(RiskItem $item): string
    {
        return match ($item->type) {
            RiskType::GroupWithoutTeacher => __('Group :code has nobody assigned to teach it in :term.', [
                'code' => $item->subjectCode,
                'term' => $item->term,
            ]),
            RiskType::GroupWithoutClassroom => __('Group :code has no classroom assigned in :term.', [
                'code' => $item->subjectCode,
                'term' => $item->term,
            ]),
            RiskType::GroupBelowEnrollmentThreshold => __('Group :code estimates :students students, below the threshold of :threshold.', [
                'code' => $item->subjectCode,
                'students' => self::integer($item->measuredValue),
                'threshold' => self::integer($item->thresholdValue),
            ]),
            RiskType::TeacherWorkloadConflict => __(':name accumulates :assigned of workload in :term, above the limit of :limit.', [
                'name' => $item->subjectName,
                'assigned' => self::decimal($item->measuredValue),
                'term' => $item->term,
                'limit' => self::decimal($item->thresholdValue),
            ]),
        };
    }

    public static function levelLabel(RiskLevel $level): string
    {
        return match ($level) {
            RiskLevel::High => __('High'),
            RiskLevel::Medium => __('Medium'),
            RiskLevel::Low => __('Low'),
        };
    }

    /**
     * CSS modifier appended to `.risk-*` classes.
     */
    public static function levelVariant(RiskLevel $level): string
    {
        return $level->value;
    }

    /**
     * Direct link to the affected record, as RE-04 requires.
     *
     * Both target screens run a client-side table whose search box is
     * seeded from the `q` query string (see the `initialSearch` prop of
     * <x-ui.data-table>), so the destination opens already filtered down
     * to the single row the alert is about.
     */
    public static function link(RiskItem $item): string
    {
        return match ($item->subject()) {
            RiskSubject::Group => route('academic.group.index', ['q' => $item->subjectCode]),
            RiskSubject::Teacher => route('academic.teacher.index', ['q' => $item->subjectCode]),
        };
    }

    public static function subjectLabel(RiskItem $item): string
    {
        return match ($item->subject()) {
            RiskSubject::Group => __('Group :code', ['code' => $item->subjectCode]),
            RiskSubject::Teacher => $item->subjectName,
        };
    }

    private static function integer(?float $value): string
    {
        return $value === null ? '—' : (string) (int) $value;
    }

    private static function decimal(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 2);
    }
}
