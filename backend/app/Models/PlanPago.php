<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPago extends Modelo
{
    public $timestamps = true;

    protected $table = 'plan_pago';

    protected $primaryKey = 'id_plan';

    protected function casts(): array
    {
        return ['monto_total' => 'decimal:2'];
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class, 'id_concepto', 'id_concepto');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class, 'id_plan', 'id_plan')->orderBy('numero');
    }
}
