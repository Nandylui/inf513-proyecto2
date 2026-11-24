<?php

namespace App\Models;

class VisitaPagina extends Modelo
{
    protected $table = 'visita_pagina';

    protected $primaryKey = 'id_visita';

    protected function casts(): array
    {
        return ['ultima_visita' => 'datetime'];
    }
}
