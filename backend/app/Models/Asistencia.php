<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Modelo
{
    protected $table = 'asistencia';

    protected $primaryKey = 'id_asistencia';

    protected function casts(): array
    {
        return ['hora_ingreso'=>'datetime','es_suplente'=>'boolean'];
    }

    public function usuario(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario'); }
}
