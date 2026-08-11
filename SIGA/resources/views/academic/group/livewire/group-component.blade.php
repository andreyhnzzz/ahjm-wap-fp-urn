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
    {{--
        Unlike the Role/Teacher/Classroom tables, server mode here also
        receives already-projected row arrays rather than Domain Entities:
        a Group references its teacher and classroom by id, and resolving
        those ids into names is the component's job (GroupComponent::toRow),
        not something a Blade template should be doing per row.
    --}}
    <x-ui.data-table
        :headers="[
                ['key' => 'code', 'label' => __('Group code'), 'sortable' => true],
                ['key' => 'courseCode', 'label' => __('Course code'), 'sortable' => true],
                ['key' => 'term', 'label' => __('Term'), 'sortable' => true],
                ['key' => 'teacher', 'label' => __('Teacher'), 'sortable' => false],
                ['key' => 'classroom', 'label' => __('Classroom'), 'sortable' => false],
                ['key' => 'estimatedEnrollment', 'label' => __('Enrollment'), 'sortable' => true],
                ['key' => 'status', 'label' => __('Status'), 'sortable' => false],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['code', 'courseCode', 'term', 'teacher', 'classroom']"
        :initial-search="$search"
        :paginator="$groups ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="1.5fr 1.1fr 1.1fr 1.8fr 1.2fr 1fr 1.1fr 0.9fr"
        :can-create="Auth::user()->can('create', \Src\Academic\Group\Domain\Entities\Group::class)"
        :can-search="Auth::user()->can('search', \Src\Academic\Group\Domain\Entities\Group::class)"
        :can-export-pdf="Auth::user()->can('exportPdf', \Src\Academic\Group\Domain\Entities\Group::class)"
        :can-export-excel="Auth::user()->can('exportExcel', \Src\Academic\Group\Domain\Entities\Group::class)"
        :title="__('Groups management')">

        @if ($tableMode === 'client')
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <span class="cell-strong" x-text="row.code"></span>
                <span x-text="row.courseCode"></span>
                <span x-text="row.term"></span>
                <span :class="row.hasTeacher ? '' : 'cell-unassigned'" x-text="row.teacher"></span>
                <span :class="row.hasClassroom ? '' : 'cell-unassigned'" x-text="row.classroom"></span>
                <span class="metric-value" x-text="row.estimatedEnrollment"></span>
                <span>
                    <span class="status-badge" :class="row.statusVariant" x-text="row.status"></span>
                </span>
                <div class="actions-cell">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('groups.edit')"
                        :can-delete="Auth::user()->hasPermissionTo('groups.delete')"
                        edit-action="$wire.openEditModal(row.id)"
                        delete-id="row.id" />
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        @forelse ($groups as $group)
        <div class="data-row" role="row">
            <span class="cell-strong">{{ $group['code'] }}</span>
            <span>{{ $group['courseCode'] }}</span>
            <span>{{ $group['term'] }}</span>
            <span class="{{ $group['hasTeacher'] ? '' : 'cell-unassigned' }}">{{ $group['teacher'] }}</span>
            <span class="{{ $group['hasClassroom'] ? '' : 'cell-unassigned' }}">{{ $group['classroom'] }}</span>
            <span class="metric-value">{{ $group['estimatedEnrollment'] }}</span>
            <span>
                <span class="status-badge {{ $group['statusVariant'] }}">{{ $group['status'] }}</span>
            </span>
            <div class="actions-cell">
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', \Src\Academic\Group\Domain\Entities\Group::class)"
                    :can-delete="Auth::user()->can('delete', \Src\Academic\Group\Domain\Entities\Group::class)"
                    edit-action="$wire.openEditModal({{ $group['id'] }})"
                    delete-id="{{ $group['id'] }}" />
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New group') : __('Edit group')" wide>
        <div class="form-grid">
            <div class="form-field">
                <label for="groupCode">{{ __('Group code') }}</label>
                <input type="text" id="groupCode" wire:model="form.code" placeholder="{{ __('E.g. ISW-521-G01') }}" class="{{ $errors->has('form.code') ? 'has-error' : '' }}">
                @error('form.code') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-field">
                <label for="groupCourseCode">{{ __('Course code') }}</label>
                <input type="text" id="groupCourseCode" wire:model="form.courseCode" placeholder="{{ __('E.g. ISW-521') }}" class="{{ $errors->has('form.courseCode') ? 'has-error' : '' }}">
                @error('form.courseCode') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-field">
                <label for="groupTerm">{{ __('Term') }}</label>
                <input type="text" id="groupTerm" wire:model="form.term" placeholder="{{ __('E.g. 2026-II') }}" class="{{ $errors->has('form.term') ? 'has-error' : '' }}">
                @error('form.term') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-field">
                <label for="groupEstimatedEnrollment">{{ __('Estimated enrollment') }}</label>
                <input type="number" min="0" max="500" step="1" id="groupEstimatedEnrollment" wire:model="form.estimatedEnrollment" class="{{ $errors->has('form.estimatedEnrollment') ? 'has-error' : '' }}">
                @error('form.estimatedEnrollment') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-field">
                <label for="groupTeacherId">{{ __('Teacher') }}</label>
                <select id="groupTeacherId" wire:model.live="form.teacherId" class="{{ $errors->has('form.teacherId') ? 'has-error' : '' }}">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($teacherOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @error('form.teacherId') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-field">
                <label for="groupAssignedWorkload">{{ __('Assigned workload') }}</label>
                <input type="number" step="0.05" min="0" max="9.99" id="groupAssignedWorkload" wire:model="form.assignedWorkload" @disabled($form->teacherId === '') class="{{ $errors->has('form.assignedWorkload') ? 'has-error' : '' }}">
                <span class="field-hint">{{ __('Workload this group represents for its teacher.') }}</span>
                @error('form.assignedWorkload') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-field">
                <label for="groupClassroomId">{{ __('Classroom') }}</label>
                <select id="groupClassroomId" wire:model="form.classroomId" class="{{ $errors->has('form.classroomId') ? 'has-error' : '' }}">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach ($classroomOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @error('form.classroomId') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-field">
                <label for="groupModality">{{ __('Modality') }}</label>
                <select id="groupModality" wire:model="form.modality" class="{{ $errors->has('form.modality') ? 'has-error' : '' }}">
                    @foreach ($modalityOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @error('form.modality') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-field">
                <label for="groupStatus">{{ __('Status') }}</label>
                <select id="groupStatus" wire:model="form.status" class="{{ $errors->has('form.status') ? 'has-error' : '' }}">
                    @foreach ($statusOptions as $option)
                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @error('form.status') <span class="form-error">{{ $message }}</span> @enderror
            </div>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-delete-modal :success-text="__('The group has been deleted.')" />
</div>
