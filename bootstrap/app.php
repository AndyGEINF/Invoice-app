<?php

use App\Http\DomainErrors;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Reglas de negocio (emisor incompleto, factura inmutable, fechas…) → 422
        // con errors.domain; nunca un 500 (contracts/web-routes.md).
        $exceptions->map(DomainException::class, DomainErrors::toValidation(...));
    })->create();
