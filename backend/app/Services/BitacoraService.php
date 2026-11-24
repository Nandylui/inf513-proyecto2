<?php

namespace App\Services;

use App\Models\BitacoraAcceso;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bitácora de accesos: inicios de sesión exitosos y fallidos, cierres de sesión, accesos denegados,
 * dirección IP y recursos consultados. Es independiente de la auditoría de negocio (tabla auditoria).
 */
class BitacoraService
{
    public const LOGIN_OK = 'LOGIN_OK';

    public const LOGIN_FALLIDO = 'LOGIN_FALLIDO';

    public const LOGOUT = 'LOGOUT';

    public const ACCESO = 'ACCESO';

    public const DENEGADO = 'DENEGADO';

    public static function registrar(string $evento, ?Request $request = null, ?Usuario $usuario = null, ?string $correo = null,
        ?string $recurso = null, ?int $estadoHttp = null, ?string $detalle = null): void
    {
        try {
            BitacoraAcceso::create([
                'id_usuario' => $usuario?->id_usuario,
                'correo' => $correo !== null ? mb_substr($correo, 0, 190) : $usuario?->correo,
                'evento' => $evento,
                'recurso' => $recurso !== null ? mb_substr($recurso, 0, 200) : null,
                'metodo' => $request?->method(),
                'estado_http' => $estadoHttp,
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'detalle' => $detalle !== null ? mb_substr($detalle, 0, 255) : null,
                'fecha' => now(),
            ]);
        } catch (Throwable $e) {
            // La bitácora nunca debe impedir que el sistema responda.
            Log::warning('No se pudo registrar la bitácora: '.$e->getMessage());
        }
    }

    /** Listado paginado con filtros (evento, correo/usuario, IP, recurso y rango de fechas). */
    public function listar(array $f, int $porPagina = 25): array
    {
        $q = BitacoraAcceso::with('usuario:id_usuario,nombres,apellidos,ci')->orderByDesc('id_bitacora');
        if (! empty($f['evento'])) {
            $q->where('evento', mb_strtoupper($f['evento']));
        }
        if (! empty($f['ip'])) {
            $q->where('ip', 'like', trim($f['ip']).'%');
        }
        if (! empty($f['q'])) {
            $like = '%'.mb_strtolower(trim($f['q'])).'%';
            $q->where(fn ($w) => $w->whereRaw('LOWER(correo) LIKE ?', [$like])->orWhereRaw('LOWER(recurso) LIKE ?', [$like]));
        }
        if (! empty($f['desde'])) {
            $q->where('fecha', '>=', $f['desde'].' 00:00:00');
        }
        if (! empty($f['hasta'])) {
            $q->where('fecha', '<=', $f['hasta'].' 23:59:59');
        }
        $p = $q->paginate(min(max($porPagina, 5), 100));

        return [
            'datos' => $p->items(),
            'pagina' => $p->currentPage(),
            'ultima_pagina' => $p->lastPage(),
            'total' => $p->total(),
        ];
    }

    /** Indicadores de seguridad y tráfico para el panel del administrador. */
    public function resumen(int $dias = 14): array
    {
        $dias = min(max($dias, 1), 90);
        $desde = now()->subDays($dias - 1)->startOfDay();
        $hoy = now()->startOfDay();

        $porDiaEvento = DB::table('bitacora_acceso')
            ->selectRaw('DATE(fecha) as dia, evento, COUNT(*) as n')
            ->where('fecha', '>=', $desde)
            ->groupBy('dia', 'evento')
            ->get();

        $serie = [];
        for ($i = 0; $i < $dias; $i++) {
            $dia = $desde->copy()->addDays($i)->format('Y-m-d');
            $serie[$dia] = ['dia' => $dia, 'ingresos' => 0, 'fallidos' => 0, 'accesos' => 0];
        }
        foreach ($porDiaEvento as $r) {
            $d = (string) $r->dia;
            if (! isset($serie[$d])) {
                continue;
            }
            match ($r->evento) {
                self::LOGIN_OK => $serie[$d]['ingresos'] += $r->n,
                self::LOGIN_FALLIDO => $serie[$d]['fallidos'] += $r->n,
                self::ACCESO => $serie[$d]['accesos'] += $r->n,
                default => null,
            };
        }

        $topRecursos = DB::table('bitacora_acceso')
            ->select('recurso', DB::raw('COUNT(*) as accesos'))
            ->where('evento', self::ACCESO)->whereNotNull('recurso')->where('fecha', '>=', $desde)
            ->groupBy('recurso')->orderByDesc('accesos')->limit(10)->get();

        $topIps = DB::table('bitacora_acceso')
            ->select('ip', DB::raw('COUNT(*) as eventos'), DB::raw("SUM(CASE WHEN evento = 'LOGIN_FALLIDO' THEN 1 ELSE 0 END) as fallidos"))
            ->whereNotNull('ip')->where('fecha', '>=', $desde)
            ->groupBy('ip')->orderByDesc('eventos')->limit(8)->get();

        $fallidosPorCorreo = DB::table('bitacora_acceso')
            ->select('correo', DB::raw('COUNT(*) as intentos'))
            ->where('evento', self::LOGIN_FALLIDO)->where('fecha', '>=', $desde)->whereNotNull('correo')
            ->groupBy('correo')->orderByDesc('intentos')->limit(8)->get();

        $conteo = fn (string $evento, $desdeF) => DB::table('bitacora_acceso')->where('evento', $evento)->where('fecha', '>=', $desdeF)->count();

        return [
            'dias' => $dias,
            'ingresos_hoy' => $conteo(self::LOGIN_OK, $hoy),
            'fallidos_hoy' => $conteo(self::LOGIN_FALLIDO, $hoy),
            'accesos_periodo' => $conteo(self::ACCESO, $desde),
            'denegados_periodo' => $conteo(self::DENEGADO, $desde),
            'serie_diaria' => array_values($serie),
            'recursos_mas_accedidos' => $topRecursos,
            'ips_mas_activas' => $topIps,
            'fallidos_por_correo' => $fallidosPorCorreo,
        ];
    }
}
