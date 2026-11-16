<?php

namespace App\Support;

use App\Models\ModuleSetting;
use Carbon\CarbonInterface;

/**
 * Ajustes de Pomodoro de un usuario sobre sus valores por defecto (CHG-135).
 *
 * Único lugar que conoce las claves guardadas y sus defectos. Se registra como
 * `scoped`: la barra de estado, el temporizador global y el guardián comparten
 * una sola lectura por petición, y `AppServiceProvider` la olvida en cuanto se
 * guarda o borra un `ModuleSetting`.
 */
class PomodoroSettings
{
    public const MODULE = 'pomodoro';

    public const DEFAULTS = [
        'work_duration_minutes' => 25,
        'short_break_minutes' => 5,
        'long_break_minutes' => 15,
        'weekday_goal_minutes' => 300,
        'weekend_goal_minutes' => 120,
    ];

    /** @var array<int, array<string, int>> */
    private array $resolved = [];

    public function flush(): void
    {
        $this->resolved = [];
    }

    /** @return array{work_duration_minutes: int, short_break_minutes: int, long_break_minutes: int, weekday_goal_minutes: int, weekend_goal_minutes: int} */
    public function for(?int $userId = null): array
    {
        $userId ??= (int) auth()->id();

        return $this->resolved[$userId] ??= $this->read($userId);
    }

    /** Meta de foco, en minutos, que corresponde a esa fecha. */
    public function goalFor(CarbonInterface $date, ?int $userId = null): int
    {
        $settings = $this->for($userId);

        return $date->isWeekend() ? $settings['weekend_goal_minutes'] : $settings['weekday_goal_minutes'];
    }

    /** @return array<string, int> */
    private function read(int $userId): array
    {
        $stored = $userId > 0
            ? ModuleSetting::withoutGlobalScopes()->where('user_id', $userId)->where('module', self::MODULE)->value('settings')
            : null;
        $settings = [];
        foreach (self::DEFAULTS as $key => $default) {
            $settings[$key] = (int) ((is_array($stored) ? $stored[$key] ?? null : null) ?? $default);
        }

        return $settings;
    }
}
