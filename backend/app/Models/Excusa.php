<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Excusa extends Modelo
{
    protected $table = 'excusa';

    protected $primaryKey = 'id_excusa';

    protected function casts(): array
    {
        return ['fecha_registro'=>'datetime','firmado_digitalmente'=>'boolean'];
    }

    public function titular(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_titular', 'id_usuario'); }
}
