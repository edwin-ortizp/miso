@props(['findings' => []])
{{-- CHG-070: lista de hallazgos (Inicio y Estadísticas). --}}
@if ($findings)
    <ul class="insights-list">
        @foreach ($findings as $finding)
            <li class="insights-list__item">
                <span class="md-headline-small insights-list__value">{{ $finding['value'] }}</span>
                <span class="md-body-large">{{ $finding['text'] }}</span>
            </li>
        @endforeach
    </ul>
@else
    <p class="md-body-medium mb-0">Sigue registrando: con unas semanas más te mostraré qué patrones aparecen.</p>
@endif
