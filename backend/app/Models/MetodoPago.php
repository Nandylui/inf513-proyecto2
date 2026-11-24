<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetodoPago extends Modelo
{
    public $timestamps = true;

    protected $table = 'metodo_pago';

    protected $primaryKey = 'id_metodo';

    protected function casts(): array
    {
        return ['predeterminado' => 'boolean'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    /** Texto que se muestra y se guarda en el comprobante (nunca el número completo). */
    public function descripcion(): string
    {
        return trim(($this->marca ?: $this->tipo).($this->ultimos4 ? ' ****'.$this->ultimos4 : ''));
    }
}
