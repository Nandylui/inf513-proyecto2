<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Base de los modelos del Consejo: tablas con nombre y clave primaria propios (diseño original). */
abstract class Modelo extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
