@props(['name', 'selected' => '', 'preview' => 'Vista previa', 'icon' => null])
@php
    $fieldId = \App\Support\Ui\FieldIdentity::for($name, 'Color');
    $message = $errors->first($name);
    $options = ['' => 'Predeterminado'] + \App\Support\Ui\CatalogColors::OPTIONS;
@endphp
<div class="md-text-field md-field md-catalog-color-select @if ($message) md-error @endif"
     x-data="catalogColorSelect($wire.entangle(@js($name)).live, @js(array_keys($options)))"
     x-on:pointerdown.window.capture="if (open && ! $el.contains($event.target)) close()"
     x-on:focusout="$nextTick(() => { if (! $el.contains(document.activeElement)) close() })"
     x-on:keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); close() }"
     x-on:resize.window="if (open) position()"
     x-on:scroll.window.capture="if (open) position()">
    <button type="button" id="{{ $fieldId }}" x-ref="trigger" role="combobox"
            class="md-field__control md-catalog-color-select__trigger"
            aria-haspopup="listbox" aria-controls="{{ $fieldId }}-list"
            aria-labelledby="{{ $fieldId }}-label {{ $fieldId }}-value"
            x-bind:aria-expanded="open.toString()"
            x-bind:aria-activedescendant="open ? '{{ $fieldId }}-option-' + active : null"
            @if ($message) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif
            x-on:click="toggle()" x-on:keydown.arrow-down.prevent="move(1)"
            x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.home.prevent="activate(0)"
            x-on:keydown.end.prevent="activate(values.length - 1)"
            x-on:keydown.enter.prevent="if (open) choose(values[active]); else show()"
            x-on:keydown.space.prevent="if (open) choose(values[active]); else show()"
            x-on:keydown.tab="close()">
        <span class="md-chip-tonal md-catalog-chip" x-bind:class="'md-catalog-chip--' + (value || 'accent')">
            @if ($icon)<i class="bi {{ $icon }}" aria-hidden="true"></i>@endif
            <span>{{ $preview ?: 'Vista previa' }}</span>
        </span>
        <span id="{{ $fieldId }}-value" class="md-catalog-color-select__value" x-text="labels[value || '']">{{ $options[$selected ?: ''] ?? 'Predeterminado' }}</span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </button>
    <label id="{{ $fieldId }}-label" for="{{ $fieldId }}">Color</label>
    <ul id="{{ $fieldId }}-list" x-ref="list" popover="manual" role="listbox" aria-label="Color"
        class="md-catalog-color-select__list" data-labels="{{ json_encode($options) }}"
        x-on:pointerdown.prevent>
        @foreach ($options as $optionValue => $optionLabel)
            <li id="{{ $fieldId }}-option-{{ $loop->index }}" role="option" wire:key="{{ $fieldId }}-{{ $loop->index }}"
                class="md-catalog-color-select__option"
                x-bind:class="{ 'is-active': active === {{ $loop->index }} }"
                x-bind:aria-selected="String(value || '') === @js((string) $optionValue)"
                x-on:click="choose(@js((string) $optionValue))">
                <span>{{ $optionLabel }}</span>
                <x-ui.catalog-chip :color="$optionValue ?: null" tone="accent" :icon="$icon">{{ $preview ?: 'Vista previa' }}</x-ui.catalog-chip>
            </li>
        @endforeach
    </ul>
    @if ($message)<p id="{{ $fieldId }}-error" class="md-supporting-text" role="alert">{{ $message }}</p>@endif
</div>
