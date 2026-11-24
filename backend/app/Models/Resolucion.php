<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resolucion extends Modelo
{
    protected $table = 'resolucion';

    protected $primaryKey = 'id_resolucion';

    protected function casts(): array
    {
        return ['fecha_aprobacion'=>'datetime','fecha_promulgacion'=>'datetime','fecha_veto'=>'datetime','fecha_publicacion'=>'datetime','fecha_limite_promulgacion'=>'date'];
    }

    public function documento(): BelongsTo { return $this->belongsTo(Documento::class, 'id_documento', 'id_documento'); }
    public function sesion(): BelongsTo { return $this->belongsTo(Sesion::class, 'id_sesion', 'id_sesion'); }
}
