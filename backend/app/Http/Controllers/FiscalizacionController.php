<?php

namespace App\Http\Controllers;

use App\Services\FiscalizacionService;
use Illuminate\Http\Request;

class FiscalizacionController extends Controller
{
    public function __construct(private FiscalizacionService $s) {}

    public function auditoria(Request $r)
    {
        return $this->s->auditoria($r->only(['ci', 'entidad', 'accion', 'canal']), (int) $r->query('limite', 200));
    }

    public function notificaciones(Request $r)
    {
        return $this->s->notificaciones($r->user(), $r->boolean('no_leidas'));
    }

    public function leer(Request $r, int $id)
    {
        return $this->s->marcarLeida($id, $r->user());
    }

    public function enviar(Request $r)
    {
        $d = $r->validate(['destino' => 'required|string', 'asunto' => 'required|string|max:200', 'mensaje' => 'required|string']);

        return response()->json(['enviadas' => $this->s->enviar($d['destino'], $d['asunto'], $d['mensaje'], $r->user())], 201);
    }

    // ---- públicos (sin autenticación): Art. 56 y 57 ----
    public function sesionesPublicas()
    {
        return $this->s->sesionesPublicas();
    }

    public function actasPublicas()
    {
        return $this->s->actasPublicas();
    }

    public function actaPublica(int $idSesion)
    {
        return $this->s->actaPublica($idSesion);
    }

    public function resolucionesPublicas()
    {
        return $this->s->resolucionesPublicas();
    }
}
