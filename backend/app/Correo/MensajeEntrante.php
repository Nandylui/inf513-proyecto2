<?php

namespace App\Correo;

/** Correo recibido por POP3, reducido a lo necesario: remitente, asunto y cabeceras de control. */
class MensajeEntrante
{
    public function __construct(
        public string $remitente,
        public string $asunto,
        public ?string $messageId = null,
        public array $cabeceras = [],
        public ?string $nombreRemitente = null,
    ) {}

    public static function desdeCrudo(string $crudo): self
    {
        $separador = preg_split("/\r?\n\r?\n/", $crudo, 2);
        $h = self::cabeceras($separador[0] ?? '');
        $from = $h['from'] ?? ($h['return-path'] ?? '');
        [$nombre, $correo] = self::direccion(self::decodificar($from));

        return new self($correo, trim(self::decodificar($h['subject'] ?? '')), $h['message-id'] ?? null, $h, $nombre);
    }

    /** Mensaje armado a mano (modo simulación y pruebas). */
    public static function simulado(string $remitente, string $asunto): self
    {
        return new self(mb_strtolower(trim($remitente)), $asunto, '<simulado-'.bin2hex(random_bytes(4)).'@local>', []);
    }

    /** Correos automáticos o rebotes: no se responden (evita bucles). */
    public function esAutomatico(): bool
    {
        $auto = mb_strtolower($this->cabeceras['auto-submitted'] ?? 'no');
        $prec = mb_strtolower($this->cabeceras['precedence'] ?? '');

        return ($auto !== 'no' && $auto !== '')
            || in_array($prec, ['bulk', 'junk', 'list'], true)
            || (bool) preg_match('/^(mailer-daemon|postmaster|no-?reply)@/i', $this->remitente);
    }

    /** @return array<string,string> cabeceras en minúsculas, ya "desplegadas" (folded). */
    public static function cabeceras(string $bloque): array
    {
        $bloque = preg_replace("/\r?\n[ \t]+/", ' ', $bloque);
        $h = [];
        foreach (preg_split("/\r?\n/", $bloque) as $linea) {
            if (str_contains($linea, ':')) {
                [$k, $v] = explode(':', $linea, 2);
                $k = mb_strtolower(trim($k));
                $h[$k] ??= trim($v);
            }
        }

        return $h;
    }

    /** Decodifica RFC 2047 (=?UTF-8?B?...?= / =?ISO-8859-1?Q?...?=). */
    public static function decodificar(string $valor): string
    {
        if (! str_contains($valor, '=?')) {
            return $valor;
        }
        $d = @iconv_mime_decode($valor, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if ($d === false || $d === '') {
            $d = mb_decode_mimeheader($valor);
        }

        return $d;
    }

    /** @return array{0:?string,1:string} nombre y correo en minúsculas */
    private static function direccion(string $from): array
    {
        if (preg_match('/<([^<>\s]+@[^<>\s]+)>/', $from, $m)) {
            return [trim(trim(explode('<', $from)[0]), "\" '") ?: null, mb_strtolower($m[1])];
        }
        if (preg_match('/[^\s<>",;]+@[^\s<>",;]+/', $from, $m)) {
            return [null, mb_strtolower($m[0])];
        }

        return [null, mb_strtolower(trim($from))];
    }
}
