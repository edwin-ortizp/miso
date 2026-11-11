@php
    $series = static function (array $data, array $dates, float $max, float $height): array {
        return collect($dates)->map(static fn (string $date) => [
            'date' => $date,
            'value' => $data[$date] ?? 0,
            'size' => $max > 0 ? round((($data[$date] ?? 0) / $max) * $height) : 0,
        ])->all();
    };

    $maxWater = max(array_values($waterData) ?: [1]);
    $maxExercise = max(array_values($exerciseData) ?: [1]);
    $maxHabits = max($totalHabits, 1);
@endphp

<x-module-shell module="statistics" archetype="dashboard">
    <x-slot:controls>
        <x-ui.filter-bar label="Ventana de análisis">
            <x-slot:chips>
                @foreach ([7 => '7 días', 14 => '14 días', 30 => '30 días'] as $value => $label)
                    <x-ui.chip variant="filter" :selected="$days === $value" wire:click="$set('days', {{ $value }})">{{ $label }}</x-ui.chip>
                @endforeach
            </x-slot:chips>
        </x-ui.filter-bar>
    </x-slot:controls>

    {{-- CHG-070: hallazgos de las últimas 8 semanas. --}}
    <x-ui.section title="Lo que dicen tus datos" description="Patrones de las últimas 8 semanas. Muestran tendencias, no causas." :level="2" id="insights">
        <x-ui.card>
            <x-insights.findings :findings="$insights" />
        </x-ui.card>
    </x-ui.section>

    <x-ui.section title="Promedios del periodo" :level="2">
        <x-ui.metric-grid label="Promedios del periodo">
            <x-ui.metric label="Hidratación" icon="bi-droplet" tone="primary"
                         :value="number_format($avgWater, 0)" unit="ml/día" support="Promedio del periodo" />
            <x-ui.metric label="Ejercicio" icon="bi-fire" tone="danger"
                         :value="number_format($avgExercise, 0)" unit="kcal/día" support="Promedio del periodo" />
            <x-ui.metric label="Hábitos" icon="bi-check2-square" tone="success"
                         :value="number_format($avgHabits, 1).'/'.$totalHabits" support="Completados por día" />
            <x-ui.metric label="Diario" icon="bi-journal" tone="info"
                         :value="$journalCount.'/'.$days" support="Días con registro" />
        </x-ui.metric-grid>
    </x-ui.section>

    <x-ui.section title="Hidratación" description="Mililitros registrados cada día." :level="3">
        <x-ui.card variant="elevated">
            <div class="stat-series stat-series--water">
                @foreach ($series($waterData, $dates, (float) $maxWater, 80) as $point)
                    <div class="stat-series__column">
                        <div class="stat-series__bar" style="--md-bar-size: {{ $point['size'] }}px;"
                             role="img" aria-label="{{ \Carbon\Carbon::parse($point['date'])->format('d/m') }}: {{ $point['value'] }} ml"></div>
                        <span class="stat-series__label md-label-small">{{ \Carbon\Carbon::parse($point['date'])->format('d') }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </x-ui.section>

    <x-ui.section title="Ejercicio" description="Calorías estimadas cada día." :level="3">
        <x-ui.card variant="elevated">
            <div class="stat-series stat-series--exercise">
                @foreach ($series($exerciseData, $dates, (float) $maxExercise, 80) as $point)
                    <div class="stat-series__column">
                        <div class="stat-series__bar" style="--md-bar-size: {{ $point['size'] }}px;"
                             role="img" aria-label="{{ \Carbon\Carbon::parse($point['date'])->format('d/m') }}: {{ $point['value'] }} kcal"></div>
                        <span class="stat-series__label md-label-small">{{ \Carbon\Carbon::parse($point['date'])->format('d') }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </x-ui.section>

    <x-ui.section title="Hábitos completados" description="Sobre {{ $totalHabits }} hábitos activos." :level="3">
        <x-ui.card variant="elevated">
            <div class="stat-series stat-series--habits">
                @foreach ($series($habitsData, $dates, (float) $maxHabits, 80) as $point)
                    <div class="stat-series__column">
                        <div class="stat-series__bar" style="--md-bar-size: {{ $point['size'] }}px;"
                             role="img" aria-label="{{ \Carbon\Carbon::parse($point['date'])->format('d/m') }}: {{ $point['value'] }} hábitos"></div>
                        <span class="stat-series__label md-label-small">{{ \Carbon\Carbon::parse($point['date'])->format('d') }}</span>
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    </x-ui.section>

    <div class="stat-split">
        <x-ui.section title="Estado de ánimo" :level="3">
            <x-ui.card variant="elevated">
                <div class="stat-series stat-series--compact stat-series--mood">
                    @foreach ($series($moodData, $dates, 5.0, 70) as $point)
                        <div class="stat-series__column">
                            <div class="stat-series__bar" style="--md-bar-size: {{ $point['size'] }}px;"
                                 role="img" aria-label="{{ \Carbon\Carbon::parse($point['date'])->format('d/m') }}: {{ $point['value'] ?: 'sin registro' }}"></div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Energía" :level="3">
            <x-ui.card variant="elevated">
                <div class="stat-series stat-series--compact stat-series--energy">
                    @foreach ($series($energyData, $dates, 5.0, 70) as $point)
                        <div class="stat-series__column">
                            <div class="stat-series__bar" style="--md-bar-size: {{ $point['size'] }}px;"
                                 role="img" aria-label="{{ \Carbon\Carbon::parse($point['date'])->format('d/m') }}: {{ $point['value'] ?: 'sin registro' }}"></div>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </x-ui.section>
    </div>

    <x-ui.section title="Tareas" :level="3">
        <x-ui.metric-grid label="Estado de las tareas">
            <x-ui.metric label="Pendientes" icon="bi-list-task" tone="warning" :value="$tasksPendingCount" />
            <x-ui.metric label="Completadas" icon="bi-check2-circle" tone="success" :value="$tasksCompletedCount" />
        </x-ui.metric-grid>
    </x-ui.section>
</x-module-shell>
