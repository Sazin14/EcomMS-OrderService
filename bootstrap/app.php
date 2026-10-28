<?php

use App\Http\Middleware\CheckAuthorization;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureCustomer;
use App\Http\Middleware\VerifyInternalKey;
use App\Http\Middleware\VerifyJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',          // every route in routes/api.php gets the /api prefix
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => VerifyJwt::class,          // checks the token, sets user_id / user_type
            'customer' => EnsureCustomer::class,     // token type must be "customer"
            'authz' => CheckAuthorization::class,    // staff only: loads permissions from Redis
            'permission' => CheckPermission::class,  // permission:order.view
            'internal' => VerifyInternalKey::class,  // X-Internal-Key (service to service)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
