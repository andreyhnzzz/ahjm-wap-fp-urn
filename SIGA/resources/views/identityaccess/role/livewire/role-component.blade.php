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
                ['key' => 'permissionsCount', 'label' => __('Permissions'), 'sortable' => false],
                ['key' => 'protected', 'label' => __('Type'), 'sortable' => false],
            ]"
        :mode="$tableMode"
        :rows="$rows ?? []"
        :searchable="['name']"
        :paginator="$roles ?? null"
        :sort-key="$sortKey"
        :sort-dir="$sortDir"
        :per-page="$perPage"
        table-cols="2.2fr 1.2fr 1fr 1fr"
        :can-create="Auth::user()->can('create', \Src\IdentityAccess\Role\Domain\Entities\Role::class)"
        :can-export="Auth::user()->can('exportPdf', \Src\IdentityAccess\Role\Domain\Entities\Role::class) || Auth::user()->can('exportExcel', \Src\IdentityAccess\Role\Domain\Entities\Role::class)"
        :title="__('Roles management')">

        @if ($tableMode === 'client')
        {{-- Client mode: Alpine renders rows from the in-browser `pageRows`
                     collection (search/sort/pagination already resolved with zero
                     server round-trips). See resources/js/data-table.js. --}}
        <template x-for="row in pageRows" :key="row.id">
            <div class="data-row" role="row">
                <div class="name-cell">
                    <span class="name-avatar" x-text="row.name.charAt(0).toUpperCase()"></span>
                    <span class="name-text" x-text="row.name"></span>
                </div>
                <span x-text="row.permissionsCount + ' {{ __('permissions') }}'"></span>
                <span>
                    <span class="status-badge system" x-show="row.protected">{{ __('System') }}</span>
                    <span class="status-badge custom" x-show="!row.protected">{{ __('Custom') }}</span>
                </span>
                <div class="actions-cell">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->hasPermissionTo('roles.edit')"
                        :can-delete="Auth::user()->hasPermissionTo('roles.delete')"
                        edit-action="$wire.openEditModal(row.id)"
                        delete-visible="!row.protected"
                        delete-id="row.id" />
                </div>
            </div>
        </template>
        <div class="empty-row" x-show="pageRows.length === 0">{{ __('No records found') }}</div>
        @else
        {{-- Server mode: unchanged Livewire-driven pagination, preserved for
                     any future catalog too large to ship to the client at once. --}}
        @forelse ($roles as $role)
        <div class="data-row" role="row">
            <div class="name-cell">
                <span class="name-avatar">{{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($role->name(), 0, 1)) }}</span>
                <span class="name-text">{{ $role->name() }}</span>
            </div>
            <span>{{ count($role->permissions()) }} {{ __('permissions') }}</span>
            <span>
                @if ($role->isProtected())
                <span class="status-badge system">{{ __('System') }}</span>
                @else
                <span class="status-badge custom">{{ __('Custom') }}</span>
                @endif
            </span>
            <div class="actions-cell">
                <x-ui.row-actions
                    :can-edit="Auth::user()->can('update', $role)"
                    :can-delete="Auth::user()->can('delete', $role) && ! $role->isProtected()"
                    edit-action="$wire.openEditModal({{ $role->id() }})"
                    delete-id="{{ $role->id() }}" />
            </div>
        </div>
        @empty
        <div class="empty-row">{{ __('No records found') }}</div>
        @endforelse
        @endif
    </x-ui.data-table>

    <x-ui.modal :show="$showModal" :title="$editingId === null ? __('New role') : __('Edit role')">
        <div class="form-field">
            <label for="roleName">{{ __('Role name') }}</label>
            <input type="text" id="roleName" wire:model="form.name" placeholder="{{ __('E.g. Content editor') }}" class="{{ $errors->has('form.name') ? 'has-error' : '' }}">
            @error('form.name') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field" x-data="{
            permSearch: '',
            catalog: @js(collect($permissionCatalog)->values()),
            get filteredNames() {
                const q = this.permSearch.trim().toLowerCase();
                if (q === '') return this.catalog.map(p => p.name);
                return this.catalog.filter(p => p.label.toLowerCase().includes(q)).map(p => p.name);
            },
        }">
            <label>{{ __('Permissions') }}</label>
            <input type="text" x-model.debounce.100ms="permSearch" placeholder="{{ __('Search permission...') }}">
            <div class="permissions-list">
                @forelse ($permissionCatalog as $permission)
                <label class="permission-item" x-show="filteredNames.includes(@js($permission['name']))">
                    <input type="checkbox" wire:model="form.permissions" value="{{ $permission['name'] }}">
                    <span>{{ $permission['label'] }}</span>
                </label>
                @empty
                <div class="permission-empty">{{ __('No permissions available') }}</div>
                @endforelse
                @if (count($permissionCatalog) > 0)
                <div class="permission-empty" x-show="filteredNames.length === 0">{{ __('No results') }}</div>
                @endif
            </div>
            @error('form.permissions') <span class="form-error">{{ $message }}</span> @enderror
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="closeModal">{{ __('Cancel') }}</button>
            <button type="button" class="btn btn-primary" wire:click="save">{{ __('Confirm') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.confirm-delete-modal :success-text="__('The role has been deleted.')" />
</div>