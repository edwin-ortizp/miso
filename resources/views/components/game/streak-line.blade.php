{{-- Racha de constancia (CHG-076) como mosaico de «Tu día» en el hero del Inicio (CHG-081). El detalle vive en Balls. --}}
@props(['streak'])
@php
    $daysLabel = $streak['days'] === 1 ? '1 día' : $streak['days'].' días';
    $needsArea = ! $streak['met_today'] && $streak['today'] >= $streak['goal'] && $streak['today_areas'] < $streak['min_areas'];
    $left = max(0, $streak['goal'] - $streak['today']);
    // CHG-070: sin racha pero con acciones hoy, se dice cuánto falta en vez de «Empieza hoy».
    $starting = $streak['days'] === 0 && $streak['today'] > 0 && ! $streak['met_today'] && ! $needsArea;
    $headline = match (true) {
        $streak['days'] > 0 => $daysLabel,
        $starting => 'A '.($left === 1 ? '1 acción' : $left.' acciones').' de tu racha',
        default => 'Empieza hoy',
    };
    $status = match (true) {
        $streak['met_today'] => 'Hoy ya está: '.$streak['today'].' de '.$streak['goal'].' acciones.',
        $needsArea => 'Hoy '.$streak['today'].' de '.$streak['goal'].' · suma otra área.',
        $starting => 'Llevas '.$streak['today'].' de '.$streak['goal'].' hoy. Aún estás a tiempo.',
        $streak['broken'] => 'Tu mejor racha fue de '.$streak['best'].' días. Hoy empieza otra.',
        default => 'Hoy '.$streak['today'].' de '.$streak['goal'].' acciones · te '.($left === 1 ? 'falta 1' : 'faltan '.$left).'.',
    };
@endphp
<article {{ $attributes->class(['game-day-tile', 'game-day-tile--done' => $streak['met_today']]) }} aria-labelledby="game-day-streak"
         x-data="{ infoOpen: false }">
    <x-game.day-tile-info id="game-day-streak-info" title="Cómo funciona la racha">
        Tu racha sube cuando haces {{ $streak['goal'] }} {{ $streak['goal'] === 1 ? 'acción' : 'acciones' }} en al menos {{ $streak['min_areas'] }} áreas distintas (tareas, hábitos, foco…).
    </x-game.day-tile-info>
    <a href="{{ route('game.balls') }}" wire:navigate class="game-day-tile__title md-label-large" id="game-day-streak">
        <i class="bi bi-fire" aria-hidden="true"></i> Racha <i class="bi bi-chevron-right game-day-tile__more" aria-hidden="true"></i>
    </a>
    <p class="game-day-tile__value md-headline-small">{{ $headline }}</p>
    <p class="game-day-tile__text md-body-medium">{{ $status }}</p>
    <x-ui.progress class="game-day-tile__bar" :value="min($streak['today'], $streak['goal'])" :max="$streak['goal']" label="Acciones de hoy para la racha" />
</article>
