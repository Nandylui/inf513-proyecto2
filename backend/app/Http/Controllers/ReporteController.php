<?php

namespace App\Http\Controllers;

use App\Services\ReporteService;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    public function __construct(private ReporteService $s) {}

    public function asistencia(Request $r)
    {
        return $this->s->asistencia($r->query('sesion'));
    }

    public function resoluciones(Request $r)
    {
        return $this->s->resoluciones($r->query('estado'));
    }

    public function tramites()
    {
        return $this->s->tramites();
    }

    public function votaciones(int $idSesion)
    {
        return $this->s->votaciones($idSesion);
    }

    public function estadisticas()
    {
        return $this->s->estadisticas();
    }

    /** Descarga CSV (UTF-8 con BOM, abre bien en Excel) de cualquiera de los reportes. */
    public function csv(Request $r, string $tipo)
    {
        $rep = match ($tipo) {
            'asistencia' => $this->s->asistencia($r->query('sesion')),
            'resoluciones' => $this->s->resoluciones($r->query('estado')),
            'tramites' => $this->s->tramites(),
            'votaciones' => $this->s->votaciones((int) $r->query('sesion')),
            'estadisticas' => $this->s->estadisticas(),
            default => abort(404),
        };

        return response()->streamDownload(function () use ($rep) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, $rep['columnas'], ';');
            foreach ($rep['filas'] as $f) {
                fputcsv($o, array_map(fn ($c) => is_bool($c) ? ($c ? 'SI' : 'NO') : $c, $f), ';');
            }
            fclose($o);
        }, "reporte_{$tipo}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
