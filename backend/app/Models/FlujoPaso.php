<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlujoPaso extends Modelo
{
    protected $table = 'flujo_paso';

    protected $primaryKey = 'id_flujo_paso';

    public function rol(): BelongsTo { return $this->belongsTo(Rol::class, 'id_rol', 'id_rol'); }
}
