{{--
    Navegador de periodo del shell del módulo: anterior · volver al actual · rótulo · siguiente.
    Por defecto navega por días; para semanas o meses se pasan las acciones, sus
    nombres accesibles y el rótulo ya formado en `label` (CHG-095).
--}}
@props([
    'date' => null,
    'previous' => 'previousDay',
    'next' => 'nextDay',
    'today' => 'today',
    'format' => 'D d M Y',
    'previousLabel' => 'Día anterior',
    'nextLabel' => 'Día siguiente',
    'todayLabel' => 'Hoy',
    'label' => null,
    'navLabel' => 'Navegación por fecha',
])

<div {{ $attributes->class(['md-date-navigator']) }} role="group" aria-label="{{ $navLabel }}">
    <button type="button" wire:click="{{ $previous }}" class="md-btn-icon" title="{{ $previousLabel }}" aria-label="{{ $previousLabel }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
    <button type="button" wire:click="{{ $today }}" class="md-date-navigator__today">{{ $todayLabel }}</button>
    @if ($label !== null)
        <span class="md-date-navigator__label">{{ $label }}</span>
    @else
        <time datetime="{{ \Carbon\Carbon::parse($date)->toDateString() }}" class="md-date-navigator__label">
            {{ \Carbon\Carbon::parse($date)->translatedFormat($format) }}
        </time>
    @endif
    <button type="button" wire:click="{{ $next }}" class="md-btn-icon" title="{{ $nextLabel }}" aria-label="{{ $nextLabel }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
</div>
