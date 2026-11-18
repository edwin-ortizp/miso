<?php

namespace App\Actions;

use App\Models\ExerciseLog;
use App\Models\ExerciseType;
use App\Support\ExerciseEnergy;
use App\Support\ExerciseMetrics;
use Illuminate\Support\Facades\Validator;

class LogExercise
{
    public static function handle(ExerciseType $type, string $date, array $data, ?ExerciseLog $log = null): ExerciseLog
    {
        abort_unless(auth()->id() && (int) $type->user_id === (int) auth()->id()
            && (! $log || (int) $log->user_id === (int) auth()->id()), 404);

        $data = Validator::make($data, ExerciseMetrics::RULES, [], ExerciseMetrics::LABELS)->validate();
        $log ??= new ExerciseLog(['date' => $date]);
        $previousCalories = $log->calories;
        $previousSource = $log->calories_source;
        $previousSteps = $log->steps;

        $log->fill(['exercise_type_id' => $type->id, ...$data]);
        $hours = (float) $log->duration / 60;
        if (array_key_exists('calories', $data) && $data['calories'] !== null) {
            $log->calories_source = 'manual';
        } elseif ($log->isDirty('calories') || ! $log->exists
            || ($previousCalories === null || in_array($previousSource, ['catalog_rate', 'speed_met'], true))
                && $log->isDirty(['distance', 'duration', 'exercise_type_id'])) {
            $kg = (float) auth()->user()->current_weight_kg;
            $distance = round((float) $log->distance, 2);
            $met = $hours > 0 && $distance > 0 && $kg > 0
                ? ExerciseEnergy::met(ExerciseMetrics::profile($type), $distance / $hours)
                : null;

            $log->calories_source = match (true) {
                $met !== null => 'speed_met',
                $hours > 0 && $type->calories_per_hour > 0 => 'catalog_rate',
                default => null,
            };
            $log->calories = match ($log->calories_source) {
                'speed_met' => self::rounded($hours * $met * $kg),
                'catalog_rate' => self::rounded($hours * $type->calories_per_hour),
                default => null,
            };
            if ($log->calories === null) {
                $log->calories_source = null;
            }
        }

        if ($log->steps === null && (! $log->exists || $previousSteps !== null || $log->isDirty(['duration', 'exercise_type_id']))) {
            $log->steps = $hours > 0 && $type->steps_equivalent > 0
                ? self::rounded($hours * $type->steps_equivalent)
                : null;
        }

        $log->save();

        return $log->setRelation('exerciseType', $type);
    }

    private static function rounded(float $value): ?int
    {
        $value = round($value);

        return $value <= 2147483647 ? (int) $value : null;
    }
}
