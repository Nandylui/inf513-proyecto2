<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Modelo
{
    protected $table = 'auditoria';

    protected $primaryKey = 'id_auditoria';

    protected function casts(): array
    {
        return ['fecha'=>'datetime','detalle'=>'array'];
    }

    public function usuario(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario'); }
}
