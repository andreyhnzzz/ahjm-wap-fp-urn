<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\Entities;

use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskLevel;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskSubject;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskType;

/**
 * One entry on the board: a single problem, about a single record.
 *
 * Immutable by construction. A risk item is never edited — it exists
 * while the condition holds and stops being produced the moment the data
 * is corrected, which is exactly the appear/disappear behaviour RE-04's
 * acceptance criteria describe. There is nothing to update, so nothing
 * here can be.
 *
 * `measuredValue` / `thresholdValue` carry the numbers behind the alert
 * (3 students against a threshold of 5; 1.25 of workload against a
 * ceiling of 1.00) as plain floats. Formatting them into a sentence is
 * Presentation's job — the domain does not build user-facing text.
 */
final readonly class RiskItem
{
    private function __construct(
        public RiskType $type,
        public int $subjectId,
        public string $subjectCode,
        public string $subjectName,
        public string $term,
        public ?float $measuredValue,
        public ?float $thresholdValue,
    ) {}

    public static function forGroup(
        RiskType $type,
        int $groupId,
        string $groupCode,
        string $term,
        ?float $measuredValue = null,
        ?float $thresholdValue = null,
    ): self {
        return new self(
            type: $type,
            subjectId: $groupId,
            subjectCode: $groupCode,
            subjectName: $groupCode,
            term: $term,
            measuredValue: $measuredValue,
            thresholdValue: $thresholdValue,
        );
    }

    public static function forTeacher(
        RiskType $type,
        int $teacherId,
        string $identityCard,
        string $name,
        string $term,
        float $measuredValue,
        float $thresholdValue,
    ): self {
        return new self(
            type: $type,
            subjectId: $teacherId,
            subjectCode: $identityCard,
            subjectName: $name,
            term: $term,
            measuredValue: $measuredValue,
            thresholdValue: $thresholdValue,
        );
    }

    public function level(): RiskLevel
    {
        return $this->type->level();
    }

    public function subject(): RiskSubject
    {
        return $this->type->subject();
    }

    /**
     * Stable identity for this alert, so the UI can key a list on it and
     * so the same problem is never counted twice. Two items are the same
     * alert when they are the same problem about the same record — the
     * measured value is not part of the identity, since a group dropping
     * from 4 students to 3 is the same alert, not a new one.
     */
    public function fingerprint(): string
    {
        return $this->type->value.':'.$this->subjectId.':'.$this->term;
    }
}
