<?php

use App\Mcp\Servers\LifeTrackerServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

// Acepta los tokens de integración existentes (lt_ai_…) y los clientes OAuth.
Route::middleware(\App\Http\Middleware\ValidateMcpTransport::class)->group(function (): void {
    Mcp::web('miso', LifeTrackerServer::class)
        ->middleware(['mcp.auth', \App\Http\Middleware\EnsureAllModuleCatalogs::class, 'throttle:60,1']);

    // Ruta anterior al cambio de nombre: mantiene funcionando los conectores ya configurados.
    Mcp::web('life-tracker', LifeTrackerServer::class)
        ->middleware(['mcp.auth', \App\Http\Middleware\EnsureAllModuleCatalogs::class, 'throttle:60,1']);
});

/*
 * Descubrimiento OAuth y registro dinámico de clientes (RFC 8414, 9728 y 7591),
 * que trae el propio laravel/mcp. El grupo no es decorativo: routes/ai.php se
 * carga sin middleware, así que sin él /oauth/register quedaría sin throttling.
 */
Route::middleware(['throttle:20,1', \App\Http\Middleware\AddOAuthTokenAuthMethods::class])
    ->group(fn () => Mcp::oauthRoutes());
