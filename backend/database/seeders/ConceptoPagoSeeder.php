<?php

namespace Database\Seeders;

use App\Models\ConceptoPago;
use Illuminate\Database\Seeder;

/**
 * Aranceles y tasas de servicios del Consejo Facultativo (montos referenciales de demostración, en bolivianos).
 */
class ConceptoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $conceptos = [
            ['CERT_RESOLUCION', 'Certificación de resolución del Consejo', 'Copia certificada de una resolución promulgada por el Consejo Facultativo.', 25.00, false, 1],
            ['COPIA_ACTA', 'Copia legalizada de acta de sesión', 'Copia legalizada de un acta de sesión con su sello de integridad.', 40.00, false, 1],
            ['CERT_ASISTENCIA', 'Certificado de asistencia a sesiones', 'Constancia de asistencia de un consejero a las sesiones del Consejo.', 15.00, false, 1],
            ['DERECHO_TRAMITE', 'Derecho de trámite de documento', 'Derecho de tramitación de un documento para su tratamiento en sesión.', 60.00, true, 3],
            ['CAPACITACION', 'Taller de capacitación para consejeros', 'Inscripción al taller de capacitación sobre normativa y procedimiento del Consejo.', 150.00, true, 5],
        ];

        foreach ($conceptos as [$codigo, $nombre, $descripcion, $monto, $plan, $cuotas]) {
            ConceptoPago::firstOrCreate(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'descripcion' => $descripcion, 'monto' => $monto, 'permite_plan' => $plan, 'max_cuotas' => $cuotas, 'activo' => true]
            );
        }
    }
}
