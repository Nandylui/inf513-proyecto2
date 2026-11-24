<?php

namespace App\Http\Controllers;

use App\Services\VotacionService;
use Illuminate\Http\Request;

class VotacionController extends Controller
{
    public function __construct(private VotacionService $s) {}

    public function asistencias(int $idSesion)
    {
        return ['asistentes' => $this->s->asistentes($idSesion)->load('usuario.rol'), 'quorum' => $this->s->quorum($idSesion)];
    }

    public function registrarAsistencia(Request $r, int $idSesion)
    {
        $d = $r->validate(['ci' => 'required|string', 'metodo' => 'nullable|in:HUELLA,FACIAL,MANUAL', 'dispositivo' => 'nullable|string|max:30']);

        return response()->json($this->s->registrarAsistencia($idSesion, $d['ci'], $d['metodo'] ?? 'HUELLA', $r->user(), $d['dispositivo'] ?? 'C5'), 201);
    }

    public function votar(Request $r, int $idSesion, int $idDocumento)
    {
        $d = $r->validate(['voto' => 'required|string', 'ci' => 'nullable|string']);
        $ci = $d['ci'] ?? $r->user()->ci;
        if ($ci !== $r->user()->ci && ! $r->user()->tieneRol('PRESIDENTE', 'SECRETARIO')) {
            throw \App\Exceptions\ReglaNegocioException::prohibido('Solo puede votar con su propio CI.');
        }
        $this->s->votar($idSesion, $ci, $idDocumento, $d['voto'], $r->user());

        return response()->json(['message' => 'Voto registrado.', 'conteo' => $this->s->conteo($idSesion, $idDocumento)], 201);
    }

    public function conteo(int $idSesion, int $idDocumento)
    {
        return $this->s->conteo($idSesion, $idDocumento);
    }

    public function cerrar(Request $r, int $idSesion, int $idDocumento)
    {
        return $this->s->cerrarVotacion($idSesion, $idDocumento, $r->user());
    }
}
