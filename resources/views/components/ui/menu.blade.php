@props([
    'label' => 'Más opciones',
    'icon' => 'bi-three-dots-vertical',
    'align' => 'end',
    'size' => 'md',
])

@php
    abort_unless(in_array($align, ['start', 'end'], true), 500, "Alineación de menú no soportada: {$align}");
    abort_unless(in_array($size, ['sm', 'md'], true), 500, "Tamaño de menú no soportado: {$size}");
    abort_unless(filled($label), 500, 'Un menú debe declarar un nombre accesible.');
@endphp

{{-- Menú de acciones secundarias (⋮). Las acciones se declaran con x-ui.menu-item. --}}
<div {{ $attributes->class(['md-menu', 'md-menu--start' => $align === 'start']) }}
     x-data="{ open: false }"
     @click.outside="open = false"
     @keydown.escape.stop="if (open) { open = false; $refs.trigger.focus() }">
    <button type="button"
            x-ref="trigger"
            class="md-btn-icon md-menu__trigger @if ($size === 'sm') md-btn--sm @endif"
            aria-haspopup="menu"
            :aria-expanded="open.toString()"
            aria-label="{{ $label }}"
            title="{{ $label }}"
            @click="open = !open">
        <i class="bi {{ $icon }}" aria-hidden="true"></i>
    </button>
    <div class="md-menu__surface" role="menu" x-cloak x-show="open" x-md-surface.no-scroll-lock="open" @md-surface-close.stop="open = false; $refs.trigger.focus()" x-transition.opacity.duration.120ms @click="open = false">
        {{ $slot }}
    </div>
</div>
