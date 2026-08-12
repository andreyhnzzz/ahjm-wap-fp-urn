<?php

declare(strict_types=1);

namespace Src\AcademicRisk\RiskBoard\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Src\AcademicRisk\RiskBoard\Application\UseCases\EvaluateRiskBoardUseCase;
use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskBoard;
use Src\AcademicRisk\RiskBoard\Domain\Entities\RiskItem;
use Src\AcademicRisk\RiskBoard\Domain\ValueObjects\RiskLevel;
use Src\AcademicRisk\RiskBoard\Presentation\Support\RiskLabelFormatter;

/**
 * Primary adapter for RE-04.
 *
 * How the "updates without a manual reload" requirement is met: the view
 * carries `wire:poll.{n}s`, so Livewire re-invokes render() on a timer,
 * which re-runs the evaluation against live data. Nothing is cached and
 * no alert is stored, so an item appears on the first poll after its
 * cause appears and disappears on the first poll after it is fixed —
 * the worst-case latency a user can observe is exactly one interval.
 *
 * The interval comes from config('academic.risk.refresh_seconds'),
 * default 15s: a quarter of the 60-second ceiling the acceptance
 * criteria set, which leaves room for a slow request without ever
 * breaching it.
 *
 * Polling was chosen over WebSockets deliberately. Broadcasting would
 * mean running a socket server and publishing domain events from every
 * write path, for a screen a handful of coordinators keep open and whose
 * requirement is measured in tens of seconds. Two indexed queries every
 * 15 seconds is the proportionate answer; the domain service behind this
 * is push-ready if that ever stops being true, since nothing about
 * RiskEvaluator knows how it was invoked.
 */
class RiskBoardComponent extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewAny', RiskBoard::class);
    }

    public function render(EvaluateRiskBoardUseCase $useCase): View
    {
        // Re-authorized on every poll, not just on mount: a poll is a
        // full request, and a permission revoked while the tab sat open
        // must take effect on the next one.
        $this->authorize('viewAny', RiskBoard::class);

        $board = $useCase->handle();

        $view = view('academicrisk.riskboard.livewire.risk-board-component', [
            'levels' => $this->levels($board),
            'total' => $board->total(),
            'refreshSeconds' => $this->refreshSeconds(),
            'updatedAt' => now()->format('H:i:s'),
        ]);

        /** @disregard P1013 Livewire registers ->layout() as a runtime macro on Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Risks'),
            'subtitle' => __('Live risks detected in the academic offer'),
        ]);
    }

    /**
     * One entry per severity band, always all three and always in
     * urgency order — an empty "Alto" column that reads "sin riesgos" is
     * information; a column that silently vanishes is ambiguous.
     *
     * @return array<int, array{key: string, label: string, count: int, items: array<int, array<string, mixed>>}>
     */
    private function levels(RiskBoard $board): array
    {
        return array_map(
            fn (RiskLevel $level): array => [
                'key' => RiskLabelFormatter::levelVariant($level),
                'label' => RiskLabelFormatter::levelLabel($level),
                'count' => $board->countAt($level),
                'items' => array_map($this->toRow(...), $board->itemsAt($level)),
            ],
            RiskLevel::byUrgency(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(RiskItem $item): array
    {
        return [
            'key' => $item->fingerprint(),
            'title' => RiskLabelFormatter::title($item->type),
            'description' => RiskLabelFormatter::description($item),
            'subject' => RiskLabelFormatter::subjectLabel($item),
            'term' => $item->term,
            'link' => RiskLabelFormatter::link($item),
        ];
    }

    /**
     * Clamped rather than trusted: a misconfigured 0 would make Livewire
     * poll as fast as the browser allows, and anything above 60 would
     * quietly break the acceptance criteria this screen exists to meet.
     */
    private function refreshSeconds(): int
    {
        $configured = (int) config('academic.risk.refresh_seconds', 15);

        return max(5, min(60, $configured));
    }
}
