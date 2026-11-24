<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Modelo
{
    public $timestamps = true;

    protected $table = 'pago';

    protected $primaryKey = 'id_pago';

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class, 'id_concepto', 'id_concepto');
    }

    public function metodo(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo', 'id_metodo');
    }
}
