<div>
    <div class="max-w-6xl mx-auto">
        <h1 class="text-lg font-semibold text-slate-700 mb-4 flex items-center gap-2">
            <span class="text-[#1a3868]">&#8635;</span> {{ __('Roles') }}
        </h1>

        <x-ui.data-table
            :headers="[
                ['key' => 'name', 'label' => __('Name'), 'sortable' => true],
                ['key' => 'permissions', 'label' => __('Permissions'), 'sortable' => false],
                ['key' => 'type', 'label' => __('Type'), 'sortable' => false],
            ]"
            :paginator="$roles"
            :sort-key="$sortKey"
            :sort-dir="$sortDir"
            :can-create="Auth::user()->can('create', \Src\IdentityAccess\Role\Domain\Entities\Role::class)"
            :can-export="Auth::user()->can('exportPdf', \Src\IdentityAccess\Role\Domain\Entities\Role::class) || Auth::user()->can('exportExcel', \Src\IdentityAccess\Role\Domain\Entities\Role::class)"
            :title="__('Roles')">
            @forelse ($roles as $role)
            <tr class="border-b border-slate-100 hover:bg-slate-100">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-full bg-[#1a3868] text-white text-[10px] font-semibold flex items-center justify-center flex-shrink-0">
                            {{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($role->name(), 0, 1)) }}
                        </span>
                        <span class="text-[#1a3868]">{{ $role->name() }}</span>
                    </div>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ count($role->permissions()) }} {{ __('permissions') }}</td>
                <td class="px-4 py-3">
                    @if ($role->isProtected())
                    <span class="text-[11px] font-semibold px-2 py-1 rounded" style="background:#e2e8f2;color:#1a3868">{{ __('System') }}</span>
                    @else
                    <span class="text-[11px] font-semibold px-2 py-1 rounded" style="background:#e6f0e2;color:#4a8f3c">{{ __('Custom') }}</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    <x-ui.row-actions
                        :can-edit="Auth::user()->can('update', $role)"
                        :can-delete="Auth::user()->can('delete', $role) && ! $role->isProtected()"
                        edit-action="openEditModal({{ $role->id() }})"
                        delete-action="delete({{ $role->id() }})" />
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