<?php

namespace Database\Seeders;

use App\Models\Mandato;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Datos de demostración para probar el sistema (contraseña de todos: Consejo2026*).
 * Ejecutar: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const CLAVE = 'Consejo2026*';

    public function run(): void
    {
        $crear = function (string $ci, string $nombres, string $apellidos, string $correo, string $rol): Usuario {
            return Usuario::updateOrCreate(['ci' => $ci], [
                'nombres' => $nombres, 'apellidos' => $apellidos, 'correo' => $correo,
                'password' => self::CLAVE, 'estado' => 'ACTIVO',
                'id_rol' => Rol::where('nombre', $rol)->value('id_rol'),
            ]);
        };

        $crear('2000001', 'Carlos', 'Mendoza Rivero', 'presidente@consejo.test', 'PRESIDENTE');
        $crear('2000002', 'Lucía', 'Fernández Soria', 'secretario@consejo.test', 'SECRETARIO');

        $carreras = ['INFORMATICA' => 'inf', 'SISTEMAS' => 'sis', 'REDES' => 'red'];
        $n = 0;
        foreach ($carreras as $carrera => $sigla) {
            $n++;
            $td = $crear("30{$n}0001", "Docente Titular {$n}", "{$carrera}", "titular.docente.{$sigla}@consejo.test", 'TITULAR_DOCENTE');
            $sd = $crear("30{$n}0002", "Docente Suplente {$n}", "{$carrera}", "suplente.docente.{$sigla}@consejo.test", 'SUPLENTE_DOCENTE');
            $te = $crear("40{$n}0001", "Estudiante Titular {$n}", "{$carrera}", "titular.estudiante.{$sigla}@consejo.test", 'TITULAR_ESTUDIANTE');
            $se = $crear("40{$n}0002", "Estudiante Suplente {$n}", "{$carrera}", "suplente.estudiante.{$sigla}@consejo.test", 'SUPLENTE_ESTUDIANTE');

            foreach ([[$td, $sd, 'DOCENTE'], [$te, $se, 'ESTUDIANTE']] as [$titular, $suplente, $estamento]) {
                Mandato::updateOrCreate(
                    ['id_titular' => $titular->id_usuario, 'carrera' => $carrera],
                    [
                        'id_suplente' => $suplente->id_usuario, 'estamento' => $estamento,
                        'fecha_inicio' => now()->startOfYear()->toDateString(),
                        'fecha_fin' => now()->startOfYear()->addYears(2)->subDay()->toDateString(),
                        'estado' => 'VIGENTE',
                    ]
                );
            }
        }
    }
}
