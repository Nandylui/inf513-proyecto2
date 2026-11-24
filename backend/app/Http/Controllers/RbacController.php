<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Menu;
use App\Models\Permiso;
use App\Models\Rol;
use App\Services\AuditoriaService;
use App\Services\MenuService;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Administración de la matriz de acceso (rol × permiso) y del menú dinámico. Solo para el administrador. */
class RbacController extends Controller
{
    public function __construct(private MenuService $menus) {}

    public function matriz()
    {
        $roles = Rol::orderBy('id_rol')->get(['id_rol', 'nombre', 'descripcion']);
        $concedidos = DB::table('rol_permiso')->get()->groupBy('id_permiso');
        $porId = $roles->pluck('nombre', 'id_rol');

        $permisos = Permiso::orderBy('modulo')->orderBy('codigo')->get()->map(fn ($p) => [
            'id_permiso' => $p->id_permiso, 'codigo' => $p->codigo, 'descripcion' => $p->descripcion, 'modulo' => $p->modulo,
            'roles' => $concedidos->get($p->id_permiso, collect())->map(fn ($c) => $porId[$c->id_rol] ?? null)->filter()->values(),
        ]);

        return ['roles' => $roles, 'permisos' => $permisos, 'intocables_admin' => Permisos::INTOCABLES_ADMIN];
    }

    /** Concede o revoca un permiso a un rol. */
    public function alternar(Request $r)
    {
        $d = $r->validate([
            'rol' => 'required|string|exists:rol,nombre',
            'permiso' => 'required|string|exists:permiso,codigo',
            'concedido' => 'required|boolean',
        ]);
        if (! $d['concedido'] && $d['rol'] === 'ADMINISTRADOR' && in_array($d['permiso'], Permisos::INTOCABLES_ADMIN, true)) {
            throw ReglaNegocioException::invalida('No se puede quitar al ADMINISTRADOR el permiso '.$d['permiso'].': el sistema quedaría sin administración.');
        }
        $idRol = Rol::where('nombre', $d['rol'])->value('id_rol');
        $idPermiso = Permiso::where('codigo', $d['permiso'])->value('id_permiso');
        if ($d['concedido']) {
            DB::table('rol_permiso')->insertOrIgnore(['id_rol' => $idRol, 'id_permiso' => $idPermiso]);
        } else {
            DB::table('rol_permiso')->where(['id_rol' => $idRol, 'id_permiso' => $idPermiso])->delete();
        }
        Permisos::limpiarCache();
        AuditoriaService::registrar($r->user(), $d['concedido'] ? 'PERMISO_CONCEDIDO' : 'PERMISO_REVOCADO', 'rol', $idRol, ['rol' => $d['rol'], 'permiso' => $d['permiso']]);

        return ['rol' => $d['rol'], 'permiso' => $d['permiso'], 'concedido' => $d['concedido']];
    }

    public function menus()
    {
        return $this->menus->todas();
    }

    public function crearMenu(Request $r)
    {
        $m = Menu::create($this->datosMenu($r));
        AuditoriaService::registrar($r->user(), 'MENU_CREADO', 'menu', $m->id_menu, ['titulo' => $m->titulo]);

        return response()->json($m, 201);
    }

    public function actualizarMenu(Request $r, int $id)
    {
        $m = Menu::findOrFail($id);
        $m->update($this->datosMenu($r, $id));
        AuditoriaService::registrar($r->user(), 'MENU_MODIFICADO', 'menu', $m->id_menu, ['titulo' => $m->titulo]);

        return $m->fresh();
    }

    public function eliminarMenu(Request $r, int $id)
    {
        $m = Menu::findOrFail($id);
        $m->delete();
        AuditoriaService::registrar($r->user(), 'MENU_ELIMINADO', 'menu', $id, ['titulo' => $m->titulo]);

        return ['message' => 'Opción de menú eliminada.'];
    }

    private function datosMenu(Request $r, ?int $id = null): array
    {
        $d = $r->validate([
            'titulo' => 'required|string|max:80',
            'ruta' => ['nullable', 'string', 'max:120', 'regex:/^\/[A-Za-z0-9\/_\-]*$/'],
            'icono' => 'nullable|string|max:40',
            'orden' => 'required|integer|between:0,999',
            'id_padre' => 'nullable|integer|exists:menu,id_menu',
            'permiso' => 'nullable|string|exists:permiso,codigo',
            'activo' => 'required|boolean',
        ]);
        if ($id !== null && ($d['id_padre'] ?? null) === $id) {
            throw ReglaNegocioException::invalida('Una opción no puede ser su propio padre.');
        }

        return [
            'titulo' => trim($d['titulo']), 'ruta' => $d['ruta'] ?? null, 'icono' => $d['icono'] ?? null, 'orden' => $d['orden'],
            'id_padre' => $d['id_padre'] ?? null, 'activo' => $d['activo'],
            'id_permiso' => ! empty($d['permiso']) ? Permiso::where('codigo', $d['permiso'])->value('id_permiso') : null,
        ];
    }
}
