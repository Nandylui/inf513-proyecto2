<?php

namespace App\Correo;

use RuntimeException;

/**
 * Cliente POP3 mínimo sobre sockets (la extensión php-imap no es necesaria).
 * Comandos del RFC 1939: USER, PASS, STAT, LIST, RETR, DELE, QUIT. El servidor de la cátedra usa el puerto 110 sin SSL.
 */
class Pop3Client
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private string $host,
        private int $puerto = 110,
        private int $timeout = 15,
    ) {}

    public function conectar(string $usuario, string $clave): void
    {
        $errno = 0;
        $errstr = '';
        $this->socket = @stream_socket_client("tcp://{$this->host}:{$this->puerto}", $errno, $errstr, $this->timeout);
        if (! $this->socket) {
            throw new RuntimeException("No se pudo conectar al servidor POP3 {$this->host}:{$this->puerto} ({$errstr}).");
        }
        stream_set_timeout($this->socket, $this->timeout);
        $this->leerEstado();   // saludo +OK
        $this->comando('USER '.$usuario);
        $this->comando('PASS '.$clave);
    }

    /** @return array{cantidad:int,bytes:int} */
    public function estado(): array
    {
        $r = $this->comando('STAT');
        $p = explode(' ', $r);

        return ['cantidad' => (int) ($p[1] ?? 0), 'bytes' => (int) ($p[2] ?? 0)];
    }

    /** @return array<int,int> número de mensaje => tamaño */
    public function listar(): array
    {
        $this->comando('LIST');
        $lista = [];
        foreach ($this->leerMultilinea() as $linea) {
            if (preg_match('/^(\d+)\s+(\d+)/', $linea, $m)) {
                $lista[(int) $m[1]] = (int) $m[2];
            }
        }

        return $lista;
    }

    public function recuperar(int $numero): string
    {
        $this->comando("RETR {$numero}");

        return implode("\r\n", $this->leerMultilinea());
    }

    public function eliminar(int $numero): void
    {
        $this->comando("DELE {$numero}");
    }

    public function cerrar(): void
    {
        if ($this->socket) {
            try {
                $this->comando('QUIT');
            } catch (\Throwable) {
            }
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    private function comando(string $c): string
    {
        $this->escribir($c);

        return $this->leerEstado();
    }

    private function escribir(string $linea): void
    {
        if (! $this->socket || @fwrite($this->socket, $linea."\r\n") === false) {
            throw new RuntimeException('Conexión POP3 cerrada.');
        }
    }

    private function leerEstado(): string
    {
        $linea = $this->leerLinea();
        if (! str_starts_with($linea, '+OK')) {
            throw new RuntimeException('Error POP3: '.$linea);
        }

        return $linea;
    }

    private function leerLinea(): string
    {
        $linea = $this->socket ? fgets($this->socket, 65536) : false;
        if ($linea === false) {
            throw new RuntimeException('Tiempo de espera agotado o conexión POP3 cerrada.');
        }

        return rtrim($linea, "\r\n");
    }

    /** Lee hasta la línea ".", deshaciendo el byte-stuffing (..). @return string[] */
    private function leerMultilinea(): array
    {
        $lineas = [];
        while (($l = $this->leerLinea()) !== '.') {
            $lineas[] = str_starts_with($l, '..') ? substr($l, 1) : $l;
        }

        return $lineas;
    }

    public function __destruct()
    {
        $this->cerrar();
    }
}
