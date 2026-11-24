<?php

namespace App\Http\Middleware;

use App\Support\Permisos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExigirPermiso
{
    public function handle(Request $request, Closure $next, string $accion): Response
    {
        $usuario = $request->user();
        abort_if(! $usuario || $usuario->estado !== 'ACTIVO', 401, 'No autenticado.');
        Permisos::exigir($usuario, $accion);

        return $next($request);
    }
}
