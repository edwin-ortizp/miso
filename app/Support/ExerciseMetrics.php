<?php

namespace App\Support;

use App\Models\ExerciseType;
use App\Models\UserCategory;
use Illuminate\Support\Str;

class ExerciseMetrics
{
    public const LABELS = [
        'total_reps' => 'Repeticiones totales',
        'sets' => 'Series',
        'reps' => 'Repeticiones por serie',
        'weight' => 'Peso usado (kg)',
        'distance' => 'Distancia (km)',
        'duration' => 'Duración (min)',
        'steps' => 'Pasos',
        'calories' => 'Calorías (kcal)',
        'notes' => 'Notas',
    ];

    public const RULES = [
        'total_reps' => ['nullable', 'integer', 'between:0,2147483647', 'prohibits:sets,reps'],
        'sets' => ['nullable', 'integer', 'between:0,2147483647'],
        'reps' => ['nullable', 'integer', 'between:0,2147483647'],
        'duration' => ['nullable', 'integer', 'between:0,2147483647'],
        'distance' => ['nullable', 'numeric', 'between:0,999999.99'],
        'weight' => ['nullable', 'numeric', 'between:0,999999.99'],
        'calories' => ['nullable', 'integer', 'between:0,2147483647'],
        'steps' => ['nullable', 'integer', 'between:0,2147483647'],
        'notes' => ['nullable', 'string', 'max:1000'],
    ];

    public static function profile(ExerciseType $type): string
    {
        $name = self::baseName($type);

        return match ($name) {
            'Caminar' => 'walk',
            'Trotar' => 'jog',
            'Correr' => 'run',
            'Senderismo', 'Bicicleta', 'Bicicleta estática', 'Natación' => 'distance',
            'Flexiones de pecho', 'Sentadillas', 'Abdominales', 'Burpees' => 'reps',
            'Entrenamiento de fuerza', 'Pesas de mano' => 'weight',
            'Pasos' => 'steps',
            null => self::strengthCategory($type) ? 'weight' : 'duration',
            default => 'duration',
        };
    }

    public static function fields(string $profile): array
    {
        [$main, $detail] = match ($profile) {
            'reps' => [['total_reps'], ['sets', 'reps', 'duration']],
            'weight' => [['total_reps'], ['sets', 'reps', 'weight', 'duration']],
            'walk', 'jog', 'run' => [['distance', 'duration'], ['steps']],
            'distance' => [['distance', 'duration'], []],
            'steps' => [['steps'], ['duration', 'distance']],
            default => [['duration'], []],
        };

        return [$main, [...$detail, 'calories', 'notes']];
    }

    public static function pace(string $profile, mixed $duration, mixed $distance): ?int
    {
        if (! in_array($profile, ['walk', 'jog', 'run'], true) || ! is_numeric($duration) || ! is_numeric($distance)
            || $duration <= 0 || $distance < 0.005) {
            return null;
        }

        return (int) round(($duration * 60) / round((float) $distance, 2));
    }

    public static function formatPace(int $seconds): string
    {
        return sprintf('%d:%02d min/km', intdiv($seconds, 60), $seconds % 60);
    }

    private static function baseName(ExerciseType $type): ?string
    {
        $definitions = DefaultExerciseTypes::all();
        foreach ($definitions as $definition) {
            if ($type->legacy_id !== null && (int) $type->legacy_id === $definition['legacy_id']) {
                return $definition['name'];
            }
        }

        $name = Str::lower(Str::ascii(trim($type->name)));
        foreach ($definitions as $definition) {
            foreach ([$definition['name'], ...$definition['aliases']] as $alias) {
                if ($name === Str::lower(Str::ascii($alias))) {
                    return $definition['name'];
                }
            }
        }

        return null;
    }

    private static function strengthCategory(ExerciseType $type): bool
    {
        if (! $type->category || ! $type->user_id) {
            return $type->category === 'strength';
        }

        $categories = app(RequestMemo::class)->remember("exercise-category-roots:{$type->user_id}", fn () => UserCategory::query()
            ->where('user_id', $type->user_id)->where('domain', 'exercise')->get(['id', 'key', 'parent_id']));
        $category = $categories->firstWhere('key', $type->category);
        $byId = $categories->keyBy('id');
        $visited = [];
        while ($category?->parent_id && ! isset($visited[$category->id])) {
            $visited[$category->id] = true;
            $category = $byId->get($category->parent_id);
        }

        return ($category?->key ?? $type->category) === 'strength';
    }
}
