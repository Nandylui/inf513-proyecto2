<?php

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Localiza y ejecuta pg_dump / pg_restore con las credenciales de la conexión activa (respaldo y restauración de la BD). */
class PostgresHerramientas
{
    /** Carpeta donde se guardan los respaldos (no se versiona). */
    public static function carpeta(): string
    {
        $c = storage_path('app/respaldos');
        if (! is_dir($c)) {
            mkdir($c, 0775, true);
        }

        return $c;
    }

    /** Ruta del ejecutable: PG_BIN (carpeta bin de PostgreSQL) > carpetas habituales de Windows > el PATH. */
    public static function ejecutable(string $nombre): string
    {
        $exe = PHP_OS_FAMILY === 'Windows' ? $nombre.'.exe' : $nombre;
        $carpetas = [env('PG_BIN')];
        if (PHP_OS_FAMILY === 'Windows') {
            foreach ([18, 17, 16, 15, 14, 13] as $v) {
                $carpetas[] = "C:\\Program Files\\PostgreSQL\\$v\\bin";
            }
        }
        foreach (array_filter($carpetas) as $dir) {
            $ruta = rtrim($dir, '\\/').DIRECTORY_SEPARATOR.$exe;
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return $exe;   // se confía en el PATH
    }

    /** @param  string[]  $args */
    public static function ejecutar(string $programa, array $args): string
    {
        $c = config('database.connections.'.config('database.default'));
        $p = new Process([self::ejecutable($programa), ...$args], null, ['PGPASSWORD' => (string) ($c['password'] ?? '')]);
        $p->setTimeout(600);
        $p->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException(trim($p->getErrorOutput()) ?: "No se pudo ejecutar $programa (¿está instalado y en el PATH, o definió PG_BIN en el .env?).");
        }

        return $p->getOutput();
    }

    /** Parámetros de conexión comunes a pg_dump y pg_restore. */
    public static function conexion(): array
    {
        $c = config('database.connections.'.config('database.default'));

        return ['-h', (string) $c['host'], '-p', (string) $c['port'], '-U', (string) $c['username'], '-d', (string) $c['database']];
    }
}
