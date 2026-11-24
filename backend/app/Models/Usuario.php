<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuario';

    protected $primaryKey = 'id_usuario';

    protected $guarded = [];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'preferencias' => 'array'];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    public function nombreRol(): string
    {
        return $this->rol?->nombre ?? '';
    }

    public function tieneRol(string ...$roles): bool
    {
        return in_array($this->nombreRol(), $roles, true);
    }

    /** Consejeros con voz y voto: titulares, suplentes acreditados y Presidente (Art. 54). */
    public function esConsejero(): bool
    {
        return $this->tieneRol('PRESIDENTE', 'TITULAR_DOCENTE', 'SUPLENTE_DOCENTE', 'TITULAR_ESTUDIANTE', 'SUPLENTE_ESTUDIANTE');
    }
}
