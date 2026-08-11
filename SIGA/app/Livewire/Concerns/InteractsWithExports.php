<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns the export ports (Src\Shared\Export\Contracts\*) into two
 * ready-to-call helpers any CRUD's Livewire component can use, without
 * re-writing the "map rows -> call exporter" plumbing each time.
 *
 * What stays generic here (write once): projecting rows down to
 * exportable columns, rendering the PDF table shell, calling the ports.
 *
 * What each component still defines (the "adapts to each table" part):
 * its own column set, in `{key, label}` pairs — the same shape already
 * used for <x-ui.data-table>'s headers prop, so export columns and
 * on-screen columns share one source of truth — plus an optional
 * `format` callback per column when the raw stored value isn't what
 * should land in the file (e.g. Role's `protected` boolean becoming
 * "Sistema"/"Personalizado" instead of "1"/"").
 *
 * Authorization is intentionally NOT handled here — call
 * $this->authorize(...) in your own exportExcel()/exportPdf() before
 * calling these helpers, exactly like every other action in this app.
 *
 * Usage (see RoleComponent for the full example):
 *
 *   public function exportExcel(ExcelExporterInterface $exporter, ListRolesUseCase $useCase): StreamedResponse
 *   {
 *       $this->authorize('exportExcel', Role::class);
 *
 *       return $this->streamExcel($this->exportHeaders(), $this->freshRows($useCase), 'roles.xlsx', $exporter);
 *   }
 *
 * Scaling this to a genuinely large table: streamExcel()/streamPdf()
 * accept any `iterable`, on purpose — the whole export pipeline (this
 * trait, both ports, both adapters) is already constant-memory-capable
 * for a table with millions of rows, with zero changes needed here.
 * What has to change is what YOUR component feeds it: instead of a
 * method like Role/Permission's `freshRows()` that calls the
 * repository's `all()` (which does `->get()` — loads every row into a
 * PHP array up front, correct for a small catalog, wrong for a huge
 * one), a large-table repository should expose a method backed by
 * Eloquent's `cursor()`:
 *
 *   public function allForExport(): iterable
 *   {
 *       foreach (DocenteModel::query()->cursor() as $model) {
 *           yield $this->toDomain($model);
 *       }
 *   }
 *
 * One real trade-off to know about before reaching for cursor(): it
 * does not play well with eager-loaded relations (`->with(...)`) —
 * Eloquent can't build the relation's single `WHERE IN (...)` query
 * without first knowing every parent id, which conflicts with fetching
 * one row at a time. That's exactly why Role's own repository keeps
 * using `->with('permissions')->get()` instead of `cursor()` — it's a
 * handful of rows, and switching would have reintroduced the N+1 query
 * problem this app already fixed once. Reach for `cursor()` when a
 * table is genuinely large AND doesn't need an eager-loaded relation
 * for its export columns; keep `get()` when it does.
 */
trait InteractsWithExports
{
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
     * Projects each row down to just the exportable columns, keyed by
     * each header's *label* (so the spreadsheet/PDF header row reads
     * "Nombre", not "name"), applying that column's `format` callback
     * when one is given. A generator on purpose — this must stay lazy
     * so a caller that supplies a genuinely lazy $rows source (a DB
     * cursor, for a future large table) keeps the exporter's constant-
     * memory guarantee end to end. For Role/Permission, `freshRows()`
     * already loads the full (small) catalog into memory beforehand —
     * that's fine for a catalog this size, just not a pattern to copy
     * blindly for a table with tens of thousands of rows.
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
