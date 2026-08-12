<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Src\Shared\Export\Contracts\ExcelExporterInterface;
use Src\Shared\Export\Contracts\PdfExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns the export ports (Src\Shared\Export\Contracts\*) into two
 * ready-to-call helpers for any CRUD Livewire component.
 *
 * Each component supplies its own columns as `{key, label, format?}`
 * pairs — the same shape <x-ui.data-table> takes for its headers prop,
 * so on-screen and exported columns share one source of truth.
 *
 * Authorization is deliberately not handled here: call
 * $this->authorize(...) in your own exportExcel()/exportPdf() first,
 * like every other action in this app.
 *
 * Both helpers accept any `iterable`, so the pipeline stays
 * constant-memory end to end if a component ever feeds it a DB cursor
 * instead of a fully-loaded array.
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
     * Projects each row down to the exportable columns, keyed by each
     * header's label so the file's header row reads "Nombre", not
     * "name", applying that column's `format` callback when given.
     * A generator on purpose: it must stay lazy for the constant-memory
     * guarantee above to hold.
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
