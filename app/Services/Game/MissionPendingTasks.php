<?php

namespace App\Services\Game;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Pendientes que pueden sostener una misión por categoría, sin inflar recurrentes. */
class MissionPendingTasks
{
    public function query(int $userId): Builder
    {
        return Task::withoutGlobalScopes()->where('user_id', $userId)
            ->where('completed', false)->where('is_private', false)
            ->where('is_recurrent', false)->where('is_recurrence_history', false);
    }

    /** @return Collection<string, int> */
    public function counts(int $userId): Collection
    {
        return $this->query($userId)->selectRaw('category, COUNT(*) as total')
            ->groupBy('category')->pluck('total', 'category')->map(fn ($count) => (int) $count);
    }
}
