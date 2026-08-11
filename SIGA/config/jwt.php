<?php

declare(strict_types=1);

/**
 * Stateless auth for the JSON API (routes/api.php) only. The Livewire UI
 * keeps using Fortify's session-based 'web' guard untouched — this is a
 * separate, deliberately independent secret so a leaked API token can
 * never be replayed against session/cookie encryption, and vice versa.
 */
return [
    'secret' => env('JWT_SECRET'),
    'ttl' => (int) env('JWT_TTL', 3600),
    'algo' => 'HS256',
];
