<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Sesion extends Modelo
{
    protected $table = 'sesion';

    protected $primaryKey = 'id_sesion';

    public $timestamps = true;

    protected function casts(): array
    {
        return ['fecha_hora'=>'datetime','publica'=>'boolean','quorum_alcanzado'=>'boolean'];
    }

    public function puntos(): HasMany { return $this->hasMany(PuntoOrdenDia::class, 'id_sesion', 'id_sesion')->orderBy('numero'); }
    public function documentos(): HasMany { return $this->hasMany(Documento::class, 'id_sesion', 'id_sesion'); }
    public function asistencias(): HasMany { return $this->hasMany(Asistencia::class, 'id_sesion', 'id_sesion'); }
    public function habilitaciones(): HasMany { return $this->hasMany(Habilitacion::class, 'id_sesion', 'id_sesion'); }
    public function excusas(): HasMany { return $this->hasMany(Excusa::class, 'id_sesion', 'id_sesion'); }
}
