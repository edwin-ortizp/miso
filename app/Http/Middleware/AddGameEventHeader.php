<?php

namespace App\Http\Middleware;

use App\Support\Game\RequestGameActivity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marca la respuesta con `X-Game-Event: 1` si durante la petición se creó
 * algún evento del juego del usuario (CHG-041).
 */
class AddGameEventHeader
{
    public const HEADER = 'X-Game-Event';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (app(RequestGameActivity::class)->touched()) {
            $response->headers->set(self::HEADER, '1');
        }

        return $response;
    }
}
