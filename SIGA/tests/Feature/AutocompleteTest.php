<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Concerns\InteractsWithAutocomplete;
use Tests\TestCase;

/**
 * The filtering behind <x-ui.autocomplete>. It runs on the server, so it
 * is testable without a browser — which is the point: the browser only
 * opens and closes the list.
 */
class AutocompleteTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class
        {
            use InteractsWithAutocomplete {
                filterOptions as public;
                labelFor as public;
            }
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    private function teachers(): array
    {
        return [
            ['value' => '1', 'label' => 'Ana Lucía Rodríguez Mora'],
            ['value' => '2', 'label' => 'Carlos Eduardo Jiménez Salas'],
            ['value' => '3', 'label' => 'María Fernanda Solís Vargas'],
            ['value' => '4', 'label' => 'Jorge Andrés Castro Núñez'],
        ];
    }

    public function test_it_matches_ignoring_accents_and_case(): void
    {
        // The data is Spanish: someone typing "nunez" on a keyboard they
        // do not want to fight is looking for "Núñez", and coming back
        // empty reads as the feature being broken rather than as a
        // spelling lesson.
        $found = $this->subject->filterOptions($this->teachers(), 'nunez');

        $this->assertCount(1, $found);
        $this->assertSame('Jorge Andrés Castro Núñez', $found[0]['label']);

        $this->assertCount(1, $this->subject->filterOptions($this->teachers(), 'JIMENEZ'));
        $this->assertCount(1, $this->subject->filterOptions($this->teachers(), 'lucia'));
    }

    public function test_an_empty_query_offers_the_first_options_not_none(): void
    {
        // Opening a picker and seeing nothing looks broken; the useful
        // default is the most likely choices.
        $this->assertCount(4, $this->subject->filterOptions($this->teachers(), ''));
        $this->assertCount(4, $this->subject->filterOptions($this->teachers(), '   '));
    }

    public function test_it_caps_the_suggestions(): void
    {
        $many = [];
        for ($i = 1; $i <= 50; $i++) {
            $many[] = ['value' => (string) $i, 'label' => "Docente {$i}"];
        }

        // Past the cap the answer is a narrower query, not a longer list.
        $this->assertCount(8, $this->subject->filterOptions($many, 'Docente'));
    }

    public function test_it_returns_nothing_when_nothing_matches(): void
    {
        $this->assertSame([], $this->subject->filterOptions($this->teachers(), 'zzz'));
    }

    public function test_it_resolves_the_label_of_the_current_selection(): void
    {
        // A reopened modal has to show "María Fernanda…", not "3".
        $this->assertSame('María Fernanda Solís Vargas',
            $this->subject->labelFor($this->teachers(), '3'));

        // Unassigned is a legal state for a group (INFRA-01 needs it), and
        // so is a dangling id after the teacher was deleted.
        $this->assertSame('', $this->subject->labelFor($this->teachers(), ''));
        $this->assertSame('', $this->subject->labelFor($this->teachers(), '999'));
    }
}
