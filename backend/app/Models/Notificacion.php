<?php

namespace App\Models;


class Notificacion extends Modelo
{
    protected $table = 'notificacion';

    protected $primaryKey = 'id_notificacion';

    protected function casts(): array
    {
        return ['fecha'=>'datetime','leida'=>'boolean','enviada_correo'=>'boolean'];
    }
}
