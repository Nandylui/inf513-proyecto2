<?php

namespace App\Http\Controllers;

use App\Models\VisitaPagina;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Contador de visitas persistente por página (se muestra en el pie de página de cada vista). */
class VisitaController extends Controller
{
    /** Suma una visita a la página indicada y devuelve su contador y el total del sitio. */
    public function registrar(Request $r)
    {
        $d = $r->validate(['pagina' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9\/_\-\.:]+$/']]);
        $pagina = mb_strtolower(rtrim($d['pagina'], '/')) ?: '/';
        $ahora = now();

        $filas = DB::table('visita_pagina')->where('pagina', $pagina)
            ->update(['contador' => DB::raw('contador + 1'), 'ultima_visita' => $ahora]);
        if ($filas === 0) {
            try {
                DB::table('visita_pagina')->insert(['pagina' => $pagina, 'contador' => 1, 'ultima_visita' => $ahora]);
            } catch (QueryException) {
                // otra solicitud creó la fila al mismo tiempo: se suma sobre la existente
                DB::table('visita_pagina')->where('pagina', $pagina)->update(['contador' => DB::raw('contador + 1'), 'ultima_visita' => $ahora]);
            }
        }

        return [
            'pagina' => $pagina,
            'visitas' => (int) DB::table('visita_pagina')->where('pagina', $pagina)->value('contador'),
            'total' => (int) DB::table('visita_pagina')->sum('contador'),
        ];
    }

    /** Páginas más visitadas (para el panel analítico). */
    public function resumen()
    {
        return [
            'total' => (int) VisitaPagina::sum('contador'),
            'paginas' => VisitaPagina::orderByDesc('contador')->limit(15)->get(['pagina', 'contador', 'ultima_visita']),
        ];
    }
}
