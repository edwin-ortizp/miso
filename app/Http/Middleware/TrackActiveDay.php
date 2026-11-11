<?php

namespace App\Http\Middleware;

use App\Models\UserActiveDay;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** CHG-067: registra el día activo una sola vez por usuario y día, sin consultar en cada request. */
class TrackActiveDay
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = (int) $request->user()?->getKey();
        if ($userId > 0 && $request->isMethod('GET')) {
            $date = now()->toDateString();
            if (Cache::add("active-day:{$userId}:{$date}", true, now()->endOfDay())) {
                try {
                    UserActiveDay::touch($userId, UserActiveDay::channel($request->query('via')), $date);
                } catch (\Throwable $e) {
                    Cache::forget("active-day:{$userId}:{$date}");
                    report($e);
                }
            }
        }

        return $next($request);
    }
}
