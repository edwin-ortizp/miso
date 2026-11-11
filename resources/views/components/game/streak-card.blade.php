{{-- Racha de constancia en Colección → Balls (CHG-076): días, hoy, gracias, mejor racha y la semana. --}}
@props(['streak'])
@php
    $daysLabel = $streak['days'] === 1 ? '1 día' : $streak['days'].' días';
    $needsArea = ! $streak['met_today'] && $streak['today'] >= $streak['goal'] && $streak['today_areas'] < $streak['min_areas'];
    $left = max(0, $streak['goal'] - $streak['today']);
    $statusText = ['met' => 'cumplido', 'grace' => 'gracia usada', 'none' => 'sin actividad', 'today' => 'hoy, en curso'];
    $weekday = fn (string $date) => ucfirst(mb_substr(\Carbon\Carbon::parse($date)->locale('es')->translatedFormat('D'), 0, 2));
@endphp
<section {{ $attributes->class(['game-streak', 'game-adventure-panel']) }} aria-labelledby="game-streak-title" x-data="{ streakHelp: false }">
    <div class="game-streak__head">
        <div class="game-streak__identity"><span class="game-streak__flame" aria-hidden="true"><i class="bi bi-fire"></i></span><div><h2 class="md-title-medium mb-0" id="game-streak-title">Tu racha</h2><p class="game-adventure-panel__eyebrow">Un día a la vez</p></div></div>
        <x-ui.icon-action icon="bi-question-circle" label="Cómo funciona la racha" @click="streakHelp = true" />
    </div>
    <p class="game-streak__days" aria-label="Racha de {{ $daysLabel }}"><i class="bi bi-fire" aria-hidden="true"></i> {{ $daysLabel }}</p>

    <p class="md-body-medium mb-0">
        @if ($streak['met_today'])
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Hoy ya está: {{ $streak['today'] }} de {{ $streak['goal'] }} acciones. Mañana sigue.
        @elseif ($streak['broken'])
            Tu mejor racha fue de {{ $streak['best'] }} días. Hoy empieza otra.
        @elseif ($needsArea)
            Hoy: {{ $streak['today'] }} de {{ $streak['goal'] }} acciones. Suma otra área para cumplir el día.
        @elseif ($streak['days'] === 0)
            Hoy empieza tu racha: te {{ $left === 1 ? 'falta 1 acción' : 'faltan '.$left.' acciones' }}.
        @else
            Hoy: {{ $streak['today'] }} de {{ $streak['goal'] }} acciones
        @endif
    </p>
    <x-ui.progress :value="min($streak['today'], $streak['goal'])" :max="$streak['goal']" tone="warning" label="Acciones de hoy"
                   :value-text="$streak['today'].' de '.$streak['goal'].' acciones'" />

    <ol class="game-streak__week" aria-label="Últimos 7 días">
        @foreach ($streak['last7'] as $day)
            <li class="game-streak__day game-streak__day--{{ $day['status'] }}">
                <span class="game-streak__dot" aria-hidden="true">
                    @if ($day['status'] === 'met')<i class="bi bi-check-lg"></i>
                    @elseif ($day['status'] === 'grace')<i class="bi bi-shield-check"></i>
                    @elseif ($day['status'] === 'today')<i class="bi bi-circle"></i>
                    @else<i class="bi bi-dash"></i>@endif
                </span>
                <span aria-hidden="true">{{ $weekday($day['date']) }}</span>
                <span class="visually-hidden">{{ \Carbon\Carbon::parse($day['date'])->locale('es')->translatedFormat('l j') }}: {{ $statusText[$day['status']] }}</span>
            </li>
        @endforeach
    </ol>

    <p class="game-streak__meta md-body-small game-muted">
        <span>Gracia: {{ $streak['grace_left'] }} de {{ $streak['grace_total'] }} en {{ $streak['month'] }}</span>
        <span>Tu mejor racha: {{ $streak['best'] === 1 ? '1 día' : $streak['best'].' días' }}</span>
    </p>

    <x-ui.dialog standard state="streakHelp" title="Cómo funciona la racha" icon="bi-fire">
        <p class="mb-1 mt-2">Cada día necesitas {{ $streak['goal'] }} acciones en al menos {{ $streak['min_areas'] }} áreas distintas. Si un día no llegas, se usa una gracia ({{ $streak['grace_total'] }} por mes).</p>
        <a href="{{ route('game.settings') }}#racha" wire:navigate>Cambiar el número de acciones</a>
    </x-ui.dialog>
</section>
