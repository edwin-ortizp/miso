@props([
    'monthData',
    'goal' => null,
    'format',
    'route',
    'label' => 'Cumplimiento de la meta diaria',
])

{{--
    Calendario del mes de una meta diaria (hidratación, minutos activos…).
    Cada día es un recipiente que se llena desde abajo según su cumplimiento: por encima del 100 % se ve lleno.
    Hoy lleva borde de acento; los días sin registros o futuros quedan vacíos y neutros.
    `monthData` viene de App\Support\GoalCalendar::month(); `format` es un closure que muestra un valor con su unidad.
--}}
<div class="goal-fill-calendar" role="grid" aria-label="{{ $label }} · {{ $monthData['label'] }}">
    <div class="goal-fill-calendar__weekdays" role="row">
        @foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)<span role="columnheader">{{ $weekday }}</span>@endforeach
    </div>
    @foreach ($monthData['weeks'] as $week)
        <div class="goal-fill-calendar__week" role="row">
            @foreach ($week as $day)
                @php
                    $isEmpty = ! $day['has_data'] || $day['future'];
                    $dateLabel = $day['date']->translatedFormat('j \d\e F');
                    $dayLabel = $isEmpty
                        ? $dateLabel.': sin registros'
                        : $dateLabel.': '.$format($day['total']).' de '.$format($day['goal'] ?? $goal).' ('.$day['raw_percentage'].' %)';
                @endphp
                <a href="{{ route($route, ['date' => $day['date']->toDateString()]) }}" wire:navigate
                   @class([
                       'goal-fill-day',
                       'is-outside' => ! $day['in_month'],
                       'is-today' => $day['today'],
                       'is-selected' => $day['selected'],
                       'is-empty' => $isEmpty,
                       'is-high' => ! $isEmpty && $day['raw_percentage'] >= 60,
                   ])
                   style="--md-progress-value: {{ $isEmpty ? 0 : min($day['raw_percentage'], 100) }}%"
                   role="gridcell" title="{{ $dayLabel }}" aria-label="{{ $day['today'] ? 'Hoy, ' : '' }}{{ $dayLabel }}">
                    <span>{{ $day['date']->day }}</span>
                </a>
            @endforeach
        </div>
    @endforeach
</div>
