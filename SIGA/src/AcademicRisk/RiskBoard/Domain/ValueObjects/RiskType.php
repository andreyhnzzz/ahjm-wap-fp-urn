<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\ValueObjects;

/**
 * The four risks RE-04 scopes this project to, plus the classification
 * the requirement fixes for each one.
 *
 * The level lives on the type rather than being passed in when an item
 * is built, because it is not a per-item decision: "a group with no
 * teacher is High" is a rule about the *kind* of problem. Encoding it
 * here means no call site can ever file the same problem under two
 * different severities, and changing a classification is a one-line
 * change in the one place that owns it.
 */
enum RiskType: string
{
    case GroupWithoutTeacher = 'group_without_teacher';
    case GroupWithoutClassroom = 'group_without_classroom';
    case GroupBelowEnrollmentThreshold = 'group_below_enrollment_threshold';
    case TeacherWorkloadConflict = 'teacher_workload_conflict';

    public function level(): RiskLevel
    {
        return match ($this) {
            self::GroupWithoutTeacher, self::GroupWithoutClassroom => RiskLevel::High,
            self::TeacherWorkloadConflict => RiskLevel::Medium,
            self::GroupBelowEnrollmentThreshold => RiskLevel::Low,
        };
    }

    public function subject(): RiskSubject
    {
        return match ($this) {
            self::TeacherWorkloadConflict => RiskSubject::Teacher,
            default => RiskSubject::Group,
        };
    }
}
