<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Presentation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Academic\Group\Presentation\Support\GroupLabelFormatter;
use Src\Reporting\OfferReport\Application\UseCases\FindStoredOfferReportUseCase;
use Src\Reporting\OfferReport\Application\UseCases\GenerateOfferReportUseCase;
use Src\Reporting\OfferReport\Application\UseCases\ListOfferTermsUseCase;
use Src\Reporting\OfferReport\Application\UseCases\StoreOfferReportUseCase;
use Src\Reporting\OfferReport\Domain\Entities\OfferReport;
use Src\Reporting\OfferReport\Domain\ValueObjects\OfferRow;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Primary adapter for RE-01.
 *
 * The requirement is specific about the shape of this screen: pick a
 * term, ask for the report, get an .xlsx **and** a .pdf carrying the
 * same information, and have the elapsed time recorded in the log. So
 * generate() produces both artifacts in one action and times the whole
 * thing — not just the query, not just the render, but everything
 * between the click and the files being downloadable, which is what
 * "desde la solicitud hasta la disponibilidad de descarga" means.
 *
 * Timing lives here rather than inside a use case on purpose: this class
 * *is* the request boundary, so it is the only place that can measure
 * the interval the criteria describe.
 */
class OfferReportComponent extends Component
{
    use AuthorizesRequests;

    /**
     * In the query string so a generated report is a shareable link — a
     * coordinator can send "the 2026-II offer" to a colleague and have
     * them land on the same screen.
     */
    #[Url(as: 'term', history: true)]
    public string $term = '';

    /**
     * Seconds the last generation took, or null when this screen has not
     * generated anything yet. Displayed next to the download buttons so
     * the 30-second criterion is visible in the UI, not only in the log.
     */
    public ?float $lastGenerationSeconds = null;

    public function mount(ListOfferTermsUseCase $termsUseCase): void
    {
        $this->authorize('viewAny', OfferReport::class);

        $this->term = $this->resolveInitialTerm($termsUseCase);
    }

    /**
     * Switching terms invalidates the timing shown on screen — it
     * described a different report.
     */
    public function updatedTerm(): void
    {
        $this->lastGenerationSeconds = null;
    }

    public function generate(
        GenerateOfferReportUseCase $generateUseCase,
        StoreOfferReportUseCase $storeUseCase,
        ListOfferTermsUseCase $termsUseCase,
    ): void {
        $this->authorize('viewAny', OfferReport::class);

        if (! $this->isKnownTerm($termsUseCase)) {
            $this->dispatch('toast', variant: 'danger', text: __('Select a term with groups loaded.'));

            return;
        }

        $startedAt = microtime(true);

        try {
            $report = $generateUseCase->handle($this->term);

            $stored = $storeUseCase->handle(
                term: $this->term,
                title: __('Academic offer :term', ['term' => $this->term]),
                headers: $this->exportHeaders(),
                rows: $this->labelledRows($report),
            );
        } catch (Throwable $exception) {
            // The PDF half runs headless Chromium through Puppeteer, which
            // is the one part of this pipeline that can fail for reasons
            // outside the application (missing browser binary, a killed
            // child process). Surfacing it as a toast plus a log entry
            // beats a raw 500 that tells the user nothing.
            Log::error('[RE-01] Offer report generation failed', [
                'term' => $this->term,
                'error' => $exception->getMessage(),
            ]);

            $this->dispatch('toast', variant: 'danger', text: __('The report could not be generated. Check the log for details.'));

            return;
        }

        $elapsedSeconds = round(microtime(true) - $startedAt, 3);
        $this->lastGenerationSeconds = $elapsedSeconds;

        $this->logGeneration($report, $elapsedSeconds, $stored->excelPath, $stored->pdfPath);

        $this->dispatch('toast', variant: 'success', text: __('Report generated in :seconds s.', [
            'seconds' => number_format($elapsedSeconds, 2),
        ]));
    }

    public function downloadExcel(FindStoredOfferReportUseCase $useCase): ?BinaryFileResponse
    {
        $this->authorize('exportExcel', OfferReport::class);

        $stored = $useCase->handle($this->term);

        if ($stored === null) {
            $this->dispatch('toast', variant: 'danger', text: __('Generate the report before downloading it.'));

            return null;
        }

        return response()->download($stored->excelPath, $this->downloadName('xlsx'));
    }

    public function downloadPdf(FindStoredOfferReportUseCase $useCase): ?BinaryFileResponse
    {
        $this->authorize('exportPdf', OfferReport::class);

        $stored = $useCase->handle($this->term);

        if ($stored === null) {
            $this->dispatch('toast', variant: 'danger', text: __('Generate the report before downloading it.'));

            return null;
        }

        return response()->download($stored->pdfPath, $this->downloadName('pdf'));
    }

    public function render(
        GenerateOfferReportUseCase $generateUseCase,
        ListOfferTermsUseCase $termsUseCase,
        FindStoredOfferReportUseCase $findUseCase,
    ): View {
        $terms = $termsUseCase->handle();
        $report = $this->term === '' ? null : $generateUseCase->handle($this->term);
        $stored = $this->term === '' ? null : $findUseCase->handle($this->term);

        $view = view('reporting.offerreport.livewire.offer-report-component', [
            'terms' => $terms,
            'headers' => $this->exportHeaders(),
            'rows' => $report === null ? [] : array_map($this->toRow(...), $report->rows()),
            'summary' => $report === null ? null : $this->summary($report),
            'hasArtifacts' => $stored !== null,
            'generatedAtLabel' => $stored?->generatedAt->format('d/m/Y H:i'),
            'lastGenerationSeconds' => $this->lastGenerationSeconds,
            'maxSeconds' => $this->maxGenerationSeconds(),
        ]);

        /** @disregard P1013 Livewire registers ->layout() as a runtime macro on Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Academic offer report'),
            'subtitle' => __('Exportable offer of a term in Excel and PDF'),
        ]);
    }

    /**
     * The columns RE-01 enumerates, in its order. One definition drives
     * the on-screen table, the spreadsheet and the PDF, so the three can
     * never disagree about what a column is called or where it sits.
     *
     * @return array<int, array{key: string, label: string}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'groupCode', 'label' => __('Group code')],
            ['key' => 'courseCode', 'label' => __('Course code')],
            ['key' => 'teacher', 'label' => __('Teacher')],
            ['key' => 'classroom', 'label' => __('Classroom')],
            ['key' => 'modality', 'label' => __('Modality')],
            ['key' => 'status', 'label' => __('Status')],
            ['key' => 'estimatedEnrollment', 'label' => __('Estimated enrollment')],
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function toRow(OfferRow $row): array
    {
        return [
            'groupCode' => $row->groupCode,
            'courseCode' => $row->courseCode,
            'teacher' => $row->teacherName ?? __('Unassigned'),
            'hasTeacher' => $row->hasTeacher(),
            'classroom' => $row->classroomName ?? __('Unassigned'),
            'hasClassroom' => $row->hasClassroom(),
            'modality' => GroupLabelFormatter::modality($row->modality),
            'status' => GroupLabelFormatter::status($row->status),
            'statusVariant' => GroupLabelFormatter::statusVariant($row->status),
            'estimatedEnrollment' => $row->estimatedEnrollment,
        ];
    }

    /**
     * Re-keys each row by its column label, which is the shape both the
     * spreadsheet writer and the PDF template consume. Extra keys the
     * screen uses for styling (hasTeacher, statusVariant) are dropped
     * here, since they are not columns of the report.
     *
     * @return array<int, array<string, scalar|null>>
     */
    private function labelledRows(OfferReport $report): array
    {
        $headers = $this->exportHeaders();

        return array_map(function (OfferRow $row) use ($headers): array {
            $values = $this->toRow($row);
            $labelled = [];

            foreach ($headers as $header) {
                $labelled[$header['label']] = $values[$header['key']] ?? '';
            }

            return $labelled;
        }, $report->rows());
    }

    /**
     * @return array<string, int>
     */
    private function summary(OfferReport $report): array
    {
        return [
            'groups' => $report->groupCount(),
            'withoutTeacher' => $report->groupsWithoutTeacher(),
            'withoutClassroom' => $report->groupsWithoutClassroom(),
            'students' => $report->totalEstimatedEnrollment(),
        ];
    }

    /**
     * The log entry RE-01 requires to be verifiable.
     *
     * Escalated to `warning` past the configured ceiling rather than
     * throwing: the coordinator still gets their files, and the breach
     * still leaves a trace someone can grep for. A report that refuses
     * to be delivered because it was slow would help nobody.
     */
    private function logGeneration(OfferReport $report, float $elapsedSeconds, string $excelPath, string $pdfPath): void
    {
        $context = [
            'term' => $report->term(),
            'groups' => $report->groupCount(),
            'elapsed_seconds' => $elapsedSeconds,
            'limit_seconds' => $this->maxGenerationSeconds(),
            'excel' => $excelPath,
            'pdf' => $pdfPath,
        ];

        if ($elapsedSeconds > $this->maxGenerationSeconds()) {
            Log::warning('[RE-01] Offer report exceeded its generation budget', $context);

            return;
        }

        Log::info('[RE-01] Offer report generated', $context);
    }

    private function maxGenerationSeconds(): int
    {
        return (int) config('academic.offer_report.max_generation_seconds', 30);
    }

    /**
     * Falls back to the most recent term with data when no default is
     * configured, so a fresh install never opens on an empty selector.
     */
    private function resolveInitialTerm(ListOfferTermsUseCase $useCase): string
    {
        $terms = $useCase->handle();

        if ($this->term !== '' && in_array($this->term, $terms, true)) {
            return $this->term;
        }

        $configured = (string) config('academic.default_term', '');

        if ($configured !== '' && in_array($configured, $terms, true)) {
            return $configured;
        }

        return $terms[0] ?? '';
    }

    /**
     * Never trust `$this->term` as a filesystem input: it is a public
     * Livewire property and therefore client-writable. Checking it
     * against the terms that actually exist is what keeps a crafted
     * value from reaching the archive at all.
     */
    private function isKnownTerm(ListOfferTermsUseCase $useCase): bool
    {
        return $this->term !== '' && in_array($this->term, $useCase->handle(), true);
    }

    private function downloadName(string $extension): string
    {
        return Str::slug(__('Academic offer').' '.$this->term).'.'.$extension;
    }
}
