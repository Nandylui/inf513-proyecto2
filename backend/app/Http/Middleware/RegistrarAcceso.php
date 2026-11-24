<?php

namespace App\Http\Middleware;

use App\Services\BitacoraService;
use App\Support\Contexto;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra en la bitácora cada acceso a un recurso de la API (usuario, IP, recurso y resultado).
 * Se ejecuta al terminar la respuesta para no retrasarla.
 */
class RegistrarAcceso
{
    /** Rutas que no se registran porque son ruido de la propia interfaz o se registran por otro medio. */
    private const OMITIR = ['api/visitas', 'api/visitas/resumen', 'api/buscar', 'api/menu', 'api/auth/me', 'api/auth/login', 'api/auth/logout', 'api/bitacora', 'api/bitacora/resumen'];

    public function handle(Request $request, Closure $next): Response
    {
        Contexto::web($request->ip());   // la auditoría de negocio también guarda la IP

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('OPTIONS') || ! ($route = $request->route())) {
            return;
        }
        $uri = $route->uri();
        if (in_array($uri, self::OMITIR, true)) {
            return;
        }
        $estado = $response->getStatusCode();
        $evento = in_array($estado, [401, 403], true) ? BitacoraService::DENEGADO : BitacoraService::ACCESO;

        BitacoraService::registrar($evento, $request, $request->user(), null, $request->method().' '.$uri, $estado);
    }
}
