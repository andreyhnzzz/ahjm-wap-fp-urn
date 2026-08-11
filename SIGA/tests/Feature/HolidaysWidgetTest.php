<?php

namespace Tests\Feature;

use App\Livewire\HolidaysWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * External REST API consumption (course transversal requirement): the
 * dashboard's public-holidays widget, backed by App\Services\PublicHolidays.
 */
class HolidaysWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The client caches the response for a day; each test needs its
        // own faked HTTP call to actually run, not the previous test's.
        Cache::forget('public-holidays.cr');
    }

    public function test_it_renders_holidays_returned_by_the_external_api(): void
    {
        Http::fake([
            'date.nager.at/*' => Http::response([
                ['date' => '2026-12-25', 'localName' => 'Navidad'],
            ]),
        ]);

        Livewire::test(HolidaysWidget::class)
            ->assertSee('Navidad');
    }

    public function test_it_degrades_quietly_when_the_external_api_is_unreachable(): void
    {
        Http::fake([
            'date.nager.at/*' => Http::response(null, 500),
        ]);

        Livewire::test(HolidaysWidget::class)
            ->assertOk();
    }
}
