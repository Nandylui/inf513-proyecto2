<?php

namespace App\Correo;

/** Respuesta que se envía por SMTP al remitente del comando. */
class Respuesta
{
    public function __construct(
        public string $destino,
        public string $asunto,
        public string $cuerpo,
        public bool $exito,
        public ?string $comando = null,
        public bool $responder = true,
    ) {}
}
