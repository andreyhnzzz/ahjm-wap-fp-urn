<?php

declare(strict_types=1);

namespace Src\Reporting\TeacherLoadReport\Presentation\Livewire;

use App\Livewire\Concerns\InteractsWithExports;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Src\Academic\Group\Presentation\Support\GroupLabelFormatter;
use Src\Reporting\TeacherLoadReport\Application\UseCases\GenerateTeacherLoadReportUseCase;
use Src\Reporting\TeacherLoadReport\Application\UseCases\ListReportTeachersUseCase;
use Src\Reporting\TeacherLoadReport\Application\UseCases\ListTeacherLoadTermsUseCase;
use Src\Reporting\TeacherLoadReport\Domain\Entities\TeacherLoadReport;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherLoadRow;
use Src\Reporting\TeacherLoadReport\Domain\ValueObjects\TeacherReference;
use Src\Reporting\TeacherLoadReport\Presentation\Support\WorkloadStatusFormatter;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Primary adapter for RE-02.
 *
 * The PDF gets its own template (`exports.teacher-load-pdf`) instead of
 * reusing the generic table one, for a single reason that turns out to
 * be load-bearing: RE-02 requires the "jornada indicativa" legend on
 * *every page*. A generic table template has no way to express that, and
 * a legend printed once at the bottom of page one is not what the
 * requirement asks for. The dedicated template pins the legend with
 * `position: fixed`, which Chromium's print engine repeats on each page.
 */
class TeacherLoadReportComponent extends Component
{
    use AuthorizesRequests;
    use InteractsWithExports;

    /**
     * Both filters live in the query string, so a specific teacher's
     * report for a specific term is a shareable link.
     */
    #[Url(as: 'teacher', history: true)]
    public string $teacherId = '';

    #[Url(as: 'term', history: true)]
    public string $term = '';

    public function mount(ListReportTeachersUseCase $teachersUseCase, ListTeacherLoadTermsUseCase $termsUseCase): void
    {
        $this->authorize('viewAny', TeacherLoadReport::class);

        $this->teacherId = $this->resolveInitialTeacher($teachersUseCase);
        $this->term = $this->resolveInitialTerm($termsUseCase);
    }

    public function exportPdf(
        PdfExporterInterface $exporter,
        GenerateTeacherLoadReportUseCase $useCase,
        ListReportTeachersUseCase $teachersUseCase,
        ListTeacherLoadTermsUseCase $termsUseCase,
    ): ?StreamedResponse {
        $this->authorize('exportPdf', TeacherLoadReport::class);

        $report = $this->currentReport($useCase, $teachersUseCase, $termsUseCase);

        if ($report === null) {
            $this->dispatch('toast', variant: 'danger', text: __('Select a teacher and a term first.'));

            return null;
        }

        $html = view('exports.teacher-load-pdf', $this->pdfData($report))->render();

        return $exporter->fromHtml($html, $this->downloadName($report, 'pdf'), 'letter');
    }

    public function exportExcel(
        ExcelExporterInterface $exporter,
        GenerateTeacherLoadReportUseCase $useCase,
        ListReportTeachersUseCase $teachersUseCase,
        ListTeacherLoadTermsUseCase $termsUseCase,
    ): ?StreamedResponse {
        $this->authorize('exportExcel', TeacherLoadReport::class);

        $report = $this->currentReport($useCase, $teachersUseCase, $termsUseCase);

        if ($report === null) {
            $this->dispatch('toast', variant: 'danger', text: __('Select a teacher and a term first.'));

            return null;
        }

        return $this->streamExcel(
            $this->exportHeaders(),
            $this->spreadsheetRows($report),
            $this->downloadName($report, 'xlsx'),
            $exporter,
        );
    }

    public function render(
        GenerateTeacherLoadReportUseCase $useCase,
        ListReportTeachersUseCase $teachersUseCase,
        ListTeacherLoadTermsUseCase $termsUseCase,
    ): View {
        $report = $this->currentReport($useCase, $teachersUseCase, $termsUseCase);

        $view = view('reporting.teacherloadreport.livewire.teacher-load-report-component', [
            'teacherOptions' => $this->teacherOptions($teachersUseCase),
            'terms' => $termsUseCase->handle(),
            'headers' => $this->exportHeaders(),
            'rows' => $report === null ? [] : array_map($this->toRow(...), $report->rows()),
            'summary' => $report === null ? null : $this->summary($report),
            'legend' => $this->legend(),
        ]);

        /** @disregard P1013 Livewire registers ->layout() as a runtime macro on Illuminate\View\View */
        return $view->layout('components.layouts.dashboard', [
            'title' => __('Teacher load report'),
            'subtitle' => __('Accumulated workload of a teacher against their reference workload'),
        ]);
    }

    /**
     * Builds the report for the current selection, or null when the
     * selection is incomplete or points at something that does not
     * exist. Both filters are public Livewire properties and therefore
     * client-writable, so each one is checked against the values the
     * screen actually offers rather than trusted.
     */
    private function currentReport(
        GenerateTeacherLoadReportUseCase $useCase,
        ListReportTeachersUseCase $teachersUseCase,
        ListTeacherLoadTermsUseCase $termsUseCase,
    ): ?TeacherLoadReport {
        if ($this->teacherId === '' || $this->term === '') {
            return null;
        }

        $knownIds = array_map(
            static fn (TeacherReference $teacher): string => (string) $teacher->id,
            $teachersUseCase->handle(),
        );

        if (! in_array($this->teacherId, $knownIds, true)) {
            return null;
        }

        if (! in_array($this->term, $termsUseCase->handle(), true)) {
            return null;
        }

        return $useCase->handle((int) $this->teacherId, $this->term);
    }

    /**
     * @return array<int, array{key: string, label: string, format?: callable}>
     */
    private function exportHeaders(): array
    {
        return [
            ['key' => 'groupCode', 'label' => __('Group code')],
            ['key' => 'courseCode', 'label' => __('Course code')],
            ['key' => 'classroom', 'label' => __('Classroom')],
            ['key' => 'modality', 'label' => __('Modality')],
            ['key' => 'status', 'label' => __('Status')],
            ['key' => 'estimatedEnrollment', 'label' => __('Estimated enrollment')],
            ['key' => 'assignedWorkload', 'label' => __('Assigned workload')],
        ];
    }

    /**
     * @return array<string, scalar>
     */
    private function toRow(TeacherLoadRow $row): array
    {
        return [
            'groupCode' => $row->groupCode,
            'courseCode' => $row->courseCode,
            'classroom' => $row->classroomName ?? __('Unassigned'),
            'hasClassroom' => $row->classroomName !== null,
            'modality' => GroupLabelFormatter::modality($row->modality),
            'status' => GroupLabelFormatter::status($row->status),
            'statusVariant' => GroupLabelFormatter::statusVariant($row->status),
            'estimatedEnrollment' => $row->estimatedEnrollment,
            'assignedWorkload' => number_format($row->assignedWorkload, 2, '.', ''),
        ];
    }

    /**
     * The spreadsheet carries the same legend the PDF prints on every
     * page, as a closing note row. A spreadsheet has no pages to repeat
     * it on, and dropping it entirely would let the figures travel
     * without the caveat that makes them safe to read.
     *
     * @return array<int, array<string, scalar>>
     */
    private function spreadsheetRows(TeacherLoadReport $report): array
    {
        $rows = array_map($this->toRow(...), $report->rows());

        $rows[] = [
            'groupCode' => $this->totalLabel(),
            'assignedWorkload' => number_format($report->assignedWorkload(), 2, '.', ''),
        ];

        $rows[] = ['groupCode' => $this->legend()];

        return $rows;
    }

    /**
     * Wrapped in a typed accessor for the same reason legend() is:
     * `__()` is declared to return string|array (a translation key can
     * resolve to a whole array), and a spreadsheet cell can only be a
     * scalar. Declaring the narrower type here is what keeps
     * spreadsheetRows() honest about what it hands the writer.
     */
    private function totalLabel(): string
    {
        return __('Total');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(TeacherLoadReport $report): array
    {
        return [
            'teacherName' => $report->teacher()->name,
            'identityCard' => $report->teacher()->identityCard,
            'term' => $report->term(),
            'groups' => $report->groupCount(),
            'students' => $report->totalEstimatedEnrollment(),
            'assigned' => number_format($report->assignedWorkload(), 2),
            'reference' => number_format($report->referenceWorkload(), 2),
            'utilization' => number_format($report->utilization() * 100, 1),
            'statusLabel' => WorkloadStatusFormatter::label($report->status()),
            'statusVariant' => WorkloadStatusFormatter::variant($report->status()),
            'underLoadPercent' => number_format($report->underLoadRatio() * 100, 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pdfData(TeacherLoadReport $report): array
    {
        return [
            'headers' => $this->exportHeaders(),
            'rows' => array_map($this->toRow(...), $report->rows()),
            'summary' => $this->summary($report),
            'palette' => WorkloadStatusFormatter::pdfPalette($report->status()),
            'legend' => $this->legend(),
            'generatedAt' => $report->generatedAt()->format('d/m/Y H:i'),
        ];
    }

    /**
     * The exact wording RE-02 mandates on every page of the report.
     */
    private function legend(): string
    {
        return __('Indicative workload — subject to HR confirmation');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function teacherOptions(ListReportTeachersUseCase $useCase): array
    {
        return array_map(
            static fn (TeacherReference $teacher): array => [
                'value' => (string) $teacher->id,
                'label' => $teacher->name,
            ],
            $useCase->handle(),
        );
    }

    private function resolveInitialTeacher(ListReportTeachersUseCase $useCase): string
    {
        $teachers = $useCase->handle();

        foreach ($teachers as $teacher) {
            if ((string) $teacher->id === $this->teacherId) {
                return $this->teacherId;
            }
        }

        return isset($teachers[0]) ? (string) $teachers[0]->id : '';
    }

    private function resolveInitialTerm(ListTeacherLoadTermsUseCase $useCase): string
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

    private function downloadName(TeacherLoadReport $report, string $extension): string
    {
        return Str::slug(__('Teacher load').' '.$report->teacher()->name.' '.$report->term()).'.'.$extension;
    }
}
