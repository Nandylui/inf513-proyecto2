<?php

namespace App\Http\Controllers;

use App\Services\BitacoraService;
use Illuminate\Http\Request;

class BitacoraController extends Controller
{
    public function __construct(private BitacoraService $s) {}

    public function index(Request $r)
    {
        $r->validate([
            'evento' => 'nullable|in:LOGIN_OK,LOGIN_FALLIDO,LOGOUT,ACCESO,DENEGADO',
            'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d', 'por_pagina' => 'nullable|integer|between:5,100',
        ]);

        return $this->s->listar($r->only(['evento', 'ip', 'q', 'desde', 'hasta']), (int) $r->query('por_pagina', 25));
    }

    public function resumen(Request $r)
    {
        return $this->s->resumen((int) $r->query('dias', 14));
    }
}
