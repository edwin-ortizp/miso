<?php

namespace App\Http\Middleware;

use App\Services\Experience\PersonalExperience;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only the authenticated application route group; APIs and MCP are independent.
        if ($request->user() && ! $request->routeIs('onboarding', 'settings', 'settings.section')
            && app(PersonalExperience::class)->required((int) $request->user()->id)) {
            return redirect()->route('onboarding');
        }
        if ($request->routeIs('settings.section') && $request->route('section') !== 'profile'
            && app(PersonalExperience::class)->required((int) $request->user()->id)) {
            return redirect()->route('onboarding');
        }

        return $next($request);
    }
}
