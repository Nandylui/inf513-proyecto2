<?php

namespace Database\Seeders;

use App\Models\FlujoPaso;
use App\Models\Rol;
use App\Models\TipoTramite;
use Illuminate\Database\Seeder;

/**
 * RF-16: mayoría necesaria según el tipo de resolución (Art. 53 y 58 del Estatuto Orgánico).
 * RF-12: flujo de validación configurable; no todos los documentos pasan por todos los roles.
 */
class TipoTramiteSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['GENERAL', 'Art. 53', 'ABSOLUTA', 'Trámite general sin atribución específica', ['SECRETARIO']],
            ['POA', 'Art. 58 inc. f)', 'ABSOLUTA', 'Plan Operativo Anual de la Facultad', ['SECRETARIO', 'PRESIDENTE']],
            ['PRESUPUESTO', 'Art. 58 inc. f)', 'ABSOLUTA', 'Presupuesto financiero ordinario o extraordinario', ['SECRETARIO', 'TITULAR_DOCENTE', 'PRESIDENTE']],
            ['CREACION_CARRERA', 'Art. 58 inc. i)', 'DOS_TERCIOS', 'Creación, fusión o supresión de Carreras o Unidades Académicas', ['SECRETARIO', 'TITULAR_DOCENTE', 'TITULAR_ESTUDIANTE', 'PRESIDENTE']],
            ['RENUNCIA_DIRECTOR', 'Art. 58 inc. l)', 'DOS_TERCIOS', 'Renuncia de Directores de Carrera', ['PRESIDENTE']],
            ['RENUNCIA_DECANO', 'Art. 58 inc. m)', 'DOS_TERCIOS', 'Renuncia del Decano y/o Vicedecano', ['SECRETARIO']],
            ['CONVENIO', 'Art. 58 inc. k)', 'ABSOLUTA', 'Convenios interuniversitarios o interfacultativos', ['SECRETARIO', 'PRESIDENTE']],
            ['LICENCIA', 'Art. 58 inc. n)', 'ABSOLUTA', 'Licencias al Decano, Vicedecano y Directores', ['SECRETARIO']],
            ['PROYECTO_CURRICULAR', 'Art. 58 inc. s)', 'ABSOLUTA', 'Proyectos curriculares de Carrera', ['SECRETARIO', 'TITULAR_DOCENTE']],
            ['CARGA_HORARIA', 'Art. 58 inc. t)', 'ABSOLUTA', 'Carga horaria docente programada', ['SECRETARIO', 'TITULAR_DOCENTE']],
            ['BECAS_TRABAJO', 'Art. 58 inc. u)', 'ABSOLUTA', 'Asignación de becas trabajo', ['SECRETARIO']],
        ];

        foreach ($tipos as [$nombre, $articulo, $mayoria, $descripcion, $flujo]) {
            $tipo = TipoTramite::updateOrCreate(
                ['nombre' => $nombre],
                ['articulo' => $articulo, 'mayoria' => $mayoria, 'descripcion' => $descripcion, 'activo' => true]
            );
            FlujoPaso::where('id_tipo_tramite', $tipo->id_tipo_tramite)->delete();
            foreach ($flujo as $i => $rol) {
                FlujoPaso::create([
                    'id_tipo_tramite' => $tipo->id_tipo_tramite,
                    'orden' => $i + 1,
                    'id_rol' => Rol::where('nombre', $rol)->value('id_rol'),
                ]);
            }
        }
    }
}
