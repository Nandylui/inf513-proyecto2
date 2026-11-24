<?php

namespace App\Http\Controllers;

use App\Services\MiembroService;
use App\Services\SesionService;
use Illuminate\Http\Request;

class MiembroController extends Controller
{
    public function __construct(private MiembroService $s, private SesionService $sesiones) {}

    public function index(Request $r)
    {
        return $this->s->listar($r->query('q'));
    }

    public function store(Request $r)
    {
        $d = $r->validate(['ci_titular' => 'required|string', 'ci_suplente' => 'nullable|string', 'carrera' => 'required|string', 'estamento' => 'required|string', 'fecha_inicio' => 'required|string', 'fecha_fin' => 'required|string']);

        return response()->json($this->s->registrar($d['ci_titular'], $d['ci_suplente'] ?? null, $d['carrera'], $d['estamento'], $d['fecha_inicio'], $d['fecha_fin'], $r->user())->load(['titular', 'suplente']), 201);
    }

    public function update(Request $r, int $id)
    {
        $d = $r->validate(['campo' => 'required|string', 'valor' => 'required|string']);

        return $this->s->modificar($id, $d['campo'], $d['valor'], $r->user())->load(['titular', 'suplente']);
    }

    public function concluir(Request $r, int $id)
    {
        return $this->s->concluir($id, $r->user());
    }

    public function panel(Request $r)
    {
        $sesion = $r->query('sesion') ? $this->sesiones->obtener($r->query('sesion')) : null;

        return $this->s->panel($sesion);
    }

    public function quorum(int $idSesion)
    {
        return $this->s->quorum($this->sesiones->obtener($idSesion), true);
    }
}
