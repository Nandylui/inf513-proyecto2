<?php

namespace App\Models;

class ConceptoPago extends Modelo
{
    protected $table = 'concepto_pago';

    protected $primaryKey = 'id_concepto';

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'permite_plan' => 'boolean', 'activo' => 'boolean'];
    }
}
