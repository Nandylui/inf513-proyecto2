<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permiso;
use Illuminate\Database\Seeder;

/**
 * Menú dinámico del sistema. Cada opción exige un permiso (null = cualquier usuario autenticado);
 * el frontend solo muestra lo que el rol activo puede usar. Idempotente.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $estructura = [
            ['Inicio', '/panel', 'inicio', null, []],
            ['Gestión del Consejo', null, 'consejo', null, [
                ['Usuarios y roles', '/usuarios', 'usuarios', 'usuarios.ver'],
                ['Miembros y quórum', '/miembros', 'miembros', 'miembros.ver'],
            ]],
            ['Sesiones y trámites', null, 'sesiones', null, [
                ['Sesiones y excusas', '/sesiones', 'calendario', 'sesion.ver'],
                ['Documentos y flujo', '/documentos', 'documentos', 'documento.ver'],
                ['Asistencia y votación', '/votacion', 'votacion', 'asistencia.ver'],
                ['Resoluciones y actas', '/resoluciones', 'resoluciones', 'resolucion.ver'],
            ]],
            ['Fiscalización', '/fiscalizacion', 'fiscalizacion', null, []],
            ['Reportes', '/reportes', 'reportes', 'reporte.ver', []],
            ['Pagos', null, 'pagos', null, [
                ['Mis pagos', '/pagos', 'tarjeta', 'pago.propio'],
                ['Administración de pagos', '/pagos/admin', 'billetera', 'pago.gestionar'],
            ]],
            ['Seguridad', null, 'seguridad', null, [
                ['Bitácora de accesos', '/bitacora', 'bitacora', 'bitacora.ver'],
                ['Matriz de acceso', '/seguridad/matriz', 'llave', 'rbac.gestionar'],
                ['Menú dinámico', '/seguridad/menu', 'lista', 'rbac.gestionar'],
            ]],
            ['Comandos por correo', '/correo', 'correo', null, []],
        ];

        $permisos = Permiso::pluck('id_permiso', 'codigo');
        $orden = 0;
        foreach ($estructura as [$titulo, $ruta, $icono, $permiso, $hijos]) {
            $padre = Menu::updateOrCreate(
                ['titulo' => $titulo, 'id_padre' => null],
                ['ruta' => $ruta, 'icono' => $icono, 'orden' => ++$orden, 'id_permiso' => $permiso ? ($permisos[$permiso] ?? null) : null]
            );
            $o = 0;
            foreach ($hijos as [$t, $r, $i, $p]) {
                Menu::updateOrCreate(
                    ['titulo' => $t, 'id_padre' => $padre->id_menu],
                    ['ruta' => $r, 'icono' => $i, 'orden' => ++$o, 'id_permiso' => $p ? ($permisos[$p] ?? null) : null]
                );
            }
        }
    }
}
