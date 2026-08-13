<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\ReportExportStatus;
use App\Jobs\GenerateReportExportJob;
use App\Models\ReportExport;
use Illuminate\Support\Facades\Auth;
use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns the export ports (Src\Shared\Export\Contracts\*) into ready-to-call
 * helpers for any CRUD Livewire component, in two flavours:
 *
 *  - streamExcel()/streamPdf(): synchronous, blocks the request until
 *    Chrome/OpenSpout finishes. Fine for a small, bounded report (one
 *    teacher's course load — see TeacherLoadReportComponent).
 *  - queueExcelExport()/queuePdfExport(): dispatches GenerateReportExportJob
 *    and returns immediately. Use this for anything backed by a table
 *    that can grow into the hundreds/thousands of rows (every
 *    <x-ui.data-table> screen) — rendering 1500+ rows can take up to
 *    ~20s (see the mPDF/Dompdf/Spatie spike), long enough to tie up a
 *    PHP-FPM worker and make the app feel unresponsive under concurrent
 *    exports. pollExportStatus() picks up the result.
 *
 * Each component supplies its own columns as `{key, label, format?}`
 * pairs — the same shape <x-ui.data-table> takes for its headers prop,
 * so on-screen and exported columns share one source of truth.
 *
 * Authorization is deliberately not handled here: call
 * $this->authorize(...) in your own exportExcel()/exportPdf() first,
 * like every other action in this app.
 */
trait InteractsWithExports
{
    /**
     * The export this component is currently waiting on, if any. Public
     * so it survives across the poll requests below — Livewire drops
     * anything not on the component between requests.
     */
    public ?int $activeExportId = null;

    /**
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     */
    protected function streamExcel(array $headers, iterable $rows, string $filename, ExcelExporterInterface $exporter): StreamedResponse
    {
        return $exporter->streamDownload($this->mapRowsForExport($headers, $rows), $filename);
    }

    /**
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     * @param  string  $paperSize  Passed straight through to PdfExporterInterface — see
     *                             that contract for why it's a parameter here rather than a fixed default.
     */
    protected function streamPdf(string $title, array $headers, iterable $rows, string $filename, PdfExporterInterface $exporter, string $paperSize = 'a4'): StreamedResponse
    {
        $html = view('exports.table-pdf', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $this->mapRowsForExport($headers, $rows),
        ])->render();

        return $exporter->fromHtml($html, $filename, $paperSize);
    }

    /**
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     */
    protected function queueExcelExport(string $title, array $headers, iterable $rows, string $filename): void
    {
        $this->queueExport('excel', $title, $headers, $rows, $filename);
    }

    /**
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     */
    protected function queuePdfExport(string $title, array $headers, iterable $rows, string $filename, string $paperSize = 'a4'): void
    {
        $this->queueExport('pdf', $title, $headers, $rows, $filename, $paperSize);
    }

    /**
     * Called by wire:poll while $activeExportId is set (see the Blade
     * view). No-ops once nothing is in flight, so components can wire
     * it up unconditionally.
     */
    public function pollExportStatus(): void
    {
        if ($this->activeExportId === null) {
            return;
        }

        $export = ReportExport::query()->find($this->activeExportId);

        if ($export === null || ! $export->status->isFinished()) {
            return;
        }

        $this->activeExportId = null;

        if ($export->status === ReportExportStatus::Ready) {
            $this->dispatch('export-ready', url: route('report-exports.download', $export), filename: $export->filename);
            $this->dispatch('toast', variant: 'success', text: __('Your file is ready.'));

            return;
        }

        $this->dispatch('toast', variant: 'danger', text: __('The file could not be generated. Please try again.'));
    }

    /**
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     */
    private function queueExport(string $format, string $title, array $headers, iterable $rows, string $filename, string $paperSize = 'a4'): void
    {
        $export = ReportExport::query()->create([
            'user_id' => Auth::id(),
            'format' => $format,
            'title' => $title,
            'filename' => $filename,
            'status' => ReportExportStatus::Pending,
            'disk' => 'local',
        ]);

        GenerateReportExportJob::dispatch(
            $export->id,
            $title,
            array_map(static fn (array $header): array => ['label' => $header['label']], $headers),
            iterator_to_array($this->mapRowsForExport($headers, $rows), false),
            $format,
            $paperSize,
        );

        $this->activeExportId = $export->id;
        $this->dispatch('toast', variant: 'info', text: __('Generating your file — this will only take a moment.'));
    }

    /**
     * Projects each row down to the exportable columns, keyed by each
     * header's label so the file's header row reads "Nombre", not
     * "name", applying that column's `format` callback when given.
     * A generator on purpose: it must stay lazy for the constant-memory
     * guarantee above to hold for the synchronous stream*() path.
     *
     * @param  array<int, array{key: string, label: string, format?: callable}>  $headers
     * @param  iterable<array<string, mixed>>  $rows
     * @return \Generator<int, array<string, mixed>>
     */
    private function mapRowsForExport(array $headers, iterable $rows): \Generator
    {
        foreach ($rows as $row) {
            $mapped = [];

            foreach ($headers as $header) {
                $value = $row[$header['key']] ?? '';
                $mapped[$header['label']] = isset($header['format']) ? ($header['format'])($value) : $value;
            }

            yield $mapped;
        }
    }
}
