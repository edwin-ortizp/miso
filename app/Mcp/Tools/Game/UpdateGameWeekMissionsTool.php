<?php

namespace App\Mcp\Tools\Game;

use App\Services\Game\SeasonManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Ajusta de 1 a 3 misiones existentes de la semana actual a petición del usuario. Consulta antes get-game-director-context-tool: usa id y solo editable=true. Envía cada sustitución completa {id, type, title, target, task_ids?, category?, period?}. No añade misiones ni cambia las que tienen avance o recompensas. category_tasks requiere pendientes no recurrentes no privados; target entre 1 y el menor de 5 y pending_tasks_by_category[category]. Los demás tipos mantienen las reglas de propose-game-week-missions-tool. Operación atómica; un reintento idéntico responde unchanged, sin nuevas recompensas. No usar para regenerar automáticamente la semana.')]
class UpdateGameWeekMissionsTool extends Tool
{
    public function handle(Request $request, SeasonManager $seasons): Response
    {
        $data = $request->validate(['missions' => ['required', 'array']]);
        $result = $seasons->updateWeekMissions((int) Auth::id(), $data['missions']);
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $result['status'] === 'rejected' ? Response::error($json) : Response::text($json);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['missions' => $schema->array()
            ->description('De 1 a 3 sustituciones completas: {id entero, type, title, target, task_ids?, category?, period?}.')
            ->required()];
    }
}
