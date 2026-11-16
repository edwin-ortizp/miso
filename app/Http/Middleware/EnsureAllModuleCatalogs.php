<?php

namespace App\Http\Middleware;

use App\Services\Catalogs\ModuleCatalogs;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Las herramientas MCP cruzan módulos: al usarlas se siembran todos los catálogos iniciales. */
class EnsureAllModuleCatalogs
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($userId = Auth::id()) {
            ModuleCatalogs::ensureAll((int) $userId);
        }

        return $next($request);
    }
}
