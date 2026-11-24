<?php

namespace App\Correo;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Envío de respuestas por SMTP (puerto 25 del servidor de la cátedra, sin autenticación ni SSL). */
class EnviadorCorreo
{
    public function enviar(Respuesta $r): bool
    {
        if (! $r->responder) {
            return false;
        }
        try {
            Mail::raw($r->cuerpo, function ($m) use ($r) {
                $m->to($r->destino)
                    ->from(config('consejo.correo.direccion'), 'Consejo Facultativo FICCT - '.config('consejo.grupo'))
                    ->subject($r->asunto);
            });

            return true;
        } catch (\Throwable $e) {
            Log::channel('correo_error')->error('SMTP: no se pudo enviar la respuesta a '.$r->destino.': '.$e->getMessage());

            return false;
        }
    }
}
