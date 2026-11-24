<?php

use App\Exceptions\ReglaNegocioException;
use App\Http\Middleware\ExigirPermiso;
use App\Http\Middleware\RegistrarAcceso;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permiso' => ExigirPermiso::class,
        ]);
        // Bitácora de accesos: usuario, IP y recurso de cada solicitud a la API.
        $middleware->api(append: [RegistrarAcceso::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Las reglas de negocio se devuelven como JSON legible (422 / 403 / 404).
        $exceptions->render(function (ReglaNegocioException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], $e->estado);
        });

        // Mensajes del framework en español (la API siempre responde JSON).
        $esApi = fn (Request $r) => $r->is('api/*') || $r->expectsJson();
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $esApi($r)
            ? response()->json(['message' => 'No autenticado. Inicie sesión para continuar.'], 401) : null);
        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $r) => $esApi($r)
            ? response()->json(['message' => $e instanceof NotFoundHttpException && $e->getMessage() !== '' && ! str_starts_with($e->getMessage(), 'No query results') && ! str_contains($e->getMessage(), 'could not be found')
                ? $e->getMessage() : 'El recurso solicitado no existe.'], 404) : null);
        $exceptions->render(fn (MethodNotAllowedHttpException $e, Request $r) => $esApi($r)
            ? response()->json(['message' => 'Método no permitido para este recurso.'], 405) : null);
        $exceptions->render(fn (TooManyRequestsHttpException $e, Request $r) => $esApi($r)
            ? response()->json(['message' => 'Demasiadas solicitudes. Intente nuevamente en unos instantes.'], 429) : null);
        $exceptions->render(fn (HttpExceptionInterface $e, Request $r) => $esApi($r) && $e->getStatusCode() === 403
            ? response()->json(['message' => $e->getMessage() ?: 'No tiene permiso para esta operación.'], 403) : null);

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
