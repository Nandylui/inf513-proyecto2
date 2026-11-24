<?php

namespace App\Http\Controllers;

use App\Services\SesionService;
use Illuminate\Http\Request;

class SesionController extends Controller
{
    public function __construct(private SesionService $s) {}

    public function index(Request $r)
    {
        return $this->s->listar($r->query('q'));
    }

    public function show(int $id)
    {
        return $this->s->obtener($id);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['fecha_hora' => 'required|string', 'lugar' => 'required|string', 'tipo' => 'required|string', 'orden_del_dia' => 'nullable', 'solicitantes' => 'nullable|integer|min:0']);

        return response()->json($this->s->convocar($d, $r->user()), 201);
    }

    public function update(Request $r, int $id)
    {
        $d = $r->validate(['campo' => 'required|string', 'valor' => 'required|string']);

        return $this->s->modificar($id, $d['campo'], $d['valor'], $r->user());
    }

    public function estado(Request $r, int $id)
    {
        return $this->s->cambiarEstado($id, $r->validate(['estado' => 'required|string'])['estado'], $r->user());
    }

    public function punto(Request $r, int $id)
    {
        $d = $r->validate(['titulo' => 'required|string', 'descripcion' => 'nullable|string']);

        return response()->json($this->s->agregarPunto($id, $d['titulo'], $d['descripcion'] ?? null, $r->user()), 201);
    }

    public function excusas(int $id)
    {
        return $this->s->excusas($id);
    }

    /** multipart/form-data: ci_titular, motivo, firmado (bool), archivo (PDF/imagen, máx. 5 MB) */
    public function excusar(Request $r, int $id)
    {
        $d = $r->validate(['ci_titular' => 'nullable|string', 'motivo' => 'required|string|min:5', 'firmado' => 'nullable|boolean', 'archivo' => 'nullable|file|max:5120|mimes:pdf,jpg,jpeg,png']);
        $res = $this->s->registrarExcusa($id, $d['ci_titular'] ?? $r->user()->ci, $d['motivo'], $r->user(), $r->file('archivo'), (bool) ($d['firmado'] ?? false));

        return response()->json(['excusa' => $res['excusa'], 'habilitacion' => $res['habilitacion']?->load('habilitado'), 'advertencia' => $res['advertencia'] ?? null], 201);
    }

    public function habilitar(Request $r, int $id)
    {
        $d = $r->validate(['ci_titular' => 'required|string', 'motivo' => 'required|string']);

        return response()->json($this->s->habilitarSuplente($id, $d['ci_titular'], $d['motivo'], $r->user())->load('habilitado'), 201);
    }

    public function habilitados(int $id)
    {
        return $this->s->habilitados($id);
    }
}
