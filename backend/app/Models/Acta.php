<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Acta extends Modelo
{
    protected $table = 'acta';

    protected $primaryKey = 'id_acta';

    public $timestamps = true;

    public function sesion(): BelongsTo { return $this->belongsTo(Sesion::class, 'id_sesion', 'id_sesion'); }
}
