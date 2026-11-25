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
    /** Rutas que nunca se registran aquí: el ingreso y la salida tienen sus propios eventos (LOGIN_*, LOGOUT). */
    private const OMITIR = ['api/auth/me', 'api/auth/login', 'api/auth/logout'];

    /**
     * Consultas automáticas de la propia interfaz (contadores, menú, campana, buscador, paneles de la bitácora):
     * se omiten cuando salen bien para no llenar el registro de ruido, pero un 401/403 sobre ellas SÍ se registra.
     */
    private const OMITIR_SI_EXITOSAS = ['POST api/visitas', 'GET api/visitas/resumen', 'GET api/buscar', 'GET api/menu', 'GET api/notificaciones', 'GET api/bitacora', 'GET api/bitacora/resumen'];

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
        if (in_array($request->method().' '.$uri, self::OMITIR_SI_EXITOSAS, true) && ! in_array($estado, [401, 403], true)) {
            return;
        }
        $evento = in_array($estado, [401, 403], true) ? BitacoraService::DENEGADO : BitacoraService::ACCESO;

        BitacoraService::registrar($evento, $request, $request->user(), null, $request->method().' '.$uri, $estado);
    }
}
