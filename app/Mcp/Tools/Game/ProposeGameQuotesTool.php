<?php

namespace App\Mcp\Tools\Game;

use App\Models\Game\GameQuote;
use App\Models\User;
use App\Services\Game\GameSettings;
use App\Services\Game\QuoteValidator;
use App\Services\Game\WeekPlanner;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Director del juego de Colección: agrega de 7 a 14 frases para el integrante del día que aparece en el panel y el dashboard del usuario autenticado. Cada frase ≤140 caracteres, en español, cálida, general (sin datos personales) y sin "debes", "obligatorio", "atrasado", "fallaste" ni "castigo". Se acepta una sola propuesta por semana: si ya hay, responde "ya_propuestas" (es seguro reintentar). Las frases del director dejan de mostrarse a los 60 días. Consulta quotes_due en get-game-director-context-tool.')]
class ProposeGameQuotesTool extends Tool
{
    public function handle(Request $request, GameSettings $settings, QuoteValidator $validator): Response
    {
        $data = $request->validate(['quotes' => ['required', 'array']]);
        $userId = (int) Auth::id();

        $result = DB::transaction(function () use ($userId, $data, $settings, $validator): array {
            User::query()->whereKey($userId)->lockForUpdate()->first();

            if (! $settings->for($userId)['director_enabled']) {
                return ['status' => 'director_paused', 'message' => 'El director está en pausa.', 'errors' => []];
            }

            $week = WeekPlanner::weekStart(now())->toDateString();
            if (GameQuote::where('user_id', $userId)->whereDate('week_start', $week)->exists()) {
                return ['status' => 'ya_propuestas', 'message' => 'Ya hay frases del director para esta semana; se conservan.', 'errors' => []];
            }

            $checked = $validator->validate($data['quotes']);
            if ($checked['errors'] !== []) {
                return ['status' => 'rejected', 'message' => 'Frases rechazadas: '.implode(' ', $checked['errors']), 'errors' => $checked['errors']];
            }

            foreach ($checked['quotes'] as $text) {
                GameQuote::create(['user_id' => $userId, 'text' => $text, 'source' => GameQuote::SOURCE_DIRECTOR, 'week_start' => $week]);
            }

            return ['status' => 'ok', 'message' => count($checked['quotes']).' frases agregadas.', 'errors' => []];
        });

        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $result['status'] === 'rejected' ? Response::error($json) : Response::text($json);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'quotes' => $schema->array()->description('Lista de 7 a 14 frases (textos ≤140 caracteres).')->required(),
        ];
    }
}
