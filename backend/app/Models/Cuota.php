<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cuota extends Modelo
{
    protected $table = 'cuota';

    protected $primaryKey = 'id_cuota';

    protected function casts(): array
    {
        return ['monto' => 'decimal:2', 'fecha_vencimiento' => 'date:Y-m-d', 'fecha_pago' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanPago::class, 'id_plan', 'id_plan');
    }
}
