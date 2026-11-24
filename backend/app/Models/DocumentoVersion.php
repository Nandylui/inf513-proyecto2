<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoVersion extends Modelo
{
    protected $table = 'documento_version';

    protected $primaryKey = 'id_version';

    protected function casts(): array
    {
        return ['created_at'=>'datetime'];
    }

    public function subidoPor(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_subido_por', 'id_usuario'); }
}
