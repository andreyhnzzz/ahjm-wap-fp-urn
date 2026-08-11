{{--
    RE-02 — Reporte de carga docente por profesor.

    The mandatory legend is shown here too, not only in the PDF: the
    caveat travels with the numbers wherever they are read.
--}}
<div>
    <div class="card">
        <div class="card-head">
            <span class="card-title">{{ __('Teacher load report') }}</span>
            <div class="card-actions">
                @can('exportPdf', \Src\Reporting\TeacherLoadReport\Domain\Entities\TeacherLoadReport::class)
                <button type="button" class="btn btn-primary" wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>{{ __('Export to PDF') }}</span>
                </button>
                @endcan

                @can('exportExcel', \Src\Reporting\TeacherLoadReport\Domain\Entities\TeacherLoadReport::class)
                <button type="button" class="btn btn-primary" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>{{ __('Export to Excel') }}</span>
                </button>
                @endcan
            </div>
        </div>

        <div class="card-controls">
            <div class="control-group">
                <span>{{ __('Teacher') }}:</span>
                <select wire:model.live="teacherId">
                    @forelse ($teacherOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @empty
                    <option value="">{{ __('No teachers loaded') }}</option>
                    @endforelse
                </select>
            </div>

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
        </div>

        @if ($summary !== null)
        <div class="load-header">
            <div class="load-identity">
                <span class="load-name">{{ $summary['teacherName'] }}</span>
                <span class="load-meta">{{ $summary['identityCard'] }} · {{ $summary['term'] }}</span>
            </div>
            <span class="workload-badge {{ $summary['statusVariant'] }}">{{ $summary['statusLabel'] }}</span>
        </div>

        <div class="report-summary">
            <div class="report-metric">
                <span class="report-metric-count">{{ $summary['assigned'] }}</span>
                <span class="report-metric-label">{{ __('Assigned workload') }}</span>
            </div>
            <div class="report-metric">
                <span class="report-metric-count">{{ $summary['reference'] }}</span>
                <span class="report-metric-label">{{ __('Estimated workload') }}</span>
            </div>
            <div class="report-metric">
                <span class="report-metric-count">{{ $summary['utilization'] }}%</span>
                <span class="report-metric-label">{{ __('Usage of the reference workload') }}</span>
            </div>
            <div class="report-metric">
                <span class="report-metric-count">{{ $summary['groups'] }}</span>
                <span class="report-metric-label">{{ __('Assigned groups') }}</span>
            </div>
        </div>
        @endif

        <div class="table-scroll" wire:loading.class="opacity-50" wire:target="teacherId,term">
            <div class="table-inner" style="--table-cols: 1.4fr 1.1fr 1.3fr 1.1fr 1.1fr 1fr 1.1fr;" role="table">
                <div class="data-row data-row-head" role="row">
                    @foreach ($headers as $header)
                    <span role="columnheader">{{ $header['label'] }}</span>
                    @endforeach
                </div>

                @forelse ($rows as $row)
                <div class="data-row" role="row">
                    <span class="cell-strong">{{ $row['groupCode'] }}</span>
                    <span>{{ $row['courseCode'] }}</span>
                    <span class="{{ $row['hasClassroom'] ? '' : 'cell-unassigned' }}">{{ $row['classroom'] }}</span>
                    <span>{{ $row['modality'] }}</span>
                    <span>
                        <span class="status-badge {{ $row['statusVariant'] }}">{{ $row['status'] }}</span>
                    </span>
                    <span class="metric-value">{{ $row['estimatedEnrollment'] }}</span>
                    <span class="metric-value">{{ $row['assignedWorkload'] }}</span>
                </div>
                @empty
                <div class="empty-row">
                    {{ $summary === null ? __('Select a teacher and a term first.') : __('This teacher has no groups assigned in the selected term.') }}
                </div>
                @endforelse
            </div>
        </div>

        <div class="card-footer">
            <span class="load-legend">{{ $legend }}</span>
            @if ($summary !== null)
            <span>{{ __('Under-contracting is flagged below :percent% of the reference workload.', ['percent' => $summary['underLoadPercent']]) }}</span>
            @endif
        </div>
    </div>
</div>
