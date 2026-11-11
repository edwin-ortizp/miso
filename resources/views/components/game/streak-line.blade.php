{{-- Racha de constancia en una línea (CHG-076): vive en el hero del Inicio y enlaza al detalle en Balls. --}}
@props(['streak'])
@php
    $daysLabel = $streak['days'] === 1 ? '1 día' : $streak['days'].' días';
    $needsArea = ! $streak['met_today'] && $streak['today'] >= $streak['goal'] && $streak['today_areas'] < $streak['min_areas'];
    $left = max(0, $streak['goal'] - $streak['today']);
    $text = match (true) {
        $streak['met_today'] => $daysLabel.' · Hoy ya está',
        $streak['broken'] => 'Tu mejor racha fue de '.$streak['best'].' días. Hoy empieza otra.',
        $streak['days'] === 0 => $needsArea ? 'Hoy empieza tu racha: suma otra área.' : 'Hoy empieza tu racha: te '.($left === 1 ? 'falta 1 acción' : 'faltan '.$left.' acciones').'.',
        default => $daysLabel.' · Hoy '.$streak['today'].' de '.$streak['goal'],
    };
@endphp
<a href="{{ route('game.balls') }}" wire:navigate {{ $attributes->class(['game-streak-line']) }}
   aria-label="Tu racha: {{ $daysLabel }}. Hoy {{ $streak['today'] }} de {{ $streak['goal'] }} acciones. Ver detalle.">
    <i class="bi bi-fire" aria-hidden="true"></i>
    <span>{{ $text }}</span>
    @if ($needsArea && $streak['days'] > 0)<span class="game-streak-line__muted">· suma otra área</span>@endif
</a>
