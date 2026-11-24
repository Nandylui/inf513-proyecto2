<?php

namespace App\Http\Controllers;

use App\Services\MenuService;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct(private MenuService $s) {}

    /** Menú dinámico del usuario autenticado, según los permisos de su rol. */
    public function index(Request $r)
    {
        return $this->s->paraUsuario($r->user()->load('rol'));
    }
}
