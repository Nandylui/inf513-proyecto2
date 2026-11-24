<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/** Base de los modelos del Consejo: tablas con nombre y clave primaria propios (diseño original). */
abstract class Modelo extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Las fechas viajan a la SPA en la hora local de la aplicación (America/La_Paz), sin convertirlas a UTC. */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
