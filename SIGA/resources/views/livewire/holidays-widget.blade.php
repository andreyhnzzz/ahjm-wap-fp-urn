@use('Illuminate\Support\Carbon')

<div class="space-y-3 rounded-lg p-4" style="background: var(--cardBg); border: 1px solid var(--border);">
    <flux:heading size="sm">{{ __('Upcoming public holidays') }}</flux:heading>

    @if (empty($holidays))
        <flux:text>{{ __('No holiday data available right now.') }}</flux:text>
    @else
        <ul class="space-y-1">
            @foreach ($holidays as $holiday)
                <li class="flex justify-between text-sm">
                    <span>{{ $holiday['name'] }}</span>
                    <span style="color: var(--textSecondary);">{{ Carbon::parse($holiday['date'])->translatedFormat('d M Y') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
