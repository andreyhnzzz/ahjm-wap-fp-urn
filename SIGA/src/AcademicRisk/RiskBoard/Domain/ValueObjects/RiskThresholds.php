<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The two numbers RE-04 requires to be configurable, carried into the
 * domain as a single Value Object rather than as two loose floats.
 *
 * The domain never reads config/academic.php itself — that would put a
 * Laravel helper inside src/. The composition root resolves the values
 * and hands them over here (see DomainServiceProvider), which is also
 * what makes RiskEvaluator trivially testable with any threshold.
 */
final readonly class RiskThresholds
{
    public function __construct(
        public int $minimumEnrollment,
        public float $maximumWorkload,
    ) {
        if ($minimumEnrollment < 0) {
            throw new InvalidArgumentException("The minimum enrollment threshold cannot be negative, [{$minimumEnrollment}] given.");
        }

        if ($maximumWorkload <= 0.0) {
            throw new InvalidArgumentException("The maximum workload threshold must be greater than zero, [{$maximumWorkload}] given.");
        }
    }
}
