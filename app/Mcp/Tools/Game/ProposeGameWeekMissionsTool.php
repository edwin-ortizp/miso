<?php

namespace App\Mcp\Tools\Game;

use App\Services\Game\SeasonManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Director del juego de Colección: propone de 1 a 3 misiones para la semana actual del usuario autenticado, pensadas en sus pendientes (usa open_tasks de get-game-director-context-tool). Tipos: specific_tasks {task_ids: 2–6 ids de open_tasks, target 1..n}, category_tasks {category: clave existente, target 2–5}, habit_groups {period: uno de habit_periods, target 2–5}, variety {target 2–5}. Cada misión lleva title (≤120, en español, amable, sin "debes", "obligatorio", "atrasado", "fallaste" ni "castigo"). Se acepta una sola propuesta por semana: si ya hay misiones del director responde "ya_propuestas" (es seguro reintentar). Solo reemplaza las misiones automáticas si aún no tienen avance; si alguna tiene avance responde "ya_con_avance" y no cambia nada. Recompensas: 1 ball al 50 %, 2 al 100 %; nunca castigan.')]
class ProposeGameWeekMissionsTool extends Tool
{
    public function handle(Request $request, SeasonManager $seasons): Response
    {
        $data = $request->validate(['missions' => ['required', 'array']]);

        $result = $seasons->proposeWeekMissions((int) Auth::id(), $data['missions']);
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $result['status'] === 'rejected' ? Response::error($json) : Response::text($json);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'missions' => $schema->array()
                ->description('Lista de 1 a 3 misiones: {type, title, target, task_ids?, category?, period?}.')
                ->required(),
        ];
    }
}
