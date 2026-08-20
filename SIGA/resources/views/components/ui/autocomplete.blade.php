{{--
    Type-to-filter picker for a foreign key.

    Server-rendered on purpose: the suggestions come from the component's
    own use case (already policy-checked) and are filtered in PHP by
    InteractsWithAutocomplete, so the browser only ever receives the
    handful it is about to show. Alpine owns nothing but the open/closed
    state and the keyboard highlight.

    Degrades honestly: with JavaScript off the input and the suggestion
    list still render server-side, and every suggestion is a real
    wire:click, so the field is still usable — it just stays open.

    Props:
      id            input id, also ties the <label>
      label         visible text, already translated by the caller
      search        wire model holding the query   (e.g. 'teacherQuery')
      options       filtered [{value,label}] to show right now
      selectedLabel label of the current selection, '' when unassigned
      select        Livewire method called with the chosen value
      clear         Livewire method that unsets the selection
      placeholder   optional
      emptyLabel    what "no selection" reads as (INFRA-01 needs it legal)
      error         validation message for the underlying field, if any
--}}
@props([
    'id',
    'label',
    'search',
    'options' => [],
    'selectedLabel' => '',
    'select',
    'clear',
    'placeholder' => null,
    'emptyLabel' => null,
    'error' => null,
])

<div class="field"
     x-data="{
        open: false,
        highlighted: 0,
        count: {{ count($options) }},
        move(delta) {
            if (!this.open) { this.open = true; return; }
            this.highlighted = Math.max(0, Math.min(this.count - 1, this.highlighted + delta));
        },
        choose() {
            const el = this.$refs.list?.querySelectorAll('[data-option]')[this.highlighted];
            if (el) { el.click(); this.open = false; }
        },
     }"
     @click.outside="open = false"
     @keydown.escape.stop="open = false">

    <label for="{{ $id }}">{{ $label }}</label>

    <div class="autocomplete">
        <input
            type="text"
            id="{{ $id }}"
            autocomplete="off"
            role="combobox"
            aria-expanded="false"
            x-bind:aria-expanded="open ? 'true' : 'false'"
            aria-controls="{{ $id }}-list"
            placeholder="{{ $placeholder ?? __('Type to search') }}"
            wire:model.live.debounce.250ms="{{ $search }}"
            @focus="open = true; highlighted = 0"
            @keydown.arrow-down.prevent="move(1)"
            @keydown.arrow-up.prevent="move(-1)"
            @keydown.enter.prevent="choose()"
            class="{{ $error ? 'has-error' : '' }}">

        {{-- What is currently chosen, shown apart from the query so typing
             a new search never looks like it already changed the value. --}}
        <div class="autocomplete-current">
            @if ($selectedLabel !== '')
                <span class="autocomplete-chip">{{ $selectedLabel }}</span>
                <button type="button" class="autocomplete-clear"
                        wire:click="{{ $clear }}"
                        aria-label="{{ __('Clear selection') }}">&times;</button>
            @else
                <span class="autocomplete-chip is-empty">{{ $emptyLabel ?? __('Unassigned') }}</span>
            @endif
        </div>

        <ul class="autocomplete-list" id="{{ $id }}-list" role="listbox"
            x-ref="list" x-show="open" x-cloak wire:key="{{ $id }}-list">
            @forelse ($options as $index => $option)
                {{-- wire:key per item: the number of suggestions changes on
                     every keystroke, and Livewire needs a stable identity per
                     item to morph a list whose length moves. Standard
                     practice, not a fix for anything specific — the table
                     emptying behind this modal turned out to be the shared
                     data-table seeding its rows through x-data, and is fixed
                     there. --}}
                <li role="option"
                    wire:key="{{ $id }}-opt-{{ $option['value'] }}"
                    aria-selected="false"
                    x-bind:aria-selected="highlighted === {{ $index }} ? 'true' : 'false'"
                    x-bind:class="highlighted === {{ $index }} ? 'is-highlighted' : ''"
                    @mouseenter="highlighted = {{ $index }}">
                    <button type="button" data-option
                            wire:click="{{ $select }}('{{ $option['value'] }}')"
                            @click="open = false">{{ $option['label'] }}</button>
                </li>
            @empty
                <li class="autocomplete-empty" wire:key="{{ $id }}-opt-none">{{ __('No matches') }}</li>
            @endforelse
        </ul>
    </div>

    @if ($error)
        <span class="form-error">{{ $error }}</span>
    @endif
</div>
