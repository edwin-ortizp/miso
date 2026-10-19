<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;

class TaskGamificationService
{
    public const XP_PER_LEVEL = 100;

    /**
     * Complete or reopen a task and return the UI feedback for the action.
     *
     * @return array{completed: bool, xp?: int, streak?: int, level?: int, levelUp?: bool, streakMilestone?: bool}
     */
    public function toggle(Task $task): array
    {
        if ($task->completed) {
            return $this->reopen($task);
        }

        return $this->complete($task);
    }

    public function reopen(Task $task): array
    {
        $task->update([
            'completed' => false,
            'completed_at' => null,
            'completion_xp' => null,
            'progress' => min((int) $task->progress, 99),
        ]);

        return ['completed' => false];
    }

    public function complete(Task $task, ?Carbon $completedAt = null): array
    {
        if ($task->completed) {
            return ['completed' => false];
        }

        $xp = $this->completionXp($task);
        $previousXp = (int) Task::where('completed', true)->sum('completion_xp');

        $task->update([
            'completed' => true,
            'completed_at' => $completedAt ?? now(),
            'completion_xp' => $xp,
            'progress' => 100,
        ]);

        $totalXp = $previousXp + $xp;
        $streaks = $this->streaks();

        return [
            'completed' => true,
            'xp' => $xp,
            'streak' => $streaks['current'],
            'level' => $this->levelForXp($totalXp),
            'levelUp' => $this->levelForXp($previousXp) < $this->levelForXp($totalXp),
            'streakMilestone' => in_array($streaks['current'], [3, 7, 14, 30, 100], true),
        ];
    }

    public function completionXp(Task $task): int
    {
        $baseXp = [
            'XS' => 1,
            'S' => 2,
            'M' => 4,
            'L' => 6,
            'XL' => 9,
        ][$task->size] ?? 1;

        return in_array($task->priority, ['urgent-important', 'not-urgent-important'], true)
            ? $baseXp * 2
            : $baseXp;
    }

    /** @return array{current: int, best: int} */
    public function streaks(): array
    {
        $dates = Task::query()
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->pluck('completed_at')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return ['current' => 0, 'best' => 0];
        }

        $best = 1;
        $run = 1;
        for ($index = 1; $index < $dates->count(); $index++) {
            if (Carbon::parse($dates[$index - 1])->addDay()->isSameDay(Carbon::parse($dates[$index]))) {
                $run++;
                $best = max($best, $run);
            } else {
                $run = 1;
            }
        }

        $current = 0;
        $cursor = today()->startOfDay();
        $dateLookup = $dates->flip();
        while ($dateLookup->has($cursor->toDateString())) {
            $current++;
            $cursor->subDay();
        }

        return ['current' => $current, 'best' => $best];
    }

    public function levelForXp(int $xp): int
    {
        return intdiv($xp, self::XP_PER_LEVEL) + 1;
    }

    /** @return array{level: int, totalXp: int, currentLevelXp: int, xpToNextLevel: int, progressPercent: int} */
    public function levelProgress(): array
    {
        $totalXp = (int) Task::where('completed', true)->sum('completion_xp');
        $currentLevelXp = $totalXp % self::XP_PER_LEVEL;

        return [
            'level' => $this->levelForXp($totalXp),
            'totalXp' => $totalXp,
            'currentLevelXp' => $currentLevelXp,
            'xpToNextLevel' => self::XP_PER_LEVEL - $currentLevelXp,
            'progressPercent' => $currentLevelXp,
        ];
    }
}
