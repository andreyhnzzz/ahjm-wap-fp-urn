@use('Illuminate\Support\Carbon')

<div class="space-y-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 p-4">
    <flux:heading size="sm">{{ __('Upcoming public holidays') }}</flux:heading>

    @if (empty($holidays))
        <flux:text>{{ __('No holiday data available right now.') }}</flux:text>
    @else
        <ul class="space-y-1">
            @foreach ($holidays as $holiday)
                <li class="flex justify-between text-sm">
                    <span>{{ $holiday['name'] }}</span>
                    <span class="text-zinc-500 dark:text-zinc-400">{{ Carbon::parse($holiday['date'])->translatedFormat('d M Y') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
