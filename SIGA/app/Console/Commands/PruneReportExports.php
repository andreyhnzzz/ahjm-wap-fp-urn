<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Src\Shared\Export\Infrastructure\RowSpool;

/**
 * Deletes generated exports once they are old enough to be nobody's
 * download any more.
 *
 * Nothing deleted these before: every queued export left a .pdf or
 * .xlsx under storage/app/private/report-exports/{user} and a row in
 * report_exports, and both stayed forever. A 12,000-row export is ~4 MB
 * of PDF, so a coordinator exporting a term once a week fills a disk on
 * a schedule — and academic data nobody asked for keeps sitting at rest.
 *
 * The row and the file are deleted together, in that order: a row whose
 * file is gone renders as a broken download, while a file whose row is
 * gone is merely unreferenced and gets swept by the orphan pass below.
 *
 * This is a scheduled command, which means it only runs where something
 * actually runs the scheduler (`* * * * * php artisan schedule:run`).
 * On a host without that cron entry nothing here ever executes — see
 * the README's deployment notes; the command is also safe to run by
 * hand or from any other timer.
 */
class PruneReportExports extends Command
{
    protected $signature = 'exports:prune
        {--days= : Override the retention window from config/exports.php}
        {--dry-run : List what would be deleted and delete nothing}';

    protected $description = 'Delete generated report exports older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('exports.retention_days'));

        if ($days < 1) {
            $this->error('The retention window must be at least one day.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $exports = ReportExport::query()->where('created_at', '<', $cutoff)->get();
        $bytes = 0;

        foreach ($exports as $export) {
            $disk = Storage::disk($export->disk);

            if ($export->file_path !== null && $disk->exists($export->file_path)) {
                $bytes += $disk->size($export->file_path);

                if (! $dryRun) {
                    $disk->delete($export->file_path);
                }
            }

            if (! $dryRun) {
                $export->delete();
            }
        }

        $orphans = $this->orphanFiles();

        if (! $dryRun) {
            foreach ($orphans as $disk => $paths) {
                Storage::disk($disk)->delete($paths);
            }

            RowSpool::pruneStale();
        }

        $this->info(sprintf(
            '%s %d export(s), %.1f MB, and %d orphaned file(s) older than %d day(s).',
            $dryRun ? 'Would delete' : 'Deleted',
            $exports->count(),
            $bytes / 1048576,
            array_sum(array_map('count', $orphans)),
            $days,
        ));

        return self::SUCCESS;
    }

    /**
     * Files under report-exports/ with no row pointing at them.
     *
     * They exist because deletion has two halves that can be interrupted
     * between them, and because a failed job writes nothing but a row
     * that later gets pruned. Without this pass the disk still grows,
     * just more slowly and more confusingly.
     *
     * @return array<string, array<int, string>>
     */
    private function orphanFiles(): array
    {
        $orphans = [];

        foreach (ReportExport::query()->distinct()->pluck('disk') as $name) {
            $disk = Storage::disk((string) $name);
            $referenced = ReportExport::query()
                ->where('disk', $name)
                ->whereNotNull('file_path')
                ->pluck('file_path')
                ->all();

            $found = array_diff($disk->allFiles('report-exports'), $referenced);

            if ($found !== []) {
                $orphans[(string) $name] = array_values($found);
            }
        }

        return $orphans;
    }
}
