<div x-data="{
    confirmDelete: { open: false, step: 'confirm', id: null },
    askDelete(id) {
        this.confirmDelete = { open: true, step: 'confirm', id };
    },
    runDelete() {
        $wire.delete(this.confirmDelete.id)
            .then(() => { this.confirmDelete.step = 'success'; })
            .catch(() => { this.confirmDelete.open = false; });
    },
    closeDeleteModal() {
        this.confirmDelete.open = false;
    },
}">
    <x-ui.data-table
        :headers="[
                ['key' => 'identityCard', 'label' => __('Identity card'), 'sortable' => true],
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
                ['key' => 'referenceWorkload', 'label' => __('Estimated workload'), 'sortable' => true],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['identityCard', 'name']"
        :initial-search="$search"
        :paginator="$teachers ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="1.3fr 2.2fr 1.3fr 1fr"
        :can-create="Auth::user()->can('create', \Src\Academic\Teacher\Domain\Entities\Teacher::class)"
        :can-search="Auth::user()->can('search', \Src\Academic\Teacher\Domain\Entities\Teacher::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\Academic\Teacher\Domain\Entities\Teacher::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\Academic\Teacher\Domain\Entities\Teacher::class)"
        :active-export-id="$activeExportId"
        :title="__('Teachers management')">

        @if ($tableMode === 'client')
        {{-- Client mode: Alpine renders from the in-browser `pageRows`
             collection; search/sort/pagination cost zero round-trips. --}}
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span x-text="row.identityCard"></span>
                <div class="name-cell">
                    <span class="name-avatar" x-text="row.name.charAt(0).toUpperCase()"></span>
                    <span class="name-text" x-text="row.name"></span>
                </div>
                <span class="metric-value" x-text="row.referenceWorkload"></span>
                <div class="actions-cell">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('teachers.edit')"
                        :can-delete="Auth::user()->hasPermissionTo('teachers.delete')"
                        edit-action="$wire.openEditModal(row.id)"
                        delete-id="row.id" />
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        @forelse ($teachers as $teacher)
        <div class="data-row" role="row">
            <span>{{ $teacher->identityCard() }}</span>
            <div class="name-cell">
                <span class="name-avatar">{{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($teacher->name(), 0, 1)) }}</span>
                <span class="name-text">{{ $teacher->name() }}</span>
            </div>
            <span class="metric-value">{{ number_format($teacher->referenceWorkload(), 2) }}</span>
            <div class="actions-cell">
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', \Src\Academic\Teacher\Domain\Entities\Teacher::class)"
                    :can-delete="Auth::user()->can('delete', \Src\Academic\Teacher\Domain\Entities\Teacher::class)"
                    edit-action="$wire.openEditModal({{ $teacher->id() }})"
                    delete-id="{{ $teacher->id() }}" />
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New teacher') : __('Edit teacher')">
        <div class="form-field">
            <label for="teacherIdentityCard">{{ __('Identity card') }}</label>
            <input type="text" id="teacherIdentityCard" wire:model="form.identityCard" placeholder="{{ __('E.g. 2-0789-0456') }}" class="{{ $errors->has('form.identityCard') ? 'has-error' : '' }}">
            @error('form.identityCard') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="teacherName">{{ __('Name') }}</label>
            <input type="text" id="teacherName" wire:model="form.name" placeholder="{{ __('E.g. Ana Rodríguez Mora') }}" class="{{ $errors->has('form.name') ? 'has-error' : '' }}">
            @error('form.name') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="teacherReferenceWorkload">{{ __('Estimated workload') }}</label>
            <input type="number" step="0.05" min="0.01" max="9.99" id="teacherReferenceWorkload" wire:model="form.referenceWorkload" class="{{ $errors->has('form.referenceWorkload') ? 'has-error' : '' }}">
            <span class="field-hint">{{ __('Reference full-time equivalent (1.00 = full journey).') }}</span>
            @error('form.referenceWorkload') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-delete-modal :success-text="__('The teacher has been deleted.')" />
</div>
