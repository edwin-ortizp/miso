<?php

use App\Http\Middleware\AddGameEventHeader;
use App\Http\Middleware\AuthenticateIntegrationToken;
use App\Http\Middleware\AuthenticateMcpRequest;
use App\Http\Middleware\MeasurePerformance;
use App\Http\Middleware\TrackActiveDay;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(MeasurePerformance::class);
        $middleware->appendToGroup('web', AddGameEventHeader::class);
        $middleware->appendToGroup('web', TrackActiveDay::class);
        $middleware->alias([
            'integration.token' => AuthenticateIntegrationToken::class,
            'mcp.auth' => AuthenticateMcpRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'log-viewer/api/*'),
        );
    })->create();
