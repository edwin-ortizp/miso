<?php

namespace App\Services\Game\Guardian;

use App\Services\Game\ModuleGuardian;
use Carbon\CarbonInterface;

/**
 * Consejo del guardián (CHG-057): candidatos del proveedor del módulo, elegidos
 * al azar ponderado sin repetir el actual; sin candidatos, la frase por avance.
 */
class GuardianAdvisor
{
    private const PROVIDERS = [
        'tasks' => TaskAdvice::class, 'health' => HealthAdvice::class,
        'water' => WaterAdvice::class, 'exercise' => ExerciseAdvice::class,
        'pomodoro' => PomodoroAdvice::class, 'habits' => HabitAdvice::class,
        'goals' => GoalAdvice::class, 'mood' => MoodAdvice::class,
        'negative-habits' => NegativeHabitAdvice::class, 'journal' => JournalAdvice::class,
        'meals' => MealAdvice::class, 'relationships' => RelationshipAdvice::class,
        'plans' => PlanAdvice::class, 'vehicles' => VehicleAdvice::class,
    ];

    /**
     * @param  array{done: int, total: int, care: bool}  $progress  para la frase por avance
     * @return array{key: string, html: string, action?: array{label: string, target: string}}
     */
    public function advise(string $module, CarbonInterface $now, ?string $except, array $progress): array
    {
        $provider = self::PROVIDERS[$module] ?? null;
        $candidates = $provider ? app($provider)->candidates($now) : [];

        $pool = array_values(array_filter($candidates, fn ($c) => $c['key'] !== $except)) ?: $candidates;
        if ($pool !== []) {
            $roll = random_int(1, array_sum(array_column($pool, 'weight')));
            foreach ($pool as $candidate) {
                if (($roll -= $candidate['weight']) <= 0) {
                    return array_intersect_key($candidate, array_flip(['key', 'html', 'action']));
                }
            }
        }

        return [
            'key' => 'progress',
            'html' => e(ModuleGuardian::line($module, $progress['done'], $progress['total'], $progress['care'], $now)),
        ];
    }
}
