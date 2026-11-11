{{-- Racha de constancia (CHG-076) como mosaico de «Tu día» en el hero del Inicio (CHG-081). El detalle vive en Balls. --}}
@props(['streak'])
@php
    $daysLabel = $streak['days'] === 1 ? '1 día' : $streak['days'].' días';
    $needsArea = ! $streak['met_today'] && $streak['today'] >= $streak['goal'] && $streak['today_areas'] < $streak['min_areas'];
    $left = max(0, $streak['goal'] - $streak['today']);
    $headline = $streak['days'] > 0 ? $daysLabel : 'Empieza hoy';
    $status = match (true) {
        $streak['met_today'] => 'Hoy ya está: '.$streak['today'].' de '.$streak['goal'].' acciones.',
        $needsArea => 'Hoy '.$streak['today'].' de '.$streak['goal'].' · suma otra área.',
        $streak['broken'] => 'Tu mejor racha fue de '.$streak['best'].' días. Hoy empieza otra.',
        default => 'Hoy '.$streak['today'].' de '.$streak['goal'].' acciones · te '.($left === 1 ? 'falta 1' : 'faltan '.$left).'.',
    };
@endphp
<article {{ $attributes->class(['game-day-tile', 'game-day-tile--done' => $streak['met_today']]) }} aria-labelledby="game-day-streak">
    <a href="{{ route('game.balls') }}" wire:navigate class="game-day-tile__title md-label-large" id="game-day-streak">
        <i class="bi bi-fire" aria-hidden="true"></i> Racha <i class="bi bi-chevron-right game-day-tile__more" aria-hidden="true"></i>
    </a>
    <p class="game-day-tile__value md-headline-small">{{ $headline }}</p>
    <p class="game-day-tile__text md-body-medium">{{ $status }}</p>
    <x-ui.progress class="game-day-tile__bar" :value="min($streak['today'], $streak['goal'])" :max="$streak['goal']" label="Acciones de hoy para la racha" />
</article>
