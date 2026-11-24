<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Resolucion;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Models\VisitaPagina;
use App\Support\Permisos;
use Illuminate\Support\Facades\DB;

/** Panel analítico: actividad del negocio del Consejo y flujo de accesos al sitio. */
class DashboardService
{
    public function __construct(private MiembroService $miembros, private BitacoraService $bitacora, private PagoService $pagos) {}

    public function datos(Usuario $u): array
    {
        $resoluciones = Resolucion::get(['estado', 'resultado', 'votos_favor', 'votos_contra', 'votos_blanco']);
        $aprobadas = $resoluciones->where('resultado', 'APROBADA')->count();

        $kpis = [
            'usuarios_activos' => Usuario::where('estado', 'ACTIVO')->count(),
            'mandatos_vigentes' => $this->miembros->vigentes()->count(),
            'sesiones_programadas' => Sesion::where('estado', 'PROGRAMADA')->count(),
            'documentos_en_revision' => Documento::whereIn('estado', ['RECIBIDO', 'EN_REVISION', 'OBSERVADO'])->count(),
            'resoluciones' => $resoluciones->count(),
            'tasa_aprobacion' => $resoluciones->count() ? round($aprobadas * 100 / $resoluciones->count()) : 0,
            'notificaciones_sin_leer' => Notificacion::where('id_usuario', $u->id_usuario)->where('leida', false)->count(),
        ];

        $out = [
            'kpis' => $kpis,
            'sesiones_por_mes' => $this->sesionesPorMes(),
            'documentos_por_tramite' => $this->documentosPorTramite(),
            'resoluciones_por_estado' => $resoluciones->groupBy('estado')->map(fn ($g, $e) => ['estado' => $e, 'total' => $g->count()])->values(),
            'votos' => [
                ['voto' => 'A favor', 'total' => (int) $resoluciones->sum('votos_favor')],
                ['voto' => 'En contra', 'total' => (int) $resoluciones->sum('votos_contra')],
                ['voto' => 'Blanco', 'total' => (int) $resoluciones->sum('votos_blanco')],
            ],
            'asistencia_por_sesion' => $this->asistenciaPorSesion(),
            'pagos' => null,
            'accesos' => null,
        ];

        if (Permisos::puede($u, 'pago.gestionar')) {
            $out['pagos'] = $this->pagos->resumen();
        } elseif (Permisos::puede($u, 'pago.propio')) {
            $mios = Pago::where('id_usuario', $u->id_usuario)->get();
            $out['pagos'] = ['propios' => true, 'recaudado' => round($mios->where('estado', 'APROBADO')->sum('monto'), 2), 'aprobados' => $mios->where('estado', 'APROBADO')->count(), 'rechazados' => $mios->where('estado', 'RECHAZADO')->count()];
        }

        if (Permisos::puede($u, 'bitacora.ver')) {
            $out['accesos'] = $this->bitacora->resumen(14) + [
                'visitas_total' => (int) VisitaPagina::sum('contador'),
                'paginas_mas_visitadas' => VisitaPagina::orderByDesc('contador')->limit(8)->get(['pagina', 'contador']),
            ];
        }

        return $out;
    }

    /** Sesiones de los últimos 6 meses y los 2 próximos. */
    private function sesionesPorMes(): array
    {
        $inicio = now()->startOfMonth()->subMonths(6);
        $meses = [];
        for ($i = 0; $i <= 8; $i++) {
            $m = $inicio->copy()->addMonths($i);
            $meses[$m->format('Y-m')] = ['mes' => $m->format('Y-m'), 'etiqueta' => ucfirst($m->locale('es')->translatedFormat('M y')), 'total' => 0];
        }
        foreach (Sesion::where('fecha_hora', '>=', $inicio)->get(['fecha_hora']) as $s) {
            $k = $s->fecha_hora->format('Y-m');
            if (isset($meses[$k])) {
                $meses[$k]['total']++;
            }
        }

        return array_values($meses);
    }

    private function documentosPorTramite(): array
    {
        return Documento::with('tipoTramite:id_tipo_tramite,nombre')->get(['id_documento', 'id_tipo_tramite'])
            ->groupBy(fn ($d) => $d->tipoTramite?->nombre ?? 'SIN TIPO')
            ->map(fn ($g, $t) => ['tramite' => $t, 'total' => $g->count()])->sortByDesc('total')->values()->take(8)->all();
    }

    private function asistenciaPorSesion(): array
    {
        $sesiones = Sesion::whereIn('estado', ['EN_CURSO', 'FINALIZADA'])->orderByDesc('fecha_hora')->limit(8)->get(['id_sesion', 'fecha_hora', 'quorum_requerido']);
        $conteo = Asistencia::whereIn('id_sesion', $sesiones->pluck('id_sesion'))->select('id_sesion', DB::raw('COUNT(*) as n'))->groupBy('id_sesion')->pluck('n', 'id_sesion');

        return $sesiones->reverse()->values()->map(fn ($s) => [
            'sesion' => '#'.$s->id_sesion, 'presentes' => (int) ($conteo[$s->id_sesion] ?? 0), 'quorum' => (int) ($s->quorum_requerido ?? 0),
        ])->all();
    }
}
