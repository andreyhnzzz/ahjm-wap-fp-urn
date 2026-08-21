<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * lang/es/validation.php did not exist, so every form in a Spanish
 * application answered in English: the field names were translated (each
 * Form declares its own validationAttributes()) but the sentence around
 * them came from the fallback locale. "The Cédula field is required."
 *
 * The failure was invisible to the suite because nothing asserted on the
 * language of a message — only on the presence of an error. These two
 * assert the language, and the second one is the one that will catch the
 * next Laravel upgrade that adds a rule nobody translates.
 */
class SpanishValidationTest extends TestCase
{
    public function test_validation_messages_are_translated(): void
    {
        $this->app->setLocale('es');

        $this->assertSame(
            'El campo Cédula es obligatorio.',
            __('validation.required', ['attribute' => 'Cédula']),
        );

        $this->assertSame(
            'El campo Capacidad debe ser al menos 1.',
            __('validation.min.numeric', ['attribute' => 'Capacidad', 'min' => 1]),
        );

        $this->assertSame(
            'El campo Cédula ya ha sido registrado.',
            __('validation.unique', ['attribute' => 'Cédula']),
        );
    }

    public function test_every_rule_the_framework_ships_has_a_spanish_message(): void
    {
        /** @var array<string, mixed> $en */
        $en = Lang::get('validation', [], 'en');
        /** @var array<string, mixed> $es */
        $es = Lang::get('validation', [], 'es');

        // Neither of these is a rule: 'custom' holds per-field overrides
        // and 'attributes' holds field names, and both are empty here on
        // purpose — the field names come from each Form's
        // validationAttributes(), which is already Spanish.
        unset($en['custom'], $en['attributes'], $es['custom'], $es['attributes']);

        $missing = [];

        foreach ($en as $rule => $message) {
            if (! array_key_exists($rule, $es)) {
                $missing[] = $rule;

                continue;
            }

            // The size-style rules are nested by the type they apply to,
            // and a half-translated one is the same bug in miniature: a
            // Spanish 'max.string' next to an English 'max.file'.
            if (is_array($message)) {
                foreach (array_keys($message) as $type) {
                    if (! is_array($es[$rule]) || ! array_key_exists($type, $es[$rule])) {
                        $missing[] = "{$rule}.{$type}";
                    }
                }
            }
        }

        $this->assertSame([], $missing,
            'These validation rules would fall back to English: '.implode(', ', $missing));
    }
}
