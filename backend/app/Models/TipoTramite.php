<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoTramite extends Modelo
{
    protected $table = 'tipo_tramite';

    protected $primaryKey = 'id_tipo_tramite';

    protected function casts(): array
    {
        return ['activo'=>'boolean'];
    }

    public function pasos(): HasMany { return $this->hasMany(FlujoPaso::class, 'id_tipo_tramite', 'id_tipo_tramite')->orderBy('orden'); }
}
