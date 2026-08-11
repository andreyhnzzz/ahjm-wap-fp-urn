<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\PublicHolidays\PublicHolidaysClient;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class HolidaysWidget extends Component
{
    public function render(PublicHolidaysClient $client): View
    {
        return view('livewire.holidays-widget', [
            'holidays' => $client->upcoming(),
        ]);
    }
}
