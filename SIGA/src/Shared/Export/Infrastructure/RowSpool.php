<?php

declare(strict_types=1);

namespace Src\Shared\Export\Infrastructure;

use Generator;
use RuntimeException;

/**
 * Hands a queued export its rows through a file instead of through the
 * job payload.
 *
 * WHY: a queued job's constructor arguments are serialized into the jobs
 * table, so passing the rows meant holding, in one web request, the
 * entity list, the projected rows, the serialized payload and the copy
 * the database driver makes of it. Measured on the real data:
 *
 *   rows     request peak   payload
 *   20,000       116 MB      7.2 MB
 *   45,000       192 MB     12.2 MB
 *
 * With PHP's default 128 MB memory_limit, 20,000 rows fits with 12 MB to
 * spare and 45,000 dies outright — and the failure lands on the click,
 * not in the worker where the heavy work is supposed to live.
 *
 * The rows are written straight through as they are produced (
 * InteractsWithExports::mapRowsForExport() is a generator and stays one),
 * so the request holds one row at a time instead of all of them, and the
 * payload becomes a path.
 *
 * NDJSON, not serialize(): one row per line means the reader is a
 * generator too, so the Excel path stays constant-memory end to end
 * rather than trading a request-side peak for a worker-side one. (The
 * PDF path does materialise the rows again in the worker — chunking has
 * to count them before it can split them — but the worker is where a
 * queue's memory limit is meant to be spent, and it is the process the
 * README's `--memory=2048` already talks about.)
 */
final class RowSpool
{
    private const DIRECTORY = 'app/export-rows';

    /**
     * How long a spool may sit unread before a later export sweeps it.
     *
     * The job discards its own spool on success and on failure, so this
     * only catches the case where no job ever ran: a queue that lost the
     * payload, a worker that never started, a dispatch rolled back with
     * the transaction around it. The array payload this replaced could
     * not leak — it lived and died with the row in the jobs table — so
     * the sweep is part of the trade, not an afterthought.
     *
     * Generous on purpose: a queue backed up for an hour is a slow
     * queue, not a broken one, and deleting the rows out from under a
     * job still waiting its turn would turn that into a failed export.
     */
    private const STALE_AFTER_SECONDS = 86400;

    /**
     * @param  iterable<array<string, scalar|null>>  $rows
     * @return string absolute path of the spooled file
     */
    public static function write(iterable $rows, int $exportId): string
    {
        $path = self::path($exportId);

        // Here rather than on a schedule: this project has no scheduler
        // running (nothing else needs one), and an export is exactly the
        // moment when a sweep costs nothing next to the work about to
        // happen and when the directory is known to be in use.
        self::pruneStale();

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not open the export row spool at {$path}.");
        }

        try {
            foreach ($rows as $row) {
                // JSON_THROW_ON_ERROR rather than a silent false: a row
                // that cannot be encoded would otherwise write an empty
                // line and lose itself between the click and the file.
                fwrite($handle, json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    /**
     * @return Generator<int, array<string, scalar|null>>
     */
    public static function read(string $path): Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("The export row spool at {$path} could not be read.");
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                /** @var array<string, scalar|null> $row */
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Safe to call twice and safe to call on a path that was never
     * written: the job discards the spool on success and on failure, and
     * those two are not mutually exclusive when a retry is involved.
     */
    public static function discard(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Deletes spools older than STALE_AFTER_SECONDS. Never touches a
     * fresh one, so a queue with several exports in flight is safe.
     */
    public static function pruneStale(): void
    {
        $directory = storage_path(self::DIRECTORY);

        if (! is_dir($directory)) {
            return;
        }

        $deadline = time() - self::STALE_AFTER_SECONDS;

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.ndjson') ?: [] as $file) {
            // filemtime() returns false for a file another process just
            // removed; comparing that to the deadline would delete
            // whatever raced us. Only a real, old timestamp qualifies.
            $modified = @filemtime($file);

            if (is_int($modified) && $modified < $deadline) {
                @unlink($file);
            }
        }
    }

    private static function path(int $exportId): string
    {
        $directory = storage_path(self::DIRECTORY);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory.DIRECTORY_SEPARATOR.$exportId.'.ndjson';
    }
}
