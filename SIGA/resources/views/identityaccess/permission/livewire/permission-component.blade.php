<div>
    <div class="max-w-6xl mx-auto">
        <h1 class="text-lg font-semibold text-slate-700 mb-4 flex items-center gap-2">
            <span class="text-[#1a3868]">&#8635;</span> {{ __('Permissions') }}
        </h1>

        <x-ui.data-table
            :headers="[
                ['key' => 'module', 'label' => __('Module'), 'sortable' => true],
                ['key' => 'action', 'label' => __('Action'), 'sortable' => true],
                ['key' => 'name', 'label' => __('Name'), 'sortable' => false],
            ]"
            :paginator="$permissions"
            :sort-key="$sortKey"
            :sort-dir="$sortDir"
            :can-create="Auth::user()->can('create', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class)"
            :can-export="Auth::user()->can('exportPdf', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class) || Auth::user()->can('exportExcel', \Src\IdentityAccess\Permission\Domain\Entities\Permission::class)"
            :title="__('Permissions')">
            @forelse ($permissions as $permission)
            <tr class="border-b border-slate-100 hover:bg-slate-100">
                <td class="px-4 py-3">
                    <span class="text-[11px] font-semibold px-2 py-1 rounded" style="background:#e2e8f2;color:#1a3868">{{ $permission->module() }}</span>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ $permission->action() }}</td>
                <td class="px-4 py-3 text-slate-500 font-mono text-xs">{{ $permission->name() }}</td>
                <td class="px-4 py-3">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->can('update', $permission)"
                        :can-delete="Auth::user()->can('delete', $permission)"
                        edit-action="openEditModal({{ $permission->id() }})"
                        delete-action="delete({{ $permission->id() }})" />
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="px-4 py-6 text-center text-slate-500">{{ __('No records found') }}</td>
            </tr>
            @endforelse
        </x-ui.data-table>
    </div>
</div>