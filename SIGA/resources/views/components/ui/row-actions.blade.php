@props([
'canView' => false,
'canEdit' => false,
'canDelete' => false,
'viewAction' => null,
'editAction' => null,
'deleteAction' => null,
])

<div class="flex items-center gap-2">
    @if ($canView && $viewAction)
    <button type="button" wire:click="{{ $viewAction }}" class="w-7 h-7 rounded-full bg-sky-100 text-sky-600 hover:bg-sky-200 flex items-center justify-center shadow-sm" title="{{ __('View') }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z" />
            <circle cx="12" cy="12" r="2.6" />
        </svg>
    </button>
    @endif

    @if ($canEdit && $editAction)
    <button type="button" wire:click="{{ $editAction }}" class="w-7 h-7 rounded-full bg-amber-100 text-amber-600 hover:bg-amber-200 flex items-center justify-center shadow-sm" title="{{ __('Edit') }}">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
        </svg>
    </button>
    @endif

    @if ($canDelete && $deleteAction)
    <button type="button" wire:click="{{ $deleteAction }}" wire:confirm="{{ __('Are you sure? This action cannot be undone.') }}" class="w-7 h-7 rounded-full bg-rose-100 text-rose-600 hover:bg-rose-200 flex items-center justify-center shadow-sm" title="{{ __('Delete') }}">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6h18" />
            <path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2" />
            <path d="M19 6l-.9 14a2 2 0 0 1-2 1.9H7.9a2 2 0 0 1-2-1.9L5 6" />
            <path d="M10 11v6M14 11v6" />
        </svg>
    </button>
    @endif
</div>