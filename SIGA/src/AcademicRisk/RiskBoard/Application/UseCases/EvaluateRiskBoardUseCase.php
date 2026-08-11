<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Application\UseCases;

use Src\AcademicRisk\RiskBoard\Domain\Contracts\RiskSourceInterface;
use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskBoard;
use Src\AcademicRisk\RiskBoard\Domain\Services\RiskEvaluator;

/**
 * Fetch the current snapshot of the offer, hand it to the domain, return
 * the board. The use case orchestrates; it decides nothing.
 *
 * There is no caching here on purpose. RE-04 requires the board to
 * reflect a data change within 60 seconds, and a cache is the classic
 * way to accidentally serve a risk that was fixed five minutes ago —
 * the two queries behind this are cheap, and correctness of a live
 * alerting screen outranks shaving milliseconds off it.
 */
final class EvaluateRiskBoardUseCase
{
    public function __construct(
        private readonly RiskSourceInterface $source,
        private readonly RiskEvaluator $evaluator,
    ) {}

    public function handle(): RiskBoard
    {
        return $this->evaluator->evaluate(
            $this->source->groupSnapshots(),
            $this->source->teacherSnapshots(),
        );
    }
}
