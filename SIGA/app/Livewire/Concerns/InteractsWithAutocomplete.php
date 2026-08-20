<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Server-side filtering for <x-ui.autocomplete>.
 *
 * The filtering deliberately happens here and not in Alpine. A `<select>`
 * that ships every option to the browser is fine for ten classrooms and
 * stops being fine the moment the catalogue grows — which is the whole
 * reason this exists. Doing it on the server keeps the payload
 * proportional to what is shown (MAX_SUGGESTIONS) instead of to what
 * exists, and keeps the app server-rendered: Alpine only opens, closes
 * and moves the highlight, it never holds the data.
 *
 * It also matters for authorisation. The options a component passes in
 * have already been through its own use case and policy; filtering a list
 * it already decided the user may see cannot widen that, whereas an
 * endpoint that queries by whatever string arrives would have to
 * re-authorise on every keystroke.
 *
 * Matching is accent- and case-insensitive because the data is Spanish:
 * a coordinator typing "nunez" is looking for "Núñez", and failing to
 * find it reads as the feature being broken.
 */
trait InteractsWithAutocomplete
{
    /**
     * How many suggestions are rendered at once. Enough to choose from,
     * few enough that the dropdown never becomes its own scrolling
     * problem — past this the answer is a narrower query, not a longer
     * list.
     */
    private const MAX_SUGGESTIONS = 8;

    /**
     * Narrows a component's option list to what matches the query.
     *
     * An empty query returns the first MAX_SUGGESTIONS rather than
     * nothing: opening the field and seeing the most likely choices is
     * how a picker is expected to behave, and it keeps the control
     * usable for someone who does not know what to type.
     *
     * @param  array<int, array{value: string, label: string}>  $options
     * @return array<int, array{value: string, label: string}>
     */
    protected function filterOptions(array $options, string $query): array
    {
        $needle = $this->foldForSearch($query);

        if ($needle === '') {
            return array_slice($options, 0, self::MAX_SUGGESTIONS);
        }

        $matches = array_filter(
            $options,
            fn (array $option): bool => str_contains($this->foldForSearch($option['label']), $needle),
        );

        return array_slice(array_values($matches), 0, self::MAX_SUGGESTIONS);
    }

    /**
     * Finds the label for an already-selected value, so the input can
     * show "Ana Lucía Rodríguez" instead of "17" when a modal reopens.
     *
     * @param  array<int, array{value: string, label: string}>  $options
     */
    protected function labelFor(array $options, string $value): string
    {
        if ($value === '') {
            return '';
        }

        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return '';
    }

    /**
     * Lowercase, accent-stripped. iconv() is not used: it is locale
     * dependent and returns false on some builds, which would silently
     * turn every search into a no-match.
     */
    private function foldForSearch(string $text): string
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');

        return strtr($lower, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
    }
}
