<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\Services;

use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskBoard;
use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskItem;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\GroupSnapshot;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskLevel;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskThresholds;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskType;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\TeacherSnapshot;

/**
 * The whole of RE-04's business logic, in one pure function of its
 * inputs.
 *
 * No database, no Eloquent, no HTTP, no clock — give it the same
 * snapshots and it returns the same board, every time. That is what
 * makes the four detection rules and their Alto/Medio/Bajo
 * classification testable without booting Laravel (see
 * tests/Unit/AcademicRisk/RiskEvaluatorTest.php), and what would let
 * this exact class run behind a queue worker, a CLI command or a
 * WebSocket push without a single edit.
 *
 * A Domain *Service* rather than a method on an entity because the rules
 * span several aggregates at once: no single Group can tell whether its
 * teacher is over-committed across all the other groups.
 */
final class RiskEvaluator
{
    /**
     * Decimal places workloads are compared at. The column is
     * decimal(4,2), so two is the real precision of the stored data;
     * rounding to it before comparing keeps binary floating-point noise
     * (0.35 + 0.35 + 0.30 landing a hair above 1.0) from inventing a
     * "jornada en conflicto" that does not exist.
     */
    private const WORKLOAD_PRECISION = 2;

    public function __construct(
        private readonly RiskThresholds $thresholds,
    ) {}

    /**
     * @param  array<int, GroupSnapshot>  $groups
     * @param  array<int, TeacherSnapshot>  $teachers
     */
    public function evaluate(array $groups, array $teachers): RiskBoard
    {
        // A cancelled group is not part of the live offer, so its missing
        // teacher or empty roster is not a risk — it is a decision that
        // was already taken.
        $activeGroups = array_values(array_filter(
            $groups,
            static fn (GroupSnapshot $group): bool => $group->isActive,
        ));

        $items = array_merge(
            $this->groupRisks($activeGroups),
            $this->workloadRisks($activeGroups, $teachers),
        );

        return RiskBoard::of($this->sortedByUrgency($items));
    }

    /**
     * Rules 1, 2 and 3 — all three answerable from a single group.
     *
     * A group can raise more than one: one with no teacher, no classroom
     * and four students legitimately produces three separate items,
     * because they are three separate things a coordinator has to fix.
     *
     * @param  array<int, GroupSnapshot>  $groups
     * @return array<int, RiskItem>
     */
    private function groupRisks(array $groups): array
    {
        $items = [];

        foreach ($groups as $group) {
            if (! $group->hasTeacher()) {
                $items[] = RiskItem::forGroup(
                    type: RiskType::GroupWithoutTeacher,
                    groupId: $group->id,
                    groupCode: $group->code,
                    term: $group->term,
                );
            }

            if (! $group->hasClassroom) {
                $items[] = RiskItem::forGroup(
                    type: RiskType::GroupWithoutClassroom,
                    groupId: $group->id,
                    groupCode: $group->code,
                    term: $group->term,
                );
            }

            if ($group->estimatedEnrollment < $this->thresholds->minimumEnrollment) {
                $items[] = RiskItem::forGroup(
                    type: RiskType::GroupBelowEnrollmentThreshold,
                    groupId: $group->id,
                    groupCode: $group->code,
                    term: $group->term,
                    measuredValue: (float) $group->estimatedEnrollment,
                    thresholdValue: (float) $this->thresholds->minimumEnrollment,
                );
            }
        }

        return $items;
    }

    /**
     * Rule 4 — the only one that cannot be answered by looking at a
     * single group: a teacher is in conflict when the workloads of all
     * their groups *within the same term* add up past the ceiling.
     *
     * Per term, not overall: teaching a full load in 2026-I and another
     * full load in 2026-II is a career, not a conflict.
     *
     * @param  array<int, GroupSnapshot>  $groups
     * @param  array<int, TeacherSnapshot>  $teachers
     * @return array<int, RiskItem>
     */
    private function workloadRisks(array $groups, array $teachers): array
    {
        $directory = [];

        foreach ($teachers as $teacher) {
            $directory[$teacher->id] = $teacher;
        }

        /** @var array<string, array{teacherId: int, term: string, total: float}> $totals */
        $totals = [];

        foreach ($groups as $group) {
            $teacherId = $group->teacherId;

            if ($teacherId === null) {
                continue;
            }

            $key = $teacherId.'|'.$group->term;

            $totals[$key] ??= ['teacherId' => $teacherId, 'term' => $group->term, 'total' => 0.0];
            $totals[$key]['total'] += $group->assignedWorkload;
        }

        $items = [];

        foreach ($totals as $accumulated) {
            $total = round($accumulated['total'], self::WORKLOAD_PRECISION);

            if ($total <= $this->thresholds->maximumWorkload) {
                continue;
            }

            $teacher = $directory[$accumulated['teacherId']] ?? null;

            // A group pointing at a teacher who is not in the directory
            // would mean the two snapshot queries disagreed with each
            // other. Skipping is the honest response: inventing an alert
            // with a blank name would send a coordinator looking for a
            // record that is not there.
            if ($teacher === null) {
                continue;
            }

            $items[] = RiskItem::forTeacher(
                type: RiskType::TeacherWorkloadConflict,
                teacherId: $teacher->id,
                identityCard: $teacher->identityCard,
                name: $teacher->name,
                term: $accumulated['term'],
                measuredValue: $total,
                thresholdValue: $this->thresholds->maximumWorkload,
            );
        }

        return $items;
    }

    /**
     * High first, then Medium, then Low; alphabetically by the affected
     * record within each band, so the board does not reshuffle between
     * two polls that found the same problems.
     *
     * @param  array<int, RiskItem>  $items
     * @return array<int, RiskItem>
     */
    private function sortedByUrgency(array $items): array
    {
        $urgency = array_flip(array_map(
            static fn (RiskLevel $level): string => $level->value,
            RiskLevel::byUrgency(),
        ));

        usort($items, static function (RiskItem $first, RiskItem $second) use ($urgency): int {
            return [$urgency[$first->level()->value], $first->subjectCode, $first->type->value]
                <=> [$urgency[$second->level()->value], $second->subjectCode, $second->type->value];
        });

        return $items;
    }
}
