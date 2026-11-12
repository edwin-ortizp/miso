@props([
    'state',
    'title',
    'id' => null,
])

@php
    $popoverId = $id ?? 'popover-'.\Illuminate\Support\Str::slug($title);
@endphp

{{--
    Superficie anclada a su contenedor (que debe ser `position: relative`).
    El disparador declara `data-popover-trigger` para que su propio clic no la cierre.
--}}
{{-- En móvil (< 768 px) es una hoja inferior: scrim, indicador de arrastre, contenido con scroll y footer fijo. --}}
<div class="md-popover__scrim" x-cloak x-show="{{ $state }}" x-transition.opacity.duration.120ms @click="{{ $state }} = false" aria-hidden="true"></div>
<div {{ $attributes->class(['md-popover']) }}
     id="{{ $popoverId }}"
     role="dialog"
     aria-labelledby="{{ $popoverId }}-title"
     x-cloak
     x-show="{{ $state }}"
     x-transition.opacity.duration.120ms
     @click.outside="if (! $event.target.closest('[data-popover-trigger]')) {{ $state }} = false"
     @keydown.escape.stop="{{ $state }} = false"
     x-data="{ dragStart: null, dragY: 0 }"
     :style="dragY > 0 ? `transform: translateY(${dragY}px); transition: none` : ''"
     @pointermove.window="if (dragStart !== null) dragY = Math.max(0, $event.clientY - dragStart)"
     @pointerup.window="if (dragStart !== null) { if (dragY > 80) {{ $state }} = false; dragStart = null; dragY = 0 }">
    <span class="md-popover__handle" aria-hidden="true"
          @pointerdown="if (matchMedia('(max-width: 767.98px)').matches) { dragStart = $event.clientY; dragY = 0 }"></span>
    <header class="md-popover__head" role="presentation" @pointerdown="if (matchMedia('(max-width: 767.98px)').matches && ! $event.target.closest('button')) { dragStart = $event.clientY; dragY = 0 }">
        <h3 id="{{ $popoverId }}-title" class="md-popover__title">{{ $title }}</h3>
        <button type="button" class="md-btn-icon md-btn--sm md-popover__close" aria-label="Cerrar {{ \Illuminate\Support\Str::lower($title) }}" title="Cerrar" @click="{{ $state }} = false">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </header>
    <div class="md-popover__body">{{ $slot }}</div>
    @isset($actions)
        <footer class="md-popover__actions">{{ $actions }}</footer>
    @endisset
</div>
