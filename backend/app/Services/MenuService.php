<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\Usuario;
use App\Support\Permisos;

/** Menú dinámico: se construye desde la BD según los permisos concedidos al rol del usuario. */
class MenuService
{
    /** Árbol de opciones visibles para el usuario. */
    public function paraUsuario(Usuario $usuario): array
    {
        $concedidos = array_flip(Permisos::delUsuario($usuario));
        $items = Menu::with('permiso:id_permiso,codigo')->where('activo', true)->orderBy('orden')->orderBy('id_menu')->get();

        $visible = fn (Menu $m) => $m->permiso === null || isset($concedidos[$m->permiso->codigo]);
        $nodo = fn (Menu $m, array $hijos = []) => [
            'id_menu' => $m->id_menu, 'titulo' => $m->titulo, 'ruta' => $m->ruta, 'icono' => $m->icono, 'hijos' => $hijos,
        ];

        $hijosPorPadre = $items->whereNotNull('id_padre')->groupBy('id_padre');
        $arbol = [];
        foreach ($items->whereNull('id_padre') as $raiz) {
            $hijos = [];
            foreach ($hijosPorPadre->get($raiz->id_menu, collect()) as $h) {
                if ($visible($h)) {
                    $hijos[] = $nodo($h);
                }
            }
            if ($raiz->ruta === null) {
                // Un grupo solo aparece si el usuario puede ver al menos una de sus opciones.
                if ($hijos && $visible($raiz)) {
                    $arbol[] = $nodo($raiz, $hijos);
                }
            } elseif ($visible($raiz)) {
                $arbol[] = $nodo($raiz, $hijos);
            }
        }

        return $arbol;
    }

    /** Todas las opciones (para el administrador), ordenadas y con su permiso. */
    public function todas(): array
    {
        return Menu::with('permiso:id_permiso,codigo,descripcion')->orderByRaw('COALESCE(id_padre, id_menu), id_padre IS NOT NULL, orden')->get()
            ->map(fn (Menu $m) => [
                'id_menu' => $m->id_menu, 'id_padre' => $m->id_padre, 'titulo' => $m->titulo, 'ruta' => $m->ruta, 'icono' => $m->icono,
                'orden' => $m->orden, 'activo' => $m->activo, 'id_permiso' => $m->id_permiso, 'permiso' => $m->permiso?->codigo,
            ])->all();
    }
}
