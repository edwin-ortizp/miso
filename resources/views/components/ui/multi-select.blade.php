@aware(['validationState' => null])
@props([
    'name',
    'live' => true,
    'label',
    'labelExpression' => null,
    'modelExpression' => null,
    'options' => [],
    'allLabel' => 'Todos',
    'id' => null,
    'icon' => null,
    'error' => null,
    'searchable' => null,
])

@php
    $fieldId = $id ?? \App\Support\Ui\FieldIdentity::for($name, $label);
    $message = $error ?? (isset($errors) ? $errors->first($name) : null);
    // Las opciones admiten grupos: `['Grupo' => [valor => etiqueta]]`.
    $groups = collect($options)->every(fn ($option) => is_array($option)) && $options !== [] ? $options : ['' => $options];
    $labels = collect($groups)->flatMap(fn ($group) => $group)->all();
    $searchable ??= count($labels) > 10;
@endphp

{{-- Selección múltiple con checkboxes, enlazada a una propiedad array de Livewire. --}}
<div {{ $attributes->class(['md-text-field', 'md-field', 'md-multi-select', 'md-field--icon' => $icon, 'md-error' => $message]) }}
     @if($message && $validationState) :class="{ 'md-error': {{ $validationState }} }" @endif
     x-data="{ open: false, above: false, search: '', @if($modelExpression) get selected() { return {{ $modelExpression }} }, set selected(value) { {{ $modelExpression }} = value }, @else selected: $wire.entangle('{{ $name }}'){{ $live ? '.live' : '' }}, @endif labels: @js($labels) }"
     @click.outside="open = false"
     @keydown.escape="if (open) { $event.stopPropagation(); open = false; $refs.trigger.focus() }">
    @if ($icon)<i class="bi {{ $icon }} md-field__icon" aria-hidden="true"></i>@endif
    <button type="button"
            id="{{ $fieldId }}"
            x-ref="trigger"
            class="md-field__control md-multi-select__trigger"
            aria-haspopup="dialog"
            :aria-expanded="open.toString()"
            @if ($message) aria-invalid="true" @if($validationState) :aria-invalid="{{ $validationState }} ? 'true' : null" @endif aria-describedby="{{ $fieldId }}-error" @endif
            @click="open = !open; if (open) { const rect = $refs.trigger.getBoundingClientRect(); above = innerHeight - rect.bottom < 280 && rect.top > innerHeight - rect.bottom }">
        <span class="md-multi-select__value"
              x-text="selected.length ? selected.slice(0, 2).map((value) => labels[value] ?? value).join(', ') + (selected.length > 2 ? ' +' + (selected.length - 2) : '') : @js($allLabel)">{{ $allLabel }}</span>
    </button>
    <label for="{{ $fieldId }}" @if($labelExpression) x-text="{{ $labelExpression }}" @endif>{{ $label }}</label>
    <div class="md-multi-select__panel" :class="{ 'md-multi-select__panel--above': above }" role="dialog" aria-label="{{ $label }}" x-cloak x-show="open" x-md-surface.no-scroll-lock="open" @md-surface-close.stop="open = false; $refs.trigger.focus()">
        @if ($searchable)
            <div class="md-multi-select__search" role="presentation"><label class="visually-hidden" for="{{ $fieldId }}-search">Buscar opciones</label><input id="{{ $fieldId }}-search" type="search" x-model="search" placeholder="Buscar opciones" autocomplete="off"></div>
        @endif
        @foreach ($groups as $groupLabel => $groupOptions)
            @if ($groupLabel !== '')
                <span class="md-multi-select__group" role="presentation">{{ $groupLabel }}</span>
            @endif
            @foreach ($groupOptions as $optionValue => $optionLabel)
                <span class="md-multi-select__option {{ str_contains($optionLabel, ' / ') ? 'md-multi-select__option--child' : '' }}" x-show="@js(mb_strtolower($optionLabel)).includes(search.toLocaleLowerCase())">
                    <input type="checkbox" id="{{ $fieldId }}-{{ $optionValue }}" value="{{ $optionValue }}" x-model="selected">
                    <span><label class="md-multi-select__option-label" for="{{ $fieldId }}-{{ $optionValue }}">{{ $optionLabel }}</label></span>
                </span>
            @endforeach
        @endforeach
    </div>
    @if ($message)
        <p id="{{ $fieldId }}-error" class="md-supporting-text" role="alert" @if($validationState) x-show="{{ $validationState }}" @endif>{{ $message }}</p>
    @endif
</div>
