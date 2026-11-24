<?php

namespace App\Http\Controllers;

use App\Services\UsuarioService;
use Illuminate\Http\Request;

class UsuarioController extends Controller
{
    public function __construct(private UsuarioService $s) {}

    public function index(Request $r)
    {
        return $this->s->listar($r->query('q'));
    }

    public function store(Request $r)
    {
        $d = $r->validate(['ci' => 'required|string', 'nombres' => 'required|string', 'apellidos' => 'required|string', 'correo' => 'required|email', 'rol' => 'required|string', 'telefono' => 'nullable|string', 'password' => 'nullable|string|min:8']);

        return response()->json($this->s->crear($d, $r->user()), 201);
    }

    public function update(Request $r, string $ci)
    {
        $d = $r->validate(['campo' => 'required|string', 'valor' => 'required|string']);

        return $this->s->modificar($ci, $d['campo'], $d['valor'], $r->user());
    }

    public function destroy(Request $r, string $ci)
    {
        return $this->s->desactivar($ci, $r->user());
    }

    public function roles()
    {
        return $this->s->roles();
    }
}
