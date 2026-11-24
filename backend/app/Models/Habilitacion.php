<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Habilitacion extends Modelo
{
    protected $table = 'habilitacion';

    protected $primaryKey = 'id_habilitacion';

    protected function casts(): array
    {
        return ['fecha'=>'datetime'];
    }

    public function titular(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_titular', 'id_usuario'); }
    public function habilitado(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_habilitado', 'id_usuario'); }
}
