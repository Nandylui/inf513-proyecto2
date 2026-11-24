<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use App\Support\Permisos;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra el catálogo de permisos y la matriz de acceso inicial (rol_permiso).
 * Es idempotente: los permisos que ya existen conservan lo que el administrador haya configurado.
 */
class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Rol::pluck('id_rol', 'nombre');
        $porDefecto = Permisos::porDefecto();

        foreach (Permisos::catalogo() as $codigo => [$modulo, $descripcion]) {
            $permiso = Permiso::firstOrNew(['codigo' => $codigo]);
            $esNuevo = ! $permiso->exists;
            $permiso->modulo = $modulo;
            $permiso->descripcion = $descripcion;
            $permiso->save();

            if (! $esNuevo) {
                continue;
            }
            $autorizados = $porDefecto[$codigo] ?? [];
            $nombres = in_array('*', $autorizados, true) ? $roles->keys()->all() : $autorizados;
            foreach ($nombres as $nombre) {
                if (isset($roles[$nombre])) {
                    DB::table('rol_permiso')->insertOrIgnore(['id_rol' => $roles[$nombre], 'id_permiso' => $permiso->id_permiso]);
                }
            }
        }
        Permisos::limpiarCache();
    }
}
