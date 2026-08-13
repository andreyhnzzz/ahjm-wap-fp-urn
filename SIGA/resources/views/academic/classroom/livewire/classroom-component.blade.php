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
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
                ['key' => 'capacity', 'label' => __('Capacity'), 'sortable' => true],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['name']"
        :initial-search="$search"
        :paginator="$classrooms ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="2.4fr 1.4fr 1fr"
        :can-create="Auth::user()->can('create', \Src\Academic\Classroom\Domain\Entities\Classroom::class)"
        :can-search="Auth::user()->can('search', \Src\Academic\Classroom\Domain\Entities\Classroom::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\Academic\Classroom\Domain\Entities\Classroom::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\Academic\Classroom\Domain\Entities\Classroom::class)"
        :active-export-id="$activeExportId"
        :title="__('Classrooms management')">

        @if ($tableMode === 'client')
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <div class="name-cell">
                    <span class="name-avatar" x-text="row.name.charAt(0).toUpperCase()"></span>
                    <span class="name-text" x-text="row.name"></span>
                </div>
                <span>
                    <span class="module-badge" x-text="row.capacity + ' {{ __('seats') }}'"></span>
                </span>
                <div class="actions-cell">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('classrooms.edit')"
                        :can-delete="Auth::user()->hasPermissionTo('classrooms.delete')"
                        edit-action="$wire.openEditModal(row.id)"
                        delete-id="row.id" />
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        @forelse ($classrooms as $classroom)
        <div class="data-row" role="row">
            <div class="name-cell">
                <span class="name-avatar">{{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($classroom->name(), 0, 1)) }}</span>
                <span class="name-text">{{ $classroom->name() }}</span>
            </div>
            <span>
                <span class="module-badge">{{ $classroom->capacity() }} {{ __('seats') }}</span>
            </span>
            <div class="actions-cell">
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', \Src\Academic\Classroom\Domain\Entities\Classroom::class)"
                    :can-delete="Auth::user()->can('delete', \Src\Academic\Classroom\Domain\Entities\Classroom::class)"
                    edit-action="$wire.openEditModal({{ $classroom->id() }})"
                    delete-id="{{ $classroom->id() }}" />
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New classroom') : __('Edit classroom')">
        <div class="form-field">
            <label for="classroomName">{{ __('Name') }}</label>
            <input type="text" id="classroomName" wire:model="form.name" placeholder="{{ __('E.g. Aula 204') }}" class="{{ $errors->has('form.name') ? 'has-error' : '' }}">
            @error('form.name') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="classroomCapacity">{{ __('Capacity') }}</label>
            <input type="number" min="1" max="1000" step="1" id="classroomCapacity" wire:model="form.capacity" class="{{ $errors->has('form.capacity') ? 'has-error' : '' }}">
            <span class="field-hint">{{ __('Maximum number of students the room seats.') }}</span>
            @error('form.capacity') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-delete-modal :success-text="__('The classroom has been deleted.')" />
</div>
