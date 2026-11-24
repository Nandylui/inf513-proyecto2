<?php

namespace App\Http\Controllers;

use App\Correo\Catalogo;
use App\Correo\MensajeEntrante;
use App\Correo\ProcesadorCorreo;
use App\Models\CorreoLog;
use App\Support\Contexto;
use Illuminate\Http\Request;

/** Consola web del Sistema Vía Mail: catálogo, bitácora y simulación sin pasar por el servidor de correo. */
class CorreoController extends Controller
{
    public function catalogo()
    {
        return array_values(array_map(fn ($c) => collect($c)->except('handler')->all(), Catalogo::todos()));
    }

    public function log(Request $r)
    {
        return CorreoLog::orderByDesc('id_correo_log')->limit(min((int) $r->query('limite', 100), 500))->get();
    }

    /** Ejecuta un comando EXACTAMENTE como si llegara por correo, pero siempre con la identidad del usuario autenticado. */
    public function simular(Request $r, ProcesadorCorreo $p)
    {
        $d = $r->validate(['asunto' => 'required|string|max:500']);
        $resp = $p->procesar(MensajeEntrante::simulado($r->user()->correo, $d['asunto']));
        Contexto::web($r->ip());

        return ['exito' => $resp->exito, 'comando' => $resp->comando, 'asunto' => $resp->asunto, 'respuesta' => $resp->cuerpo];
    }
}
