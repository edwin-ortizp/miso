<?php

namespace Tests\Feature\Game;

use App\Models\Game\GameSpecies;
use App\Models\HabitDefinition;
use App\Models\Task;
use App\Services\Game\GameSettings;
use App\Services\TaskGamificationService;

/** Catálogo genérico de prueba: el repo no contiene especies de terceros. */
trait GameTestHelpers
{
    protected function seedSpecies(): void
    {
        // Las pruebas previas a CHG-021 no miden misiones: sin ellas, el conteo de balls es determinista.
        // Las pruebas de la semana las reactivan con withWeeklyMissions().
        config(['game.mission_types' => []]);

        $rows = [
            [1, 'Brotelo', 'brotelo', 1, 'common'],
            [2, 'Brotalia', 'brotelo', 2, 'common'],
            [3, 'Chispin', 'chispin', 1, 'common'],
            [4, 'Chispazo', 'chispin', 2, 'common'],
            [5, 'Gotin', 'gotin', 1, 'uncommon'],
            [6, 'Rocon', 'rocon', 1, 'rare'],
        ];
        foreach ($rows as [$number, $name, $line, $stage, $rarity]) {
            GameSpecies::create(['number' => $number, 'name' => $name, 'line_key' => $line, 'stage' => $stage, 'rarity' => $rarity, 'image_key' => "{$number}.png"]);
        }
    }

    protected function withWeeklyMissions(): void
    {
        config(['game.mission_types' => ['category_tasks', 'habit_groups', 'variety']]);
    }

    protected function completeTask(string $title = 'Pagar arriendo'): Task
    {
        $task = Task::create(['title' => $title, 'start_date' => today()]);
        app(TaskGamificationService::class)->complete($task);

        return $task->refresh();
    }

    protected function habit(string $name = 'Caminar'): HabitDefinition
    {
        return HabitDefinition::create(['name' => $name, 'time_of_day' => 'morning']);
    }

    /** @param array<string, mixed> $settings */
    protected function gameSettings(array $settings): void
    {
        app(GameSettings::class)->save(auth()->id(), $settings);
    }
}
