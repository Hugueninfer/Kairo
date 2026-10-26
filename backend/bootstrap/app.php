<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum em modo SPA: o front autentica por cookie de sessão + CSRF, sem token no navegador.
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Mensagens padronizadas na API, sem expor nomes de models ou detalhes internos.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : null;

            if ($e instanceof ModelNotFoundException || $status === 404) {
                return response()->json(['message' => 'Recurso não encontrado.', 'code' => 'not_found'], 404);
            }

            if ($e instanceof AuthorizationException || $status === 403) {
                return response()->json(['message' => 'Você não tem permissão para esta ação.', 'code' => 'forbidden'], 403);
            }

            return null;
        });
    })->create();
