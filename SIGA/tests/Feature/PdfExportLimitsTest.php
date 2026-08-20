<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Browsershot\Browsershot;
use Src\Shared\Export\Infrastructure\BrowsershotConfiguration;
use Tests\TestCase;

/**
 * Pins that Chrome is asked NOT to emit the PDF/UA structure tree, which
 * is invisible in the output and would therefore rot silently: delete the
 * `setOption('tagged', …)` line and every export still produces a
 * correct, much slower, much fatter PDF, and nothing fails.
 *
 * This file used to also pin a hard row ceiling (a PDF past ~15,000 rows
 * was refused outright). ChunkedChromePdfWriter replaced that guard by
 * splitting a large export into several documents and stitching them back
 * together — see its own tests (ChunkedPdfWriterTest) for what protects
 * that path now. Refusing a report was the honest answer only while
 * nothing existed that could actually render it.
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
}
