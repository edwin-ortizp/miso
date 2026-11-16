{{-- Racha compartida con un amigo (CHG-122): días en que los dos cumplieron su racha personal. --}}
@props(['streak', 'name'])
@php
    $daysLabel = $streak['days'] === 1 ? '1 día' : $streak['days'].' días';
    $bestLabel = $streak['best'] === 1 ? '1 día' : $streak['best'].' días';
    $statusText = ['met' => 'cumplido por los dos', 'grace' => 'gracia usada', 'none' => 'sin cumplir', 'today' => 'hoy, en curso'];
    $weekday = fn (string $date) => ucfirst(mb_substr(\Carbon\Carbon::parse($date)->locale('es')->translatedFormat('D'), 0, 2));
@endphp
<section {{ $attributes->class(['game-streak']) }} x-data="{ sharedHelp: false }" aria-label="Racha compartida con {{ $name }}">
    <div class="game-streak__head">
        <p class="md-title-small mb-0">Tú y {{ $name }}</p>
        <x-ui.icon-action icon="bi-question-circle" label="Cómo funciona la racha compartida" @click="sharedHelp = true" />
    </div>
    <p class="game-streak__days" aria-label="Racha compartida de {{ $daysLabel }}"><i class="bi bi-fire" aria-hidden="true"></i>
        @if ($streak['days'] > 0){{ $daysLabel }} juntos @else Empiecen hoy @endif
    </p>
    @if ($streak['broken'])
        <p class="md-body-small mb-0">Retomen cuando quieran. Su mejor racha sigue: {{ $bestLabel }}.</p>
    @endif
    <ol class="game-streak__week" aria-label="Últimos 7 días de los dos">
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
        <span>Gracias: {{ $streak['grace_left'] }} de {{ $streak['grace_total'] }} este mes</span>
        <span>Mejor racha: {{ $bestLabel }}</span>
    </p>
    <x-ui.dialog standard state="sharedHelp" title="Cómo funciona la racha compartida" icon="bi-fire">
        <p class="mb-1 mt-2">Un día cuenta cuando los dos cumplieron su racha personal. Si uno no llega, se usa una gracia ({{ $streak['grace_total'] }} por mes). Si se corta, no se pierde nada: su mejor racha se conserva y el conteo vuelve a empezar.</p>
        <p class="mb-1 mt-2">Solo se comparten los días cumplidos, nunca qué hizo cada quien.</p>
    </x-ui.dialog>
</section>
