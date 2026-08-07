@props([
'headers' => [],
'paginator' => null,
'sortKey' => null,
'sortDir' => 'asc',
'canCreate' => false,
'canExport' => false,
'title' => '',
])

@php
$total = $paginator?->total() ?? 0;
$perPage = $paginator?->perPage() ?? 10;
$currentPage = $paginator?->currentPage() ?? 1;
$lastPage = max(1, $paginator?->lastPage() ?? 1);
$from = $total === 0 ? 0 : ($currentPage - 1) * $perPage + 1;
$to = min($currentPage * $perPage, $total);

if ($lastPage <= 7) {
    $pageSet=range(1, $lastPage);
    } else {
    $pageSet=collect([1, 2, $lastPage - 1, $lastPage, $currentPage - 1, $currentPage, $currentPage + 1])
    ->filter(fn ($p) => $p >= 1 && $p <= $lastPage)
        ->unique()
        ->sort()
        ->values()
        ->all();
        }
        @endphp

        <div class="bg-white rounded shadow-sm border border-slate-200 overflow-visible">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 border-b border-slate-200">
                <h2 class="font-semibold text-slate-700">{{ $title }}</h2>
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canCreate)
                    <button wire:click="openCreateModal" class="bg-[#e2801f] hover:bg-[#c96f16] text-white text-sm font-medium px-4 py-2 rounded flex items-center gap-2.5">
                        <span class="w-4 h-4 rounded-full bg-white text-[#e2801f] flex items-center justify-center flex-shrink-0">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                        </span>
                        {{ __('Add') }}
                    </button>
                    @endif

                    @if ($canExport)
                    <div class="relative" x-data="{ open: false }">
                        <button type="button" x-on:click="open = !open" class="bg-[#1a3868] hover:bg-[#142c53] text-white text-sm font-medium px-4 py-2 rounded flex items-center gap-2">
                            <span>&#9776;</span> {{ __('Download') }}
                        </button>
                        <div x-show="open" x-on:click.outside="open = false" x-transition class="absolute right-0 mt-1 w-52 bg-white border border-slate-200 rounded shadow-lg z-20 py-1" style="display: none;">
                            <button type="button" wire:click="exportExcel" class="w-full flex items-center gap-3 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 text-left">
                                <img src="{{ asset('images/icons/xls-icon.png') }}" class="w-9 h-9 flex-shrink-0 object-contain" alt="XLS">
                                XLS
                            </button>
                            <button type="button" wire:click="exportPdf" class="w-full flex items-center gap-3 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 text-left">
                                <img src="{{ asset('images/icons/pdf-icon.png') }}" class="w-9 h-9 flex-shrink-0 object-contain" alt="PDF">
                                PDF
                            </button>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 text-sm text-slate-600 border-b border-slate-200 bg-slate-50">
                <div class="flex items-center gap-2">
                    <span class="uppercase text-xs tracking-wide">{{ __('Show') }}</span>
                    <select wire:model.live="perPage" class="border border-slate-300 rounded px-2 py-1 text-sm">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span class="uppercase text-xs tracking-wide">{{ __('Records') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="uppercase text-xs tracking-wide">{{ __('Search') }}:</span>
                    <input wire:model.live.debounce.400ms="search" class="border border-slate-300 rounded px-2 py-1 text-sm w-full sm:w-56">
                </div>
            </div>

            <div class="overflow-x-auto" wire:loading.class="opacity-50" wire:target="search,perPage,sort,previousPage,nextPage,gotoPage">
                <table class="w-full text-sm min-w-[900px]">
                    <thead>
                        <tr class="text-left text-xs uppercase text-slate-500 border-b border-slate-200">
                            @foreach ($headers as $header)
                            <th class="px-4 py-3 font-semibold {{ ($header['sortable'] ?? false) ? 'cursor-pointer select-none' : '' }}"
                                @if ($header['sortable'] ?? false) wire:click="sort('{{ $header['key'] }}')" @endif>
                                <span class="flex items-center gap-1">
                                    {{ $header['label'] }}
                                    @if ($header['sortable'] ?? false)
                                    <span class="text-slate-300">
                                        @if ($sortKey === $header['key'])
                                        {{ $sortDir === 'asc' ? '▲' : '▼' }}
                                        @else
                                        ↕
                                        @endif
                                    </span>
                                    @endif
                                </span>
                            </th>
                            @endforeach
                            <th class="px-4 py-3 font-semibold">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{ $slot }}
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 text-sm text-slate-600">
                <span>
                    @if ($total === 0)
                    {{ __('No records found') }}
                    @else
                    {{ __('Showing :from to :to of :total records', ['from' => $from, 'to' => $to, 'total' => $total]) }}
                    @endif
                </span>
                <div class="flex flex-wrap items-center gap-1">
                    <button wire:click="previousPage" @disabled($currentPage <=1) class="border border-slate-300 rounded px-3 py-1 text-slate-600 hover:bg-slate-50 disabled:opacity-40">{{ __('Previous') }}</button>
                    <div class="flex items-center gap-1">
                        @php $prev = null; @endphp
                        @foreach ($pageSet as $p)
                        @if (!is_null($prev) && $p - $prev > 1)
                        <span class="px-2 py-1 text-slate-400">…</span>
                        @endif
                        <button wire:click="gotoPage({{ $p }})" class="border rounded px-3 py-1 {{ $p === $currentPage ? 'bg-[#1a3868] text-white border-[#1a3868]' : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
                            {{ $p }}
                        </button>
                        @php $prev = $p; @endphp
                        @endforeach
                    </div>
                    <button wire:click="nextPage" @disabled($currentPage>= $lastPage) class="border border-slate-300 rounded px-3 py-1 text-slate-600 hover:bg-slate-50 disabled:opacity-40">{{ __('Next') }}</button>
                </div>
            </div>
        </div>