<?php

namespace App\Console\Commands;

use App\Support\PostgresHerramientas as Pg;
use Illuminate\Console\Command;
use Throwable;

class BdRestaurar extends Command
{
    protected $signature = 'bd:restaurar {archivo? : Archivo .dump (por defecto, el respaldo más reciente)} {--force : No pedir confirmación}';

    protected $description = 'Restaura un respaldo generado con bd:respaldo (reemplaza las tablas del esquema de la aplicación).';

    public function handle(): int
    {
        $archivo = $this->argument('archivo');
        if (! $archivo) {
            $todos = glob(Pg::carpeta().DIRECTORY_SEPARATOR.'respaldo_*.dump') ?: [];
            sort($todos);
            $archivo = end($todos) ?: null;
        }
        if (! $archivo || ! is_file($archivo)) {
            $this->error('No hay ningún respaldo para restaurar. Genere uno con: php artisan bd:respaldo');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("Se reemplazarán las tablas actuales con el respaldo $archivo. ¿Continuar?")) {
            return self::SUCCESS;
        }
        try {
            Pg::ejecutar('pg_restore', [...Pg::conexion(), '--clean', '--if-exists', '--no-owner', '--no-privileges', $archivo]);
        } catch (Throwable $e) {
            $this->error('No se pudo restaurar: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Restauración completada desde $archivo");

        return self::SUCCESS;
    }
}
