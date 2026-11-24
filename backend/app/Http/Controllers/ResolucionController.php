<?php

namespace App\Http\Controllers;

use App\Services\ResolucionService;
use Illuminate\Http\Request;

class ResolucionController extends Controller
{
    public function __construct(private ResolucionService $s) {}

    public function index(Request $r)
    {
        return $this->s->listar($r->query('q'));
    }

    public function show(int $id)
    {
        return $this->s->obtener($id);
    }

    public function promulgar(Request $r, int $id)
    {
        return $this->s->promulgar($id, $r->user());
    }

    public function vetar(Request $r, int $id)
    {
        return $this->s->vetar($id, $r->validate(['fundamento' => 'required|string|min:10'])['fundamento'], $r->user());
    }

    public function mantener(Request $r, int $id)
    {
        $d = $r->validate(['votos_favor' => 'required|integer|min:0', 'presentes' => 'required|integer|min:1']);

        return $this->s->resolverVeto($id, $d['votos_favor'], $d['presentes'], $r->user());
    }

    public function publicar(Request $r, int $id)
    {
        return $this->s->publicar($id, $r->validate(['gaceta' => 'required|string'])['gaceta'], $r->user());
    }

    public function acta(int $idSesion)
    {
        return $this->s->acta($idSesion);
    }

    public function generarActa(Request $r, int $idSesion)
    {
        return response()->json($this->s->generarActa($idSesion, $r->user()), 201);
    }
}
