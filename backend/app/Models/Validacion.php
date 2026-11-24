<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Validacion extends Modelo
{
    protected $table = 'validacion';

    protected $primaryKey = 'id_validacion';

    protected function casts(): array
    {
        return ['fecha'=>'datetime'];
    }

    public function usuario(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario'); }
    public function rol(): BelongsTo { return $this->belongsTo(Rol::class, 'id_rol', 'id_rol'); }
}
