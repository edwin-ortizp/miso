@props([
    'label',
    'floating' => false,
])

@php
    abort_unless(filled($label), 500, 'Las acciones de fila deben declarar un nombre accesible.');
    abort_unless(isset($primary), 500, 'Las acciones de fila necesitan una acción principal (slot primary).');

    $primaryAttributes = $primary->attributes;
@endphp

{{--
    Acciones por fila en vistas de gestión.
    Escritorio: botón dividido (acción principal visible + ▾ con el resto).
    Card compacta o móvil: se colapsa a un único ⋮ que incluye también la acción principal.
    Las opciones se declaran con x-ui.menu-item y x-ui.menu-divider.
--}}
<div {{ $attributes->class(['md-row-actions']) }}
     @if ($floating)
     x-data="{
         open: false,
         placeMenu() {
             if (!this.open) return;
             const menu = this.$refs.menu;
             if (!menu.matches(':popover-open')) menu.showPopover();
             const trigger = this.$refs.trigger.getBoundingClientRect();
             const below = Math.max(0, innerHeight - trigger.bottom - 12);
             const above = Math.max(0, trigger.top - 12);
             const opensAbove = menu.scrollHeight > below && above > below;
             menu.style.setProperty('--md-menu-max-height', (opensAbove ? above : below) + 'px');
             const { width, height } = menu.getBoundingClientRect();
             menu.style.setProperty('--md-menu-left', Math.max(8, Math.min(trigger.right - width, innerWidth - width - 8)) + 'px');
             menu.style.setProperty('--md-menu-top', (opensAbove ? trigger.top - height - 4 : trigger.bottom + 4) + 'px');
         },
     }"
     x-effect="open ? placeMenu() : $refs.menu.hidePopover()"
     @resize.window="placeMenu()"
     @scroll.window.capture="placeMenu()"
     @else
     x-data="{ open: false }"
     @endif
     @click.outside="open = false"
     @keydown.escape.stop="if (open) { open = false; $refs.trigger.focus() }">
    <button type="button" {{ $primaryAttributes->class(['md-btn-outlined', 'md-row-actions__main']) }}>{{ $primary }}</button>
    <button type="button"
            x-ref="trigger"
            class="md-btn-outlined md-row-actions__more"
            aria-haspopup="menu"
            :aria-expanded="open.toString()"
            aria-label="{{ $label }}"
            title="{{ $label }}"
            @click.stop="open = !open">
        <i class="bi bi-chevron-down md-row-actions__chevron" aria-hidden="true"></i>
        <i class="bi bi-three-dots-vertical md-row-actions__dots" aria-hidden="true"></i>
    </button>
    <div @class(['md-menu__surface', 'md-menu__surface--floating' => $floating]) role="menu"
         @if ($floating)
             x-ref="menu" popover="manual" @click="open = false; $refs.trigger.focus()"
         @else
             x-cloak x-show="open" x-transition.opacity.duration.120ms @click="open = false"
         @endif>
        <button type="button" role="menuitem" {{ $primaryAttributes->except(['x-optimistic-toggle', 'class'])->class(['md-menu__item', 'md-row-actions__primary-item']) }}>
            <span>{{ $primary }}</span>
        </button>
        {{ $slot }}
    </div>
</div>
