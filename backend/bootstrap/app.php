<?php

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Autorização dos canais de WebSocket (Reverb) dentro da API da empresa ativa:
    // mesma sessão do Sanctum, mesmo CSRF e mesmo filtro de tenant das outras rotas.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api/v1/company',
        'middleware' => ['api', 'auth:sanctum', 'tenant'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum em modo SPA: o front autentica por cookie de sessão + CSRF, sem token no navegador.
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->append(SecurityHeaders::class);

        // A empresa ativa precisa estar definida antes do route model binding,
        // para os models de empresa já serem buscados com o filtro de tenant.
        $middleware->alias(['tenant' => ResolveTenant::class]);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class);
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

            if ($e instanceof AuthenticationException) {
                return response()->json(['message' => 'Você precisa estar autenticado.', 'code' => 'unauthenticated'], 401);
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
