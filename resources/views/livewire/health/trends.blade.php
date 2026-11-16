@php
    use App\Models\SleepLog;
    use App\Support\HealthTrends;

    $fmt = fn ($n) => str_replace('.', ',', (string) $n);
@endphp

<x-module-shell module="health">
    <p class="md-body-medium" id="trends-notice">
        MiSo muestra la evolución de lo que registras. No diagnostica ni reemplaza a tu profesional de salud.
    </p>

    <x-ui.filter-bar label="Periodo">
        <x-slot:chips>
            @foreach ($periods as $value => $periodLabel)
                <x-ui.chip variant="filter" :selected="$selected === $value" wire:click="setDays('{{ $value }}')" wire:key="period-{{ $value }}">{{ $periodLabel }}</x-ui.chip>
            @endforeach
        </x-slot:chips>
    </x-ui.filter-bar>

    @if ($charts->isEmpty() && ! $sleep)
        <x-ui.state variant="empty" icon="bi-graph-up" title="Aún no hay datos para mostrar tendencias"
                    message="Registra una medición o tu sueño en sus pestañas." />
    @endif

    @if ($insight)
        <x-ui.section title="Sueño y energía" :level="2" id="trend-insight">
            <p class="md-body-large mb-0">{{ $insight['text'] }}</p>
            <p class="md-body-small mb-0">Según {{ $insight['days'] }} días con sueño y energía registrados.</p>
        </x-ui.section>
    @endif

    @foreach ($charts as $chart)
        @php
            $def = $chart['definition'];
            $sum = $chart['summary'];
            $hasSecond = isset($def['value2_label']);
            $band = $def['reference']['value'] ?? null;
            $decimals = in_array($chart['type'], ['creatinine', 'temperature', 'weight'], true) ? 1 : 0;
            $unit = $def['unit'];
            $range = $chart['type'] === 'blood_pressure' ? '90–120 / 60–80 '.$unit : ($band ? str_replace('.', ',', $band[0].'–'.$band[1]).' '.$unit : null);
            $averageText = $fmt($sum['average']).($hasSecond ? '/'.$fmt($sum['average2']) : '');
            $series = [['name' => $def['value_label'], 'key' => 'value']];
            if ($hasSecond) { $series[] = ['name' => $def['value2_label'], 'key' => 'value2', 'dashed' => true, 'square' => true]; }
        @endphp
        <x-ui.section :title="$def['label']" :level="2" :description="$sum['count'].' '.($sum['count'] === 1 ? 'registro' : 'registros')" wire:key="trend-{{ $chart['type'] }}">
            <x-health.trend-chart :series="$series" :points="$chart['points']->all()" :days="$chart['days']" :band="$band" :band-label="$band ? 'Rango de referencia' : null"
                                  :unit="$unit" :label="$def['label']" :decimals="$decimals" />
            <p class="md-body-medium mb-0">
                Promedio {{ $averageText }} {{ $unit }}
                · mínimo {{ $fmt($sum['min']) }} · máximo {{ $fmt($sum['max']) }}
                @if ($sum['out_of_range'] > 0)
                    · {{ $sum['out_of_range'] }} fuera del rango de referencia general @if ($range) ({{ $range }}) @endif
                @endif
                @if ($dir = HealthTrends::directionText($sum['direction'])) · {{ $dir }} @endif
            </p>
        </x-ui.section>
    @endforeach

    @if ($sleep)
        @php
            $s = $sleep['summary'];
            $goalHours = round($sleep['goal'] / 60, 2);
        @endphp
        <x-ui.section title="Sueño" :level="2" :description="$s['count'].' '.($s['count'] === 1 ? 'registro' : 'registros').' · meta '.SleepLog::formatMinutes($sleep['goal'])" wire:key="trend-sleep">
            <x-health.trend-chart :series="[['name' => 'Horas dormidas', 'key' => 'value']]" :points="$sleep['points']->all()" :days="$sleep['days']"
                                  :goal="$goalHours" :goal-label="'Meta '.SleepLog::formatMinutes($sleep['goal'])" unit="h" label="Sueño" :decimals="1" />
            <p class="md-body-medium mb-0">
                Promedio {{ SleepLog::formatMinutes($s['average_minutes']) }}
                · {{ $s['meeting_goal'] }} de {{ $s['count'] }} {{ $s['count'] === 1 ? 'registro cumplió' : 'registros cumplieron' }} tu meta
                · mínimo {{ SleepLog::formatMinutes($s['min_minutes']) }} · máximo {{ SleepLog::formatMinutes($s['max_minutes']) }}
                @if ($dir = HealthTrends::directionText($s['direction'])) · {{ $dir }} @endif
            </p>
        </x-ui.section>
    @endif
</x-module-shell>
