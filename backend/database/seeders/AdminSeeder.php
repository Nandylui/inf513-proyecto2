<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Usuario::updateOrCreate(
            ['ci' => config('consejo.admin.ci')],
            [
                'nombres' => 'Administrador',
                'apellidos' => 'del Sistema',
                'correo' => config('consejo.admin.correo'),
                'password' => config('consejo.admin.clave'),
                'id_rol' => Rol::where('nombre', 'ADMINISTRADOR')->value('id_rol'),
                'estado' => 'ACTIVO',
            ]
        );
    }
}
