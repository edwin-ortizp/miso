<?php

namespace App\Support\Habits\Actions;

use App\Actions\LogExercise;
use App\Models\ExerciseType;
use App\Models\HabitDefinition;
use App\Support\ExerciseMetrics;
use App\Support\Habits\HabitAction;
use App\Support\Habits\HabitActionField;
use App\Support\Habits\HabitActionResult;
use Carbon\CarbonInterface;

class LogExerciseAction extends HabitAction
{
    public static function key(): string
    {
        return 'exercise.log';
    }

    public static function module(): string
    {
        return 'exercise';
    }

    public static function label(): string
    {
        return 'Registrar una sesión de ejercicio';
    }

    public static function icon(): string
    {
        return 'bi-bicycle';
    }

    public function fields(): array
    {
        $types = app(\App\Services\Catalog\CatalogPicker::class)->ordered('exercise')->pluck('name', 'id')->all();

        return [
            HabitActionField::select('exercise_type_id', '¿Qué ejercicio hiciste?', $types, ['required', 'string'], array_key_first($types), ask: true),
            HabitActionField::number('duration', 'Duración (minutos)', ['nullable', 'integer', 'min:0', 'max:600'], 30, 'Cuando hay una tasa configurada, la duración permite estimar calorías y pasos.', ask: true),
        ];
    }

    public function promptFields(array $config = []): array
    {
        [$type] = $this->fields();
        $selected = $config['exercise_type_id'] ?? $type->default;
        $type = HabitActionField::select($type->key, $type->label, $type->options, $type->rules, $selected, ask: true);
        $fields = [$type];
        foreach (ExerciseMetrics::RULES as $key => $rules) {
            $label = ExerciseMetrics::LABELS[$key];
            $default = $config[$key] ?? null;
            $fields[] = $key === 'notes'
                ? HabitActionField::text($key, $label, $rules, $default, ask: true)
                : HabitActionField::number($key, $label, $rules, $default, ask: true);
        }

        return $fields;
    }

    public function execute(HabitDefinition $habit, CarbonInterface $date, array $config, array $input): HabitActionResult
    {
        $values = array_replace($config, $input);
        $type = ExerciseType::query()->find($values['exercise_type_id'] ?? null);

        if (! $type) {
            throw new \RuntimeException('El tipo de ejercicio configurado ya no existe en tu catálogo.');
        }

        $log = LogExercise::handle($type, $date->toDateString(), $values);
        $detail = match (true) {
            $log->totalRepetitions() !== null => ' ('.$log->totalRepetitions().' repeticiones)',
            (bool) $log->duration => " ({$log->duration} min)",
            (bool) $log->distance => " ({$log->distance} km)",
            default => '',
        };

        return new HabitActionResult($log, "También registré {$type->name}{$detail} en tu ejercicio.");
    }
}
