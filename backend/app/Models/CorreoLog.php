<?php

namespace App\Models;


class CorreoLog extends Modelo
{
    protected $table = 'correo_log';

    protected $primaryKey = 'id_correo_log';

    protected function casts(): array
    {
        return ['fecha'=>'datetime','exito'=>'boolean'];
    }
}
