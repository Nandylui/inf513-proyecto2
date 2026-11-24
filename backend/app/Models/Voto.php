<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voto extends Modelo
{
    protected $table = 'votacion';

    protected $primaryKey = 'id_votacion';

    protected function casts(): array
    {
        return ['fecha'=>'datetime'];
    }

    public function usuario(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario'); }
}
