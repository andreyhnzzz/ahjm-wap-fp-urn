<?php

declare(strict_types=1);

namespace Tests\Feature;

use Generator;
use Src\Shared\Export\Infrastructure\RowSpool;
use Tests\TestCase;

/**
 * The spool is what a queued export's rows travel through now that they
 * are no longer a job argument (see RowSpool for the measurements). Two
 * properties have to hold or the change trades a memory peak for a
 * correctness bug: what comes out is what went in, and reading is lazy.
 */
class RowSpoolTest extends TestCase
{
    public function test_it_round_trips_rows_including_the_characters_that_break_line_formats(): void
    {
        $rows = [
            ['Docente' => 'Ana Lucía Rodríguez', 'Aula' => 'Aula A-101', 'Carga' => 0.75],
            // A cell holding a newline is what rules out the obvious
            // "one row per line, split on \n" reading of this format:
            // json_encode escapes it, json_decode gives it back.
            ['Docente' => "Carlos\nJiménez", 'Aula' => 'Sin asignar', 'Carga' => null],
            ['Docente' => 'Ñoño "el ñu"', 'Aula' => "Tab\there", 'Carga' => 1],
        ];

        $path = RowSpool::write($rows, 4242);

        try {
            $this->assertSame($rows, iterator_to_array(RowSpool::read($path), false));
        } finally {
            RowSpool::discard($path);
        }
    }

    public function test_reading_is_lazy(): void
    {
        // The Excel path's constant-memory guarantee depends on this: if
        // read() ever became "decode the file into an array", the peak
        // this class exists to remove would simply move to the worker.
        $path = RowSpool::write([['a' => 1], ['a' => 2], ['a' => 3]], 4243);

        try {
            $rows = RowSpool::read($path);

            $this->assertInstanceOf(Generator::class, $rows);
            $this->assertSame(['a' => 1], $rows->current());
        } finally {
            RowSpool::discard($path);
        }
    }

    public function test_writing_sweeps_spools_that_no_job_ever_read(): void
    {
        // The failure this covers is the one the array payload could not
        // have: rows written for a job that never ran (a dispatch rolled
        // back with its transaction, a lost queue payload) used to sit in
        // storage forever, megabytes at a time.
        $abandoned = RowSpool::write([['a' => 1]], 4245);
        touch($abandoned, time() - 86_401);

        $fresh = RowSpool::write([['a' => 1]], 4246);

        try {
            $this->assertFileDoesNotExist($abandoned);
            $this->assertFileExists($fresh, 'The sweep took a spool that a queued job may still be waiting on.');
        } finally {
            RowSpool::discard($fresh);
            RowSpool::discard($abandoned);
        }
    }

    public function test_discarding_twice_is_not_an_error(): void
    {
        // The job discards on success and again in failed(), and a retry
        // makes those two orderings both reachable.
        $path = RowSpool::write([['a' => 1]], 4244);

        RowSpool::discard($path);
        RowSpool::discard($path);

        $this->assertFileDoesNotExist($path);
    }
}
