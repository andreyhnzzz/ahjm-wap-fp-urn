{{--
    RE-04 — Tablero de riesgos.

    `wire:poll` is what satisfies "the board must reflect a data change
    within 60 seconds without the user reloading the page": Livewire
    re-runs render() on the interval, which re-evaluates the risks
    against live data. The interval is server-side configuration
    (config/academic.php), clamped in the component, never hardcoded
    here.
--}}
<div wire:poll.{{ $refreshSeconds }}s>
    <div class="card">
        <div class="card-head">
            <span class="card-title">{{ __('Risk board of the academic offer') }}</span>
            <div class="card-actions">
                <span class="live-pill">
                    <span class="live-dot" aria-hidden="true"></span>
                    {{ __('Live · updated at :time', ['time' => $updatedAt]) }}
                </span>
            </div>
        </div>

        <div class="risk-summary">
            @foreach ($levels as $level)
            <div class="risk-metric risk-{{ $level['key'] }}">
                <span class="risk-metric-count">{{ $level['count'] }}</span>
                <span class="risk-metric-label">{{ __('Risk level: :level', ['level' => $level['label']]) }}</span>
            </div>
            @endforeach
            <div class="risk-metric risk-total">
                <span class="risk-metric-count">{{ $total }}</span>
                <span class="risk-metric-label">{{ __('Total detected risks') }}</span>
            </div>
        </div>

        <div class="risk-columns">
            @foreach ($levels as $level)
            <section class="risk-column" aria-label="{{ $level['label'] }}">
                <header class="risk-column-head risk-{{ $level['key'] }}">
                    <span class="risk-column-title">{{ $level['label'] }}</span>
                    <span class="risk-column-count">{{ $level['count'] }}</span>
                </header>

                <div class="risk-column-body">
                    @forelse ($level['items'] as $item)
                    <article class="risk-item risk-{{ $level['key'] }}" wire:key="{{ $item['key'] }}">
                        <span class="risk-item-title">{{ $item['title'] }}</span>
                        <span class="risk-item-subject">{{ $item['subject'] }} · {{ $item['term'] }}</span>
                        <span class="risk-item-text">{{ $item['description'] }}</span>
                        <a href="{{ $item['link'] }}" wire:navigate class="risk-item-link">
                            {{ __('Go to record') }}
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </article>
                    @empty
                    <div class="risk-empty">{{ __('No risks at this level') }}</div>
                    @endforelse
                </div>
            </section>
            @endforeach
        </div>

        <div class="card-footer">
            <span>{{ __('The board refreshes itself every :seconds seconds without reloading the page.', ['seconds' => $refreshSeconds]) }}</span>
        </div>
    </div>
</div>
