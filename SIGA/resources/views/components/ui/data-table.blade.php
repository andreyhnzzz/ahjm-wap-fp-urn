@props([
'headers' => [],
'paginator' => null,
'rows' => [],
'mode' => 'server',
'searchable' => [],
'sortKey' => null,
'sortDir' => 'asc',
'perPage' => 10,
'canCreate' => false,
'canSearch' => true,
'canExportPdf' => false,
'canExportExcel' => false,
'title' => '',
'tableCols' => '1fr',
'createAction' => "\$wire.openCreateModal()",
])

@php
$isClient = $mode === 'client';
$canExport = $canExportPdf || $canExportExcel;
@endphp

{{--
    Full-width by design (.card has `width: 100%` in app.css) — this
    component fills whatever container it's placed in. Do NOT wrap
    <x-ui.data-table> in a max-w-* / mx-auto container in the consuming
    view (role-component.blade.php, permission-component.blade.php,
    and any future CRUD's *-component.blade.php) unless a narrower
    table is a deliberate, one-off design decision for that specific
    screen. The outer <div> in those views only exists because Livewire
    full-page components require a single root element — it carries no
    width constraint itself.

    Permission props (canCreate/canSearch/canExportPdf/canExportExcel):
    always pass these from Auth::user()->can(...) / hasPermissionTo(...)
    in the consuming view — never hardcode true here. Each one hides
    its UI element AND the underlying Livewire action is independently
    authorize()'d server-side (openCreateModal/save/delete/exportPdf/
    exportExcel all call $this->authorize(...) themselves) — so a
    prop passed wrong only hides/shows a button, it never grants real
    access on its own.
--}}

@php
if (! $isClient) {
$total = $paginator?->total() ?? 0;
$perPage = $paginator?->perPage() ?? $perPage;
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
        }
        @endphp

        <div class="card"
            @if ($isClient)
            wire:key="crud-table-{{ $tableVersion ?? 0 }}"
            x-data="crudTable({
        rows: @js($rows),
        searchable: @js($searchable),
        sortKey: @js($sortKey),
        sortDir: @js($sortDir),
        perPage: @js($perPage),
    })"
            @endif>
            <div class="card-head">
                <span class="card-title">{{ $title }}</span>
                <div class="card-actions">
                    @if ($canCreate)
                    <button type="button" class="btn btn-orange" @click="{{ $createAction }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        <span>{{ __('Add') }}</span>
                    </button>
                    @endif

                    @if ($canExport)
                    <div class="download-wrap" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button type="button" class="btn btn-primary" @click="open = !open">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            <span>{{ __('Download') }}</span>
                        </button>
                        <div class="download-menu" :class="{ 'open': open }">
                            {{--
                        Client mode passes Alpine's live `search` value as
                        a plain positional argument — NOT wrapped as
                        `{ search: search }`. Livewire evaluates that JS
                        object literally and hands it to PHP as a single
                        array argument rather than unpacking it into a
                        named parameter, which is what actually broke
                        here the first time (search arrived as an array,
                        not a string). A bare value works because Livewire
                        already skips $exporter/$useCase (both container-
                        resolvable) and fills the one remaining
                        unresolved parameter, $search, by position —
                        same proven pattern openEditModal(row.id) already
                        uses elsewhere in this file. The PHP $search
                        property is never updated in client mode (that
                        input binds to Alpine's `search`, not wire:model,
                        by design — see the control-group above). Server
                        mode passes nothing; $this->search there is
                        already live via wire:model, no explicit value
                        needed.
                    --}}
                            @if ($canExportPdf)
                            <button type="button" class="download-item" wire:click="{{ $isClient ? 'exportPdf(search)' : 'exportPdf' }}" x-on:click="open = false">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-red-600" style="color:#DC2626">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="9" y1="13" x2="9" y2="17" />
                                    <line x1="12" y1="13" x2="12" y2="17" />
                                    <line x1="15" y1="13" x2="15" y2="17" />
                                </svg>
                                <span>{{ __('Export to PDF') }}</span>
                            </button>
                            @endif
                            @if ($canExportPdf && $canExportExcel)
                            <div class="download-divider"></div>
                            @endif
                            @if ($canExportExcel)
                            <button type="button" class="download-item" wire:click="{{ $isClient ? 'exportExcel(search)' : 'exportExcel' }}" x-on:click="open = false">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="color:#16A34A">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <path d="M9 13l2.5 5L14 13" />
                                </svg>
                                <span>{{ __('Export to Excel') }}</span>
                            </button>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <div class="card-controls">
                <div class="control-group">
                    <span>{{ __('Show') }}</span>
                    @if ($isClient)
                    <select x-model.number="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    @else
                    <select wire:model.live="perPage">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    @endif
                    <span>{{ __('Records') }}</span>
                </div>
                @if ($canSearch)
                <div class="control-group">
                    <span>{{ __('Search') }}:</span>
                    @if ($isClient)
                    <input type="text" x-model.debounce.150ms="search">
                    @else
                    <input type="text" wire:model.live.debounce.400ms="search">
                    @endif
                </div>
                @endif
            </div>

            <div class="table-scroll"
                @if ($isClient)
                wire:loading.class="opacity-50" wire:target="delete,save,exportPdf,exportExcel"
                @else
                wire:loading.class="opacity-50" wire:target="search,perPage,sort,previousPage,nextPage,gotoPage,delete,save,exportPdf,exportExcel"
                @endif>
                <div class="table-inner" style="--table-cols: {{ $tableCols }};" role="table">
                    <div class="data-row data-row-head" role="row">
                        @foreach ($headers as $header)
                        <span data-sortable="{{ ($header['sortable'] ?? false) ? 'true' : 'false' }}"
                            role="columnheader"
                            @if ($header['sortable'] ?? false)
                            @if ($isClient) @click="sort('{{ $header['key'] }}')" @else wire:click="sort('{{ $header['key'] }}')" @endif
                            @endif>
                            {{ $header['label'] }}
                            @if ($header['sortable'] ?? false)
                            @if ($isClient)
                            <span x-text="sortKey === '{{ $header['key'] }}' ? (sortDir === 'asc' ? '▲' : '▼') : '↕'"></span>
                            @else
                            <span>
                                @if ($sortKey === $header['key'])
                                {{ $sortDir === 'asc' ? '▲' : '▼' }}
                                @else
                                ↕
                                @endif
                            </span>
                            @endif
                            @endif
                        </span>
                        @endforeach
                        <span>{{ __('Actions') }}</span>
                    </div>

                    {{ $slot }}
                </div>
            </div>

            <div class="card-footer">
                @if ($isClient)
                <span>
                    <span x-show="total === 0">{{ __('No records found') }}</span>
                    <span x-show="total > 0" x-text="paginationSummary(@js(__('Showing :from to :to of :total records')))"></span>
                </span>
                @else
                <span>
                    @if ($total === 0)
                    {{ __('No records found') }}
                    @else
                    {{ __('Showing :from to :to of :total records', ['from' => $from, 'to' => $to, 'total' => $total]) }}
                    @endif
                </span>
                @endif
                <div class="pagination">
                    @if ($isClient)
                    <button type="button" class="page-btn" :class="{ 'disabled': page <= 1 }" :disabled="page <= 1" @click="previousPage()">{{ __('Previous') }}</button>
                    <template x-for="(p, index) in pageSet" :key="p">
                        <span style="display:flex;align-items:center;gap:6px;">
                            <span x-show="index > 0 && p - pageSet[index - 1] > 1" class="page-ellipsis">…</span>
                            <button type="button" class="page-btn" :class="{ 'active': p === page }" @click="gotoPage(p)" x-text="p"></button>
                        </span>
                    </template>
                    <button type="button" class="page-btn" :class="{ 'disabled': page >= lastPage }" :disabled="page >= lastPage" @click="nextPage()">{{ __('Next') }}</button>
                    @else
                    <button type="button" class="page-btn {{ $currentPage <= 1 ? 'disabled' : '' }}" @disabled($currentPage <=1) wire:click="previousPage">{{ __('Previous') }}</button>
                    @php $prev = null; @endphp
                    @foreach ($pageSet as $p)
                    @if (!is_null($prev) && $p - $prev > 1)
                    <span class="page-ellipsis">…</span>
                    @endif
                    <button type="button" class="page-btn {{ $p === $currentPage ? 'active' : '' }}" wire:click="gotoPage({{ $p }})">{{ $p }}</button>
                    @php $prev = $p; @endphp
                    @endforeach
                    <button type="button" class="page-btn {{ $currentPage >= $lastPage ? 'disabled' : '' }}" @disabled($currentPage>= $lastPage) wire:click="nextPage">{{ __('Next') }}</button>
                    @endif
                </div>
            </div>
        </div>