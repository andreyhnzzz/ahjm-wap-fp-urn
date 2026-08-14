<?php

namespace Tests\Feature;

use App\Enums\ReportExportStatus;
use App\Jobs\GenerateReportExportJob;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Src\Shared\Export\Contracts\ExcelFileWriterInterface;
use Src\Shared\Export\Contracts\PdfFileWriterInterface;
use Src\Shared\Export\Infrastructure\BrowsershotConfiguration;
use Tests\TestCase;

/**
 * Pins the two properties the PDF export optimisation depends on, both
 * of which are invisible in the output and would therefore rot silently:
 *
 *  - Chrome is asked NOT to emit the PDF/UA structure tree. That single
 *    option is worth 8.9 MB -> 0.8 MB and 2.66s -> 1.96s on a 2500-row
 *    export; delete the line and every export still produces a correct,
 *    much slower, much fatter PDF, and nothing fails.
 *  - A report past the measured 15,000-row cliff is refused immediately
 *    instead of spending two minutes failing in Chrome twice.
 *
 * Each test is written so that it knows how to go red: the tagging test
 * asserts the exact value rather than "an option is set", and the
 * ceiling tests assert both that too many rows are rejected AND that a
 * report at the limit still renders — a guard that blocked everything
 * would pass the first assertion alone.
 */
class PdfExportLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_browsershot_is_configured_to_skip_the_tagged_pdf_structure_tree(): void
    {
        config()->set('exports.pdf.tagged', false);

        $browsershot = Browsershot::html('<p>x</p>');
        BrowsershotConfiguration::apply($browsershot);

        // createPdfCommand() is the payload browser.cjs forwards straight
        // into puppeteer's page.pdf(), so this asserts what Chrome is
        // actually told — not merely that a setter was called.
        $command = $browsershot->createPdfCommand();

        $this->assertArrayHasKey('tagged', $command['options']);
        $this->assertFalse($command['options']['tagged']);
    }

    public function test_the_tagged_structure_tree_can_be_turned_back_on_by_configuration(): void
    {
        config()->set('exports.pdf.tagged', true);

        $browsershot = Browsershot::html('<p>x</p>');
        BrowsershotConfiguration::apply($browsershot);

        $this->assertTrue($browsershot->createPdfCommand()['options']['tagged']);
    }

    public function test_a_pdf_past_the_row_ceiling_is_refused_without_reaching_chrome(): void
    {
        config()->set('exports.pdf.max_rows', 3);

        $writer = $this->spyingPdfWriter();
        $export = $this->pendingExport('pdf');

        // Captured rather than wrapped in fail()/catch: PHPUnit's own
        // AssertionFailedError extends RuntimeException, so a catch block
        // for RuntimeException swallows the fail() that is supposed to
        // report the guard's absence — which it did, and the test still
        // went red, but with a message about the wrong thing.
        $thrown = null;

        try {
            $this->jobFor($export, 'pdf', rows: 4)->handle($writer, $this->app->make(ExcelFileWriterInterface::class));
        } catch (RuntimeException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'a report past the ceiling should have been refused');
        $this->assertStringContainsString('4 rows', $thrown->getMessage());
        $this->assertStringContainsString('limited to 3', $thrown->getMessage());
        $this->assertFalse($writer->called, 'the renderer was invoked for a report known to be unrenderable');
    }

    public function test_a_pdf_exactly_at_the_row_ceiling_still_renders(): void
    {
        config()->set('exports.pdf.max_rows', 3);

        $writer = $this->spyingPdfWriter();
        $export = $this->pendingExport('pdf');

        $this->jobFor($export, 'pdf', rows: 3)->handle($writer, $this->app->make(ExcelFileWriterInterface::class));

        $this->assertTrue($writer->called);
        $this->assertSame(ReportExportStatus::Ready, $export->refresh()->status);
    }

    public function test_the_ceiling_does_not_apply_to_excel_which_streams_row_by_row(): void
    {
        config()->set('exports.pdf.max_rows', 3);
        Storage::fake('local');

        $export = $this->pendingExport('excel');

        $this->jobFor($export, 'excel', rows: 50)->handle(
            $this->spyingPdfWriter(),
            $this->app->make(ExcelFileWriterInterface::class),
        );

        $this->assertSame(ReportExportStatus::Ready, $export->refresh()->status);
    }

    private function spyingPdfWriter(): PdfFileWriterInterface
    {
        return new class implements PdfFileWriterInterface
        {
            public bool $called = false;

            public function write(string $html, string $absolutePath, string $paperSize = 'a4'): void
            {
                $this->called = true;
                file_put_contents($absolutePath, '%PDF-1.4 fake');
            }
        };
    }

    private function pendingExport(string $format): ReportExport
    {
        Storage::fake('local');

        return ReportExport::factory()->create([
            'user_id' => User::factory()->create()->id,
            'format' => $format,
            'status' => ReportExportStatus::Pending,
            'disk' => 'local',
        ]);
    }

    private function jobFor(ReportExport $export, string $format, int $rows): GenerateReportExportJob
    {
        return new GenerateReportExportJob(
            $export->id,
            'Groups',
            [['label' => 'Name']],
            array_fill(0, $rows, ['Name' => 'Grupo']),
            $format,
        );
    }
}
