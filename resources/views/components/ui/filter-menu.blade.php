@props([
    'name',
    'label',
    'options' => [],
    'selected' => null,
    'target' => null,
    'allLabel' => 'Todos',
    'align' => 'start',
])

@php
    abort_unless(in_array($align, ['start', 'end'], true), 500, "Alineación de menú no soportada: {$align}");

    $target ??= $name;
    $current = $selected !== null && $selected !== '' ? ($options[$selected] ?? $label) : $label;
@endphp

<div class="md-chip-menu @if ($align === 'end') md-chip-menu--end @endif" :class="{ 'open': openMenu === '{{ $name }}' }">
    <button type="button"
            {{ $attributes->class(['md-chip', 'md-chip-filter', 'selected' => filled($selected)]) }}
            @click="openMenu = openMenu === '{{ $name }}' ? null : '{{ $name }}'"
            :aria-expanded="openMenu === '{{ $name }}'"
            aria-haspopup="listbox">
        {{ $current }}
        <i class="bi bi-chevron-down md-chip-menu__arrow" aria-hidden="true"></i>
    </button>

    <div x-show="openMenu === '{{ $name }}'" x-md-surface.no-scroll-lock="openMenu === '{{ $name }}'" @md-surface-close.stop="openMenu = null" x-transition x-cloak class="md-chip-menu__dropdown" role="listbox" aria-label="{{ $label }}">
        <button type="button"
                class="md-chip-menu__item @if (blank($selected)) active @endif"
                role="option"
                aria-selected="{{ blank($selected) ? 'true' : 'false' }}"
                wire:click="$set('{{ $target }}', '')"
                @click="openMenu = null">{{ $allLabel }}</button>

        @foreach ($options as $value => $optionLabel)
            <button type="button"
                    class="md-chip-menu__item @if ((string) $selected === (string) $value) active @endif"
                    role="option"
                    aria-selected="{{ (string) $selected === (string) $value ? 'true' : 'false' }}"
                    wire:click="$set('{{ $target }}', '{{ $value }}')"
                    @click="openMenu = null">{{ $optionLabel }}</button>
        @endforeach
    </div>
</div>
