<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Menu extends Modelo
{
    protected $table = 'menu';

    protected $primaryKey = 'id_menu';

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(Permiso::class, 'id_permiso', 'id_permiso');
    }
}
