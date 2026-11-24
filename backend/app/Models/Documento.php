<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Documento extends Modelo
{
    protected $table = 'documento';

    protected $primaryKey = 'id_documento';

    protected function casts(): array
    {
        return ['fecha_recepcion'=>'datetime'];
    }

    public function remitente(): BelongsTo { return $this->belongsTo(Usuario::class, 'id_remitente', 'id_usuario'); }
    public function sesion(): BelongsTo { return $this->belongsTo(Sesion::class, 'id_sesion', 'id_sesion'); }
    public function tipoTramite(): BelongsTo { return $this->belongsTo(TipoTramite::class, 'id_tipo_tramite', 'id_tipo_tramite'); }
    public function versiones(): HasMany { return $this->hasMany(DocumentoVersion::class, 'id_documento', 'id_documento')->orderBy('numero'); }
    public function validaciones(): HasMany { return $this->hasMany(Validacion::class, 'id_documento', 'id_documento')->orderBy('fecha')->orderBy('id_validacion'); }
}
