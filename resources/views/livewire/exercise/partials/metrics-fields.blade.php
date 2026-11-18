@php
    [$mainFields, $detailFields] = \App\Support\ExerciseMetrics::fields($profile);
    $supportsSeries = in_array('total_reps', $mainFields, true) || $seriesMode || in_array('total_reps', $preservedFields, true);
    $mainFields = array_values(array_diff($mainFields, $seriesMode ? ['total_reps'] : []));
    $detailFields = array_values(array_unique([...$detailFields, ...$preservedFields, ...($seriesMode ? ['sets', 'reps'] : [])]));
    $detailFields = array_values(array_diff($detailFields, $mainFields, $seriesMode ? ['total_reps'] : ['sets', 'reps']));
    $pace = \App\Support\ExerciseMetrics::pace($profile, $values['duration'] ?? null, $values['distance'] ?? null);
    $errorsInDetail = $errors->hasAny(array_map(fn ($key) => $prefix.$key, $detailFields));
@endphp

<div class="d-flex flex-column gap-3">
    @if ($mainFields)
        <div class="md-field-pair">
            @foreach ($mainFields as $key)
                @include('livewire.exercise.partials.metric-field', ['key' => $key])
            @endforeach
        </div>
    @endif

    @if ($pace !== null)
        <p class="md-body-medium mb-0" role="status">Ritmo calculado · {{ \App\Support\ExerciseMetrics::formatPace($pace) }}</p>
    @endif

    <details wire:ignore.self
             @if ($seriesMode || $errorsInDetail || array_intersect($preservedFields, $detailFields)) open @endif
             wire:key="{{ $prefix }}exercise-details-{{ $profile }}-{{ (int) $errorsInDetail }}">
        <summary class="md-body-medium">Más detalle</summary>
        <div class="d-flex flex-column gap-3 mt-3">
            @if ($supportsSeries)
                <div>
                    <button type="button" class="md-btn-text" wire:click="{{ $seriesMode ? $seriesAction.'(false)' : $seriesAction.'(true)' }}">
                        {{ $seriesMode ? 'Registrar el total' : 'Registrar por series' }}
                    </button>
                </div>
            @endif
            @foreach (array_chunk(array_diff($detailFields, ['notes']), 2) as $pair)
                <div class="md-field-pair">
                    @foreach ($pair as $key)
                        @include('livewire.exercise.partials.metric-field', ['key' => $key])
                    @endforeach
                </div>
            @endforeach
            @if ($seriesMode && is_numeric($values['sets'] ?? null) && is_numeric($values['reps'] ?? null))
                <p class="md-body-medium mb-0" role="status">{{ (int) $values['sets'] * (int) $values['reps'] }} repeticiones en total</p>
            @endif
            <x-ui.textarea :name="$prefix.'notes'" label="Notas" rows="2" wire:model="{{ $prefix }}notes" />
        </div>
    </details>
</div>
