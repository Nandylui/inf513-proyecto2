<?php

namespace App\Correo;

use Illuminate\Support\Facades\Log;

/** Ciclo de lectura del buzón: conecta por POP3, procesa cada mensaje, responde por SMTP y lo borra del servidor. */
class BuzonPop3
{
    public function __construct(private ProcesadorCorreo $procesador, private EnviadorCorreo $enviador) {}

    /** @return int cantidad de mensajes procesados */
    public function revisar(): int
    {
        $cfg = config('consejo.correo');
        $pop = new Pop3Client($cfg['pop3_host'], $cfg['pop3_puerto'], $cfg['pop3_timeout']);
        $procesados = 0;

        try {
            $pop->conectar($cfg['pop3_usuario'], (string) $cfg['pop3_clave']);
            foreach (array_keys($pop->listar()) as $numero) {
                try {
                    $crudo = $pop->recuperar($numero);
                    $respuesta = $this->procesador->procesar(MensajeEntrante::desdeCrudo($crudo));
                    $this->enviador->enviar($respuesta);
                    $procesados++;
                } catch (\Throwable $e) {
                    Log::channel('correo_error')->error("Mensaje POP3 #{$numero}: ".$e->getMessage());
                } finally {
                    try {
                        $pop->eliminar($numero);   // evita reprocesar el mismo comando (idempotencia)
                    } catch (\Throwable) {
                    }
                }
            }
        } finally {
            $pop->cerrar();
        }

        return $procesados;
    }
}
