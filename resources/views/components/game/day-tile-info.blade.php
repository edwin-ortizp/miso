{{-- Ayuda ⓘ de un mosaico de «Tu día» (CHG-089). El contenedor declara x-data="{ infoOpen: false }".
     Se abre al pasar el mouse (dispositivos con hover), con un toque o con Enter/Espacio; se cierra con Esc o fuera.
     Se teletransporta al body porque el hero del Inicio recorta lo que sobresale (overflow: hidden). --}}
@props(['id', 'title'])
<div class="game-day-tile__info"
     x-data="{ pos: {}, place() { const r = $refs.btn.getBoundingClientRect(); this.pos = { top: r.bottom + 4, right: Math.max(8, window.innerWidth - r.right) } } }"
     @mouseenter="if (matchMedia('(hover: hover)').matches) { place(); infoOpen = true }"
     @mouseleave="if (matchMedia('(hover: hover)').matches) infoOpen = false"
     @resize.window="infoOpen && place()" @scroll.window="infoOpen && place()">
    <button type="button" x-ref="btn" class="md-btn-icon md-btn--sm game-day-tile__info-btn" data-popover-trigger
            aria-label="{{ $title }}" :aria-expanded="infoOpen.toString()" aria-controls="{{ $id }}"
            @click="place(); infoOpen = ! infoOpen">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
    </button>
    <template x-teleport="body">
        <div class="game-day-tile__anchor" :style="`top: ${pos.top}px; right: ${pos.right}px`">
            <x-ui.popover state="infoOpen" :title="$title" :id="$id" class="game-day-tile__popover">
                <p class="md-body-medium mb-0">{{ $slot }}</p>
            </x-ui.popover>
        </div>
    </template>
</div>
