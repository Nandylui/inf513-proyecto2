<?php

namespace App\Console\Commands;

use App\Correo\BuzonPop3;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CorreoEscuchar extends Command
{
    protected $signature = 'correo:escuchar {--intervalo= : Segundos entre revisiones (por defecto CONSEJO_MAIL_INTERVALO)} {--una-vez : Revisa el buzón una sola vez y termina}';

    protected $description = 'Revisa el buzón POP3 del Consejo, ejecuta los comandos recibidos y responde por SMTP.';

    public function handle(BuzonPop3 $buzon): int
    {
        $intervalo = max(5, (int) ($this->option('intervalo') ?: config('consejo.correo.intervalo_segundos')));
        $this->info('Sistema Vía Mail - '.config('consejo.grupo').' | POP3 '.config('consejo.correo.pop3_host').':'.config('consejo.correo.pop3_puerto'));

        do {
            try {
                $n = $buzon->revisar();
                $this->line('['.now()->format('H:i:s')."] {$n} correo(s) procesado(s).");
            } catch (\Throwable $e) {
                Log::channel('correo_error')->error('POP3: '.$e->getMessage());
                $this->error('['.now()->format('H:i:s').'] '.$e->getMessage());
                if ($this->option('una-vez')) {
                    return self::FAILURE;
                }
            }
            if ($this->option('una-vez')) {
                return self::SUCCESS;
            }
            sleep($intervalo);
        } while (true);
    }
}
