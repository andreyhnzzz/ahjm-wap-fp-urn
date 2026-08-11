<?php

declare(strict_types=1);

namespace Src\Reporting\OfferReport\Infrastructure\Persistence\Archives;

use DateTimeImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Src\Reporting\OfferReport\Domain\Contracts\OfferReportArchiveInterface;
use Src\Reporting\OfferReport\Domain\ValueObjects\StoredOfferReport;
use Src\Shared\Export\Contracts\ExcelFileWriterInterface;
use Src\Shared\Export\Contracts\PdfFileWriterInterface;

/**
 * Writes the .xlsx and the .pdf of a term to a private disk and finds
 * them again.
 *
 * Both formats are produced from the same in-memory rows in the same
 * call, which is what makes RE-01's "misma información en ambos
 * archivos" true by construction rather than by convention — there is no
 * second query between them that could see different data.
 *
 * File names are derived from the term, so re-generating a term
 * overwrites its own pair instead of accumulating one more copy per
 * click. That also means a download can never be served a path the
 * browser supplied: the caller asks for a term, and the term determines
 * the path.
 *
 * The PDF reuses `exports.table-pdf`, the same institutional template
 * every CRUD export renders, so the offer report comes out visually
 * identical to the rest of the system's documents.
 */
final class FilesystemOfferReportArchive implements OfferReportArchiveInterface
{
    /**
     * The template is built for US Letter (see its own @page rule), so
     * the format is stated at the call site rather than left to the
     * port's a4 default.
     */
    private const PAPER_SIZE = 'letter';

    public function __construct(
        private readonly ExcelFileWriterInterface $excelWriter,
        private readonly PdfFileWriterInterface $pdfWriter,
    ) {}

    public function store(string $term, string $title, array $headers, array $rows): StoredOfferReport
    {
        $disk = $this->disk();
        $disk->makeDirectory($this->directory());

        $excelPath = $disk->path($this->relativePath($term, 'xlsx'));
        $pdfPath = $disk->path($this->relativePath($term, 'pdf'));

        $this->excelWriter->write($rows, $excelPath);

        $this->pdfWriter->write(
            view('exports.table-pdf', [
                'title' => $title,
                'headers' => $headers,
                'rows' => $rows,
            ])->render(),
            $pdfPath,
            self::PAPER_SIZE,
        );

        return new StoredOfferReport(
            term: $term,
            excelPath: $excelPath,
            pdfPath: $pdfPath,
            generatedAt: new DateTimeImmutable,
        );
    }

    public function find(string $term): ?StoredOfferReport
    {
        $disk = $this->disk();

        $excelRelative = $this->relativePath($term, 'xlsx');
        $pdfRelative = $this->relativePath($term, 'pdf');

        // Both or neither: half a deliverable is not a generated report,
        // and offering a download button for the surviving file would
        // misrepresent what happened.
        if (! $disk->exists($excelRelative) || ! $disk->exists($pdfRelative)) {
            return null;
        }

        return new StoredOfferReport(
            term: $term,
            excelPath: $disk->path($excelRelative),
            pdfPath: $disk->path($pdfRelative),
            generatedAt: (new DateTimeImmutable)->setTimestamp($disk->lastModified($pdfRelative)),
        );
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('academic.offer_report.storage_disk', 'local'));

        return $disk;
    }

    private function directory(): string
    {
        return (string) config('academic.offer_report.storage_path', 'reports/offer');
    }

    /**
     * Slugging the term is what keeps a user-supplied string from
     * reaching the filesystem: "2026-II" becomes "2026-ii", and anything
     * containing a slash or a dot-dot loses it here rather than escaping
     * the directory.
     */
    private function relativePath(string $term, string $extension): string
    {
        return $this->directory().'/'.Str::slug($term).'.'.$extension;
    }
}
