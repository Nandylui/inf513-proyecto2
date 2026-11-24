<?php

namespace Database\Seeders;

use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'ADMINISTRADOR' => 'Administrador del sistema',
            'PRESIDENTE' => 'Presidente del Consejo / Decano',
            'SECRETARIO' => 'Secretario del Consejo',
            'TITULAR_DOCENTE' => 'Consejero Titular Estamento Docente',
            'SUPLENTE_DOCENTE' => 'Consejero Suplente Estamento Docente',
            'TITULAR_ESTUDIANTE' => 'Consejero Titular Estamento Estudiantil',
            'SUPLENTE_ESTUDIANTE' => 'Consejero Suplente Estamento Estudiantil',
        ];
        foreach ($roles as $nombre => $descripcion) {
            Rol::updateOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }
    }
}
