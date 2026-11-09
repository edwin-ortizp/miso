<?php

namespace App\Mcp\Tools\Game;

use App\Services\Game\SeasonManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Director del juego de Colección: devuelve el resumen del juego del usuario autenticado (participación por semana, actividad por ámbito, misiones, colección, temporada activa y anteriores, límites permitidos y si toca proponer). Incluye open_tasks (hasta 40 tareas abiertas no privadas: id, código, título, categoría, vencimiento, tamaño y prioridad, sin descripciones), habit_periods y current_week para proponer misiones con propose-game-week-missions-tool. Úsalo antes de propose-game-season-tool. No incluye descripciones de tareas, tareas privadas, diario, salud ni contactos.')]
class GetGameDirectorContextTool extends Tool
{
    public function handle(Request $request, SeasonManager $seasons): Response
    {
        return Response::text(json_encode(
            $seasons->context((int) Auth::id()),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT,
        ));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
