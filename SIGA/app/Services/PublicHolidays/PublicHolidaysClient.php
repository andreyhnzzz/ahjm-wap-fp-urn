<?php

declare(strict_types=1);

namespace App\Services\PublicHolidays;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * External REST API consumption (course transversal requirement):
 * Costa Rica's upcoming public holidays from the free Nager.Date API —
 * no key, no auth, read-only. Coordinators use this to sanity-check why
 * a term's enrollment might dip around a given week; it does not feed
 * any risk/report calculation.
 *
 * Cached a day at a time: the source updates rarely and the dashboard
 * widget has no reason to hit the network on every page load.
 */
final class PublicHolidaysClient
{
    private const ENDPOINT = 'https://date.nager.at/api/v3/NextPublicHolidays/CR';

    /**
     * @return array<int, array{date: string, name: string}>
     */
    public function upcoming(): array
    {
        $cached = Cache::get('public-holidays.cr');

        if ($cached !== null) {
            return $cached;
        }

        $holidays = $this->fetch();

        // Only a successful, non-empty response is worth a day in cache —
        // caching a transient failure would leave the widget showing
        // "no data" for 24h instead of self-healing on the next request.
        if ($holidays !== []) {
            Cache::put('public-holidays.cr', $holidays, now()->addDay());
        }

        return $holidays;
    }

    /**
     * @return array<int, array{date: string, name: string}>
     */
    private function fetch(): array
    {
        try {
            $response = Http::timeout(3)->get(self::ENDPOINT);
        } catch (Throwable $e) {
            Log::warning('public-holidays: request failed', ['error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('public-holidays: unexpected response', ['status' => $response->status()]);

            return [];
        }

        /** @var array<int, array{date: string, localName: string}> $holidays */
        $holidays = $response->json();

        return array_map(
            static fn (array $holiday): array => [
                'date' => $holiday['date'],
                'name' => $holiday['localName'],
            ],
            $holidays,
        );
    }
}
