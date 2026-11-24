<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mandato extends Modelo
{
    protected $table = 'mandato';

    protected $primaryKey = 'id_mandato';

    public $timestamps = true;

    protected function casts(): array
    {
        return ['fecha_inicio'=>'date','fecha_fin'=>'date'];
    }

    public function titular(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_titular', 'id_usuario'); }
    public function suplente(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_suplente', 'id_usuario'); }
}
