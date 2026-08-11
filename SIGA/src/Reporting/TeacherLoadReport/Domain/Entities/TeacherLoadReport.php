<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Domain\Entities;

use DateTimeImmutable;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherLoadRow;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherReference;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\WorkloadStatus;

/**
 * TeacherLoadReport — Aggregate Root of the Reporting\TeacherLoadReport
 * module: one teacher, one term, every group they carry, and the verdict
 * on their total load.
 *
 * All three numbers RE-02 compares — assigned total, reference, and the
 * resulting alert — are derived here from the rows, never passed in
 * pre-computed. That is the point of the aggregate: a caller cannot hand
 * it a total that disagrees with its own rows, so the coloured badge and
 * the table below it are guaranteed to tell the same story.
 */
final class TeacherLoadReport
{
    /**
     * Workloads are compared at the precision the column actually
     * stores (decimal(4,2)); see RiskEvaluator for the same reasoning
     * about binary floating-point noise flipping a verdict.
     */
    private const WORKLOAD_PRECISION = 2;

    /**
     * @param  array<int, TeacherLoadRow>  $rows
     */
    private function __construct(
        private readonly TeacherReference $teacher,
        private readonly string $term,
        private readonly array $rows,
        private readonly float $underLoadRatio,
        private readonly DateTimeImmutable $generatedAt,
    ) {}

    /**
     * @param  array<int, TeacherLoadRow>  $rows
     */
    public static function assemble(
        TeacherReference $teacher,
        string $term,
        array $rows,
        float $underLoadRatio,
        DateTimeImmutable $generatedAt,
    ): self {
        return new self($teacher, $term, array_values($rows), $underLoadRatio, $generatedAt);
    }

    public function teacher(): TeacherReference
    {
        return $this->teacher;
    }

    public function term(): string
    {
        return $this->term;
    }

    /**
     * @return array<int, TeacherLoadRow>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function groupCount(): int
    {
        return count($this->rows);
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function assignedWorkload(): float
    {
        return round(
            array_sum(array_map(
                static fn (TeacherLoadRow $row): float => $row->assignedWorkload,
                $this->rows,
            )),
            self::WORKLOAD_PRECISION,
        );
    }

    public function referenceWorkload(): float
    {
        return $this->teacher->referenceWorkload;
    }

    public function status(): WorkloadStatus
    {
        return WorkloadStatus::classify(
            assigned: $this->assignedWorkload(),
            reference: $this->referenceWorkload(),
            underLoadRatio: $this->underLoadRatio,
        );
    }

    /**
     * Assigned load as a fraction of the reference, for the percentage
     * the report prints next to the badge. The reference is guaranteed
     * positive by TeacherReference, so there is no division to guard.
     */
    public function utilization(): float
    {
        return round($this->assignedWorkload() / $this->referenceWorkload(), 4);
    }

    public function underLoadRatio(): float
    {
        return $this->underLoadRatio;
    }

    public function totalEstimatedEnrollment(): int
    {
        return array_sum(array_map(
            static fn (TeacherLoadRow $row): int => $row->estimatedEnrollment,
            $this->rows,
        ));
    }
}
