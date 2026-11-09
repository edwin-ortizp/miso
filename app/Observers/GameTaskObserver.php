<?php

namespace App\Observers;

use App\Models\Task;
use App\Services\Game\RewardGranter;
use Illuminate\Support\Facades\DB;

/**
 * Da la ball desde el propio modelo para cubrir todas las vías de completar una
 * tarea: listas, kanban o cambio de estado, recurrentes (su copia de historial),
 * calendario, salud, relaciones, panel, CalDAV y MCP. La idempotencia del
 * RewardGranter evita duplicados si una vía guarda dos veces.
 */
class GameTaskObserver
{
    public function saved(Task $task): void
    {
        if (! $task->completed) {
            return;
        }

        if ($task->wasRecentlyCreated || $task->wasChanged('completed')) {
            // Tras confirmar la transacción: así una recurrente ya tiene su serie (CHG-026)
            // y una completación revertida nunca da ball. Sin transacción corre de inmediato.
            DB::afterCommit(fn () => app(RewardGranter::class)->forTask($task->fresh() ?? $task));
        }
    }
}
