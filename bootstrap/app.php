<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            // Agregamos nuestro middleware de contexto de sucursal aquí
            // para que se ejecute en todas las rutas web después de iniciar sesión
            \App\Http\Middleware\EnsureBranchContext::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // lógica para el error 404
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            return Inertia::render('404Error', [ // Nombre del componente a renderizar
                'status' => $e->getStatusCode(),
            ])->toResponse($request)->setStatusCode($e->getStatusCode());
        });
    })->create();