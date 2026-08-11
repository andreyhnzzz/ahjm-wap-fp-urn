{{--
    RE-01 — Reporte de oferta académica por cuatrimestre.

    One action produces both artifacts; the two download buttons only
    hand over what has already been written to disk. The generation time
    is shown next to them so the 30-second acceptance criterion is
    visible on screen as well as in the log.
--}}
<div>
    <div class="card">
        <div class="card-head">
            <span class="card-title">{{ __('Academic offer report') }}</span>
            <div class="card-actions">
                <button type="button" class="btn btn-orange" wire:click="generate" wire:loading.attr="disabled" wire:target="generate">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    {{--
                        The "Generando..." label carries an inline
                        display:none so its resting state is correct in the
                        markup itself, not only once Livewire's JS has
                        booted and hidden it. Livewire drives
                        `style.display` on wire:loading elements directly,
                        so it takes over from here unchanged — this only
                        removes the flash of both labels on first paint.
                    --}}
                    <span wire:loading.remove wire:target="generate">{{ __('Generate report') }}</span>
                    <span wire:loading wire:target="generate" style="display:none">{{ __('Generating...') }}</span>
                </button>

                @if ($hasArtifacts)
                @can('exportPdf', \Src\Reporting\OfferReport\Domain\Entities\OfferReport::class)
                <button type="button" class="btn btn-primary" wire:click="downloadPdf">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>{{ __('Download PDF') }}</span>
                </button>
                @endcan

                @can('exportExcel', \Src\Reporting\OfferReport\Domain\Entities\OfferReport::class)
                <button type="button" class="btn btn-primary" wire:click="downloadExcel">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>{{ __('Download Excel') }}</span>
                </button>
                @endcan
                @endif
            </div>
        </div>

        <div class="card-controls">
            <div class="control-group">
                <span>{{ __('Term') }}:</span>
                <select wire:model.live="term">
                    @forelse ($terms as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                    @empty
                    <option value="">{{ __('No terms loaded') }}</option>
                    @endforelse
                </select>
            </div>

            <div class="control-group report-status">
                @if ($lastGenerationSeconds !== null)
                <span class="status-badge {{ $lastGenerationSeconds <= $maxSeconds ? 'custom' : 'system' }}">
                    {{ __('Generated in :seconds s (limit :limit s)', ['seconds' => number_format($lastGenerationSeconds, 2), 'limit' => $maxSeconds]) }}
                </span>
                @elseif ($hasArtifacts)
                <span class="status-badge system">{{ __('Last generation: :date', ['date' => $generatedAtLabel]) }}</span>
                @else
                <span class="field-hint">{{ __('No files generated for this term yet.') }}</span>
                @endif
            </div>
        </div>

        @if ($summary !== null)
        <div class="report-summary">
            <div class="report-metric">
                <span class="report-metric-count">{{ $summary['groups'] }}</span>
                <span class="report-metric-label">{{ __('Groups in the term') }}</span>
            </div>
            <div class="report-metric">
                <span class="report-metric-count">{{ $summary['students'] }}</span>
                <span class="report-metric-label">{{ __('Estimated students') }}</span>
            </div>
            <div class="report-metric {{ $summary['withoutTeacher'] > 0 ? 'report-metric-alert' : '' }}">
                <span class="report-metric-count">{{ $summary['withoutTeacher'] }}</span>
                <span class="report-metric-label">{{ __('Groups without a teacher') }}</span>
            </div>
            <div class="report-metric {{ $summary['withoutClassroom'] > 0 ? 'report-metric-alert' : '' }}">
                <span class="report-metric-count">{{ $summary['withoutClassroom'] }}</span>
                <span class="report-metric-label">{{ __('Groups without a classroom') }}</span>
            </div>
        </div>
        @endif

        <div class="table-scroll" wire:loading.class="opacity-50" wire:target="generate,term">
            <div class="table-inner" style="--table-cols: 1.4fr 1fr 1.8fr 1.2fr 1.1fr 1fr 1fr;" role="table">
                <div class="data-row data-row-head" role="row">
                    @foreach ($headers as $header)
                    <span role="columnheader">{{ $header['label'] }}</span>
                    @endforeach
                </div>

                @forelse ($rows as $row)
                <div class="data-row" role="row">
                    <span class="cell-strong">{{ $row['groupCode'] }}</span>
                    <span>{{ $row['courseCode'] }}</span>
                    <span class="{{ $row['hasTeacher'] ? '' : 'cell-unassigned' }}">{{ $row['teacher'] }}</span>
                    <span class="{{ $row['hasClassroom'] ? '' : 'cell-unassigned' }}">{{ $row['classroom'] }}</span>
                    <span>{{ $row['modality'] }}</span>
                    <span>
                        <span class="status-badge {{ $row['statusVariant'] }}">{{ $row['status'] }}</span>
                    </span>
                    <span class="metric-value">{{ $row['estimatedEnrollment'] }}</span>
                </div>
                @empty
                <div class="empty-row">{{ __('No records found') }}</div>
                @endforelse
            </div>
        </div>

        <div class="card-footer">
            <span>{{ __('Both files carry the same information and are produced by a single generation.') }}</span>
        </div>
    </div>
</div>
