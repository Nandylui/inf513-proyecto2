<?php

namespace App\Console\Commands;

use App\Correo\EnviadorCorreo;
use App\Correo\MensajeEntrante;
use App\Correo\ProcesadorCorreo;
use Illuminate\Console\Command;

class CorreoSimular extends Command
{
    protected $signature = 'correo:simular {asunto : Asunto con el comando, p. ej. LISUSU[\"*\"]} {--de=presidente@consejo.test : Correo del remitente} {--enviar : Envía además la respuesta por SMTP}';

    protected $description = 'Ejecuta un comando como si llegara por correo (sin POP3), para probar el sistema sin conexión al servidor.';

    public function handle(ProcesadorCorreo $procesador, EnviadorCorreo $enviador): int
    {
        $r = $procesador->procesar(MensajeEntrante::simulado($this->option('de'), $this->argument('asunto')));
        $this->line("De: {$this->option('de')}   Asunto: {$this->argument('asunto')}");
        $this->line('--- Respuesta ('.($r->exito ? 'OK' : 'ERROR').") a {$r->destino} | {$r->asunto} ---");
        $this->line($r->cuerpo);
        if ($this->option('enviar')) {
            $this->info($enviador->enviar($r) ? 'Respuesta enviada por SMTP.' : 'No se pudo enviar (revise error.log).');
        }

        return $r->exito ? self::SUCCESS : self::FAILURE;
    }
}
