<?php

namespace App\Services\Game;

use App\Models\Game\GameMission;
use App\Models\HabitDefinition;
use App\Models\Task;
use App\Models\TaskCategory;

/**
 * Valida las misiones semanales que propone el director (CHG-026). Estricto:
 * cualquier campo desconocido, referencia ajena o meta fuera de rango rechaza
 * la propuesta completa. Devuelve las misiones normalizadas listas para crear.
 */
class MissionProposalValidator
{
    private const KEYS = ['type', 'title', 'target', 'task_ids', 'category', 'period'];
    private const PENALTY_WORDS = ['debes', 'obligatorio', 'obligatoria', 'atrasado', 'atrasada', 'fallaste', 'castigo', 'castigar'];

    /**
     * @param  array<int, mixed>  $missions
     * @return array{errors: array<int, string>, missions: array<int, array<string, mixed>>}
     */
    public function validate(array $missions, int $userId): array
    {
        $errors = [];
        $clean = [];
        [$minTarget, $maxTarget] = config('game.season.mission_target');

        if ($missions === [] || count($missions) > (int) config('game.missions_per_week') || ! array_is_list($missions)) {
            return ['errors' => ['Envía de 1 a '.config('game.missions_per_week').' misiones.'], 'missions' => []];
        }

        // Categorías de tarea más los ámbitos del juego que no son hábitos (foco, metas, salud).
        $categories = TaskCategory::withoutGlobalScopes()->where('user_id', $userId)->pluck('name', 'key')
            ->union(collect(config('game.scopes'))->except('habits'));
        $periods = HabitDefinition::withoutGlobalScopes()->where('user_id', $userId)->get(['time_of_day'])
            ->map(fn ($habit) => $habit->time_of_day ?: 'anytime')->unique()->values()->all();

        foreach ($missions as $index => $mission) {
            $n = $index + 1;
            if (! is_array($mission)) {
                $errors[] = "Misión {$n}: debe ser un objeto.";

                continue;
            }
            foreach (array_diff(array_keys($mission), self::KEYS) as $unknown) {
                $errors[] = "Misión {$n}: campo no permitido «{$unknown}».";
            }

            $title = $mission['title'] ?? null;
            if (! is_string($title) || trim($title) === '' || mb_strlen($title) > 120) {
                $errors[] = "Misión {$n}: el título es obligatorio y tiene máximo 120 caracteres.";
            } elseif ($word = $this->penaltyWord($title)) {
                $errors[] = "Misión {$n}: el título no puede usar «{$word}»; las misiones no castigan.";
            }

            $target = $mission['target'] ?? null;
            $type = $mission['type'] ?? null;
            $row = ['type' => $type, 'title' => is_string($title) ? trim($title) : '', 'target' => $target, 'params' => []];

            switch ($type) {
                case GameMission::TYPE_SPECIFIC:
                    $ids = $mission['task_ids'] ?? null;
                    if (! is_array($ids) || count($ids) < 2 || count($ids) > 6 || count(array_unique($ids)) !== count($ids)) {
                        $errors[] = "Misión {$n}: elige de 2 a 6 tareas distintas.";
                        break;
                    }
                    $tasks = Task::withoutGlobalScopes()->where('user_id', $userId)->whereIn('id', array_map('strval', $ids))
                        ->where('completed', false)->where('is_private', false)->where('is_recurrence_history', false)->get(['id', 'code', 'title', 'recurrence_series_id']);
                    if ($tasks->count() !== count($ids)) {
                        $errors[] = "Misión {$n}: hay tareas que no existen, no son tuyas, ya están completadas o son privadas.";
                        break;
                    }
                    if (! is_int($target) || $target < 1 || $target > count($ids)) {
                        $errors[] = "Misión {$n}: la meta debe estar entre 1 y ".count($ids).'.';
                        break;
                    }
                    // El orden de la propuesta se respeta; el título es una instantánea por si la tarea se borra.
                    $byId = $tasks->keyBy(fn ($task) => (string) $task->id);
                    $row['params'] = ['tasks' => array_map(fn ($id) => [
                        'id' => (string) $id,
                        'series_id' => $byId[(string) $id]->recurrence_series_id,
                        'code' => $byId[(string) $id]->code,
                        'title' => $byId[(string) $id]->title,
                    ], $ids)];
                    break;

                case GameMission::TYPE_CATEGORY:
                    $category = $mission['category'] ?? null;
                    if (! is_string($category) || ! $categories->has($category)) {
                        $errors[] = "Misión {$n}: la categoría no existe.";
                    }
                    $row['params'] = ['category' => $category, 'category_name' => $categories[$category] ?? $category];
                    $this->checkTarget($target, $minTarget, $maxTarget, $n, $errors);
                    break;

                case GameMission::TYPE_HABITS:
                    $period = $mission['period'] ?? null;
                    if (! in_array($period, $periods, true)) {
                        $errors[] = "Misión {$n}: no tienes hábitos en ese momento del día.";
                    }
                    $row['params'] = ['period' => $period];
                    $this->checkTarget($target, $minTarget, $maxTarget, $n, $errors);
                    break;

                case GameMission::TYPE_VARIETY:
                    $this->checkTarget($target, $minTarget, min($maxTarget, $categories->count() + ($periods !== [] ? 1 : 0)), $n, $errors);
                    break;

                default:
                    $errors[] = "Misión {$n}: tipo no permitido (specific_tasks, category_tasks, habit_groups o variety).";
            }

            $clean[] = $row;
        }

        return ['errors' => $errors, 'missions' => $errors === [] ? $clean : []];
    }

    /** @param array<int, string> $errors */
    private function checkTarget(mixed $target, int $min, int $max, int $n, array &$errors): void
    {
        if (! is_int($target) || $target < $min || $target > $max) {
            $errors[] = "Misión {$n}: la meta debe estar entre {$min} y {$max}.";
        }
    }

    private function penaltyWord(string $title): ?string
    {
        $lower = mb_strtolower($title);
        foreach (self::PENALTY_WORDS as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $lower)) {
                return $word;
            }
        }

        return null;
    }
}
