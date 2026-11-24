<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BitacoraAcceso extends Modelo
{
    protected $table = 'bitacora_acceso';

    protected $primaryKey = 'id_bitacora';

    protected function casts(): array
    {
        return ['fecha' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
