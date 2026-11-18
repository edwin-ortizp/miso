@php
    $name = $prefix.$key;
    $step = in_array($key, ['distance', 'weight'], true) ? '0.01' : '1';
    $preview = in_array($key, ['sets', 'reps'], true)
        || (in_array($profile, ['walk', 'jog', 'run'], true) && in_array($key, ['duration', 'distance'], true));
@endphp
@if ($preview)
    <x-ui.field :name="$name" :label="\App\Support\ExerciseMetrics::LABELS[$key]" type="number" min="0" :step="$step" wire:model.live.blur="{{ $name }}" />
@else
    <x-ui.field :name="$name" :label="\App\Support\ExerciseMetrics::LABELS[$key]" type="number" min="0" :step="$step"
                :help="$key === 'calories' ? 'Si lo dejas vacío, MiSo estima las calorías cuando tiene datos suficientes.' : null"
                wire:model="{{ $name }}" />
@endif
