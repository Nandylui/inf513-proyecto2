<?php

namespace App\Console\Commands;

use App\Support\PostgresHerramientas as Pg;
use Illuminate\Console\Command;
use Throwable;

class BdRespaldo extends Command
{
    protected $signature = 'bd:respaldo {--conservar=10 : Cantidad de respaldos que se conservan (los más antiguos se eliminan)}';

    protected $description = 'Genera un respaldo (pg_dump) del esquema de la aplicación en storage/app/respaldos.';

    public function handle(): int
    {
        $esquema = config('database.connections.pgsql.search_path') ?: 'public';
        $esquema = trim(explode(',', $esquema)[0]);
        $archivo = Pg::carpeta().DIRECTORY_SEPARATOR.'respaldo_'.date('Ymd_His').'.dump';
        try {
            Pg::ejecutar('pg_dump', [...Pg::conexion(), '-n', $esquema, '-Fc', '--no-owner', '--no-privileges', '-f', $archivo]);
        } catch (Throwable $e) {
            $this->error('No se pudo generar el respaldo: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("Respaldo generado (esquema \"$esquema\"): $archivo (".number_format(filesize($archivo) / 1024, 1).' KB)');

        $todos = glob(Pg::carpeta().DIRECTORY_SEPARATOR.'respaldo_*.dump') ?: [];
        sort($todos);
        foreach (array_slice($todos, 0, max(0, count($todos) - (int) $this->option('conservar'))) as $viejo) {
            @unlink($viejo);
        }

        return self::SUCCESS;
    }
}
