<?php

namespace App\Mcp\Tools\Game;

use App\Services\Game\SeasonManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Director del juego de Colección: propone la siguiente temporada del usuario autenticado. MiSo la valida contra rangos fijos y, si es válida y toca proponer, la activa sola; si no, la rechaza con motivos y el juego sigue igual. Responde "not_due" cuando la temporada actual aún tiene más de 7 días. Campos de "season": name (≤60), narrative (≤400), notes opcional (≤1000; ideas para revisar, nada se instala), weeks (2–6), missions {types: [category_tasks|habit_groups|variety], target: [min,max] entre 2 y 5, focus: hasta 2 claves de categoría}, team_bonus {clave de categoría o "habits": multiplicador 1.0–1.5} hasta 2, rarity_weights {common|uncommon|rare|legendary: 0–100, legendary ≤3}, featured_line (line_key existente). Nunca castiga la baja actividad: impulsa ámbitos poco atendidos con bonos, no con exigencias.')]
class ProposeGameSeasonTool extends Tool
{
    public function handle(Request $request, SeasonManager $seasons): Response
    {
        $data = $request->validate(['season' => ['required', 'array']]);

        $result = $seasons->propose((int) Auth::id(), $data['season'], 'mcp');
        $payload = [
            'status' => $result['status'],
            'message' => $result['message'],
            'season_id' => $result['season']?->id,
            'errors' => $result['errors'],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $result['status'] === 'rejected' ? Response::error($json) : Response::text($json);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'season' => $schema->object()
                ->description('Propuesta de temporada con name, narrative, notes, weeks, missions, team_bonus, rarity_weights y featured_line.')
                ->required(),
        ];
    }
}
