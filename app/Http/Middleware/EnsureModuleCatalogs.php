<?php

namespace App\Http\Middleware;

use App\Services\Catalogs\ModuleCatalogs;
use App\Services\Experience\PersonalExperience;
use App\Support\CategoryCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Siembra el catálogo inicial del módulo que la persona abre por primera vez.
 * Mi día y el panel muestran tarjetas de los módulos visibles, así que también
 * siembran esos módulos.
 */
class EnsureModuleCatalogs
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->user()?->id;
        if ($userId && $request->isMethodSafe()) {
            foreach ($this->modulesFor($request, $userId) as $module) {
                ModuleCatalogs::ensure($userId, $module);
            }
        }

        return $next($request);
    }

    /** @return list<string> */
    private function modulesFor(Request $request, int $userId): array
    {
        $pending = array_diff(ModuleCatalogs::MODULES, ModuleCatalogs::provisioned($userId));
        if (! $pending) {
            return [];
        }
        if ($request->routeIs('categories.settings')) {
            $module = CategoryCatalog::DOMAINS[$request->route('domain')][2] ?? null;

            return $module ? [$module] : [];
        }
        $experience = app(PersonalExperience::class);
        $home = $request->routeIs(...(array) config('modules.home.patterns', []));

        return array_values(array_filter($pending, function (string $module) use ($request, $home, $experience, $userId) {
            $patterns = (array) config("modules.$module.patterns", []);

            return ($patterns && $request->routeIs(...$patterns))
                || ($home && $experience->visible($module, $userId));
        }));
    }
}
