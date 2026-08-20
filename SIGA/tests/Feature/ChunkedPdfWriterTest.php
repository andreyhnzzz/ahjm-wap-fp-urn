<?php

declare(strict_types=1);

namespace Tests\Feature;

use ReflectionMethod;
use Src\Shared\Export\Contracts\PdfFileWriterInterface;
use Src\Shared\Export\Infrastructure\ChunkedChromePdfWriter;
use Tests\TestCase;

/**
 * Pins the decisions ChunkedChromePdfWriter makes before Chromium is ever
 * involved, so they can be verified without a browser in CI: when a report
 * stays a single document, and how the rows are split when it does not.
 *
 * The rendering itself is deliberately not exercised here against a real
 * browser — that needs the sidecar running and belongs to a manual check
 * (dispatch a real GenerateReportExportJob with 45.000 rows and read the
 * `pdf.chunked` log line it writes), not a unit suite that has to pass
 * without one.
 */
class ChunkedPdfWriterTest extends TestCase
{
    public function test_a_small_report_stays_one_document_and_keeps_the_cover(): void
    {
        $captured = null;

        $single = new class($captured) implements PdfFileWriterInterface
        {
            public function __construct(public mixed &$html) {}

            public function write(string $html, string $absolutePath, string $paperSize = 'a4'): void
            {
                $this->html = $html;
                file_put_contents($absolutePath, 'pdf');
            }
        };

        $writer = new ChunkedChromePdfWriter($single);
        $path = tempnam(sys_get_temp_dir(), 'pdf');

        $writer->write('Grupos', [['label' => 'Código']],
            array_fill(0, 50, ['Código' => 'G-1']), $path);

        // The cover block only renders when it is not a continuation, so
        // its presence is what proves the single-document path was taken.
        $this->assertStringContainsString('hero', (string) $single->html);
        $this->assertStringContainsString('G-1', (string) $single->html);

        @unlink($path);
    }

    /**
     * Renders go out in waves of PARALLEL_REQUESTS and each wave waits for
     * its slowest member, so a partly-filled last wave wastes those slots.
     * 45.000 rows at a flat 2.000 would give 23 chunks — two full waves of
     * ten and a third of three. The size is adjusted so the chunk count is
     * a whole multiple of the pool instead.
     */
    public function test_chunk_size_is_adjusted_so_waves_are_full(): void
    {
        $chunkSize = new ReflectionMethod(ChunkedChromePdfWriter::class, 'chunkSize');
        $writer = new ChunkedChromePdfWriter(
            $this->app->make(PdfFileWriterInterface::class));

        $parallel = new \ReflectionClassConstant(ChunkedChromePdfWriter::class, 'PARALLEL_REQUESTS');
        $pool = (int) $parallel->getValue();

        foreach ([45000, 30000, 12345] as $rows) {
            $size = (int) $chunkSize->invoke($writer, $rows);
            $chunks = (int) ceil($rows / $size);

            $this->assertSame(0, $chunks % $pool,
                "{$rows} rows split into {$chunks} chunks, which does not fill whole waves of {$pool}");
        }
    }

    public function test_row_numbering_continues_across_chunks(): void
    {
        // The offset is what keeps the merged document numbered 1..n: each
        // chunk is its own Blade render, so $loop->iteration restarts at 1
        // in every one of them.
        $html = new ReflectionMethod(ChunkedChromePdfWriter::class, 'html');
        $writer = new ChunkedChromePdfWriter(
            $this->app->make(PdfFileWriterInterface::class));

        $rows = array_fill(0, 3, ['Código' => 'G-1']);
        $second = (string) $html->invoke($writer, 'Grupos', [['label' => 'Código']], $rows, true, 1500);

        $this->assertStringContainsString('>1501<', $second);
        $this->assertStringContainsString('>1503<', $second);
        // A continuation must not repeat the cover block either.
        $this->assertStringNotContainsString('<section class="hero">', $second);

        // ...and the first chunk must still start at 1 and carry the cover.
        $first = (string) $html->invoke($writer, 'Grupos', [['label' => 'Código']], $rows, false, 0);
        $this->assertStringContainsString('<td class="row-index">1</td>', $first);
        $this->assertStringContainsString('<section class="hero">', $first);
    }

    /**
     * The institutional banner (<header class="report-header">) used to
     * sit outside the ` @unless($continuation)` guard, so only the hero
     * title was suppressed on later chunks — the banner itself reappeared
     * on every one of them. Invisible in a unit test that only checks the
     * hero; caught by rendering a real 45.000-row PDF and counting how
     * many times "UNIVERSIDAD T..." shows up (10, once per chunk, instead
     * of 1). See D-19 / D-16 in the diary.
     */
    public function test_a_continuation_chunk_does_not_repeat_the_institutional_header(): void
    {
        $html = new ReflectionMethod(ChunkedChromePdfWriter::class, 'html');
        $writer = new ChunkedChromePdfWriter(
            $this->app->make(PdfFileWriterInterface::class));

        $rows = array_fill(0, 3, ['Código' => 'G-1']);
        $second = (string) $html->invoke($writer, 'Grupos', [['label' => 'Código']], $rows, true, 1500);
        $first = (string) $html->invoke($writer, 'Grupos', [['label' => 'Código']], $rows, false, 0);

        // The bare class name also appears in every render's <style> block
        // (the CSS rule), so the assertion targets the actual tag.
        $this->assertStringNotContainsString('<header class="report-header">', $second);
        $this->assertStringContainsString('<header class="report-header">', $first);
    }
}
