<?php

namespace App\Correo;

/** Resultado de un comando de correo: se transforma en el cuerpo de texto plano de la respuesta. */
class Resultado
{
    public function __construct(
        public string $titulo,
        public string $mensaje = '',
        public array $columnas = [],
        public array $filas = [],
        public array $detalle = [],
    ) {}

    public static function mensaje(string $titulo, string $mensaje = '', array $detalle = []): self
    {
        return new self($titulo, $mensaje, [], [], $detalle);
    }

    public static function tabla(string $titulo, array $columnas, array $filas, array $detalle = [], string $mensaje = ''): self
    {
        return new self($titulo, $mensaje, $columnas, array_values($filas), $detalle);
    }

    /** Convierte la estructura de ReporteService en un resultado. */
    public static function reporte(array $r): self
    {
        return new self($r['titulo'], '', $r['columnas'], $r['filas'], $r['resumen'] ?? []);
    }

    public function render(): string
    {
        $out = [$this->titulo, str_repeat('=', min(78, max(8, mb_strlen($this->titulo))))];
        if ($this->mensaje !== '') {
            $out[] = $this->mensaje;
        }
        if ($this->detalle) {
            $ancho = max(array_map(fn ($k) => mb_strlen((string) $k), array_keys($this->detalle)));
            foreach ($this->detalle as $k => $v) {
                $out[] = self::pad((string) $k, $ancho).' : '.self::texto($v);
            }
        }
        if ($this->columnas) {
            $out[] = '';
            $out[] = $this->tablaTexto();
        }

        return implode("\n", $out);
    }

    private function tablaTexto(): string
    {
        if (! $this->filas) {
            return '(sin registros)';
        }
        $filas = array_map(fn ($f) => array_map(fn ($c) => self::texto($c, 38), array_values($f)), $this->filas);
        $anchos = [];
        foreach ($this->columnas as $i => $c) {
            $anchos[$i] = max(mb_strlen($c), ...array_map(fn ($f) => mb_strlen($f[$i] ?? ''), $filas));
        }
        $linea = fn (array $celdas) => implode(' | ', array_map(fn ($c, $i) => self::pad($c, $anchos[$i]), $celdas, array_keys($celdas)));
        $sep = implode('-+-', array_map(fn ($a) => str_repeat('-', $a), $anchos));
        $txt = [$linea($this->columnas), $sep];
        foreach ($filas as $f) {
            $txt[] = $linea($f);
        }
        $txt[] = '';
        $txt[] = count($filas).' registro(s).';

        return implode("\n", $txt);
    }

    public static function texto(mixed $v, int $max = 0): string
    {
        $t = match (true) {
            $v === null => '-',
            is_bool($v) => $v ? 'SI' : 'NO',
            $v instanceof \DateTimeInterface => $v->format('Y-m-d H:i'),
            is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
            default => (string) $v,
        };
        $t = trim(preg_replace('/\s+/u', ' ', $t));

        return $max > 0 ? mb_strimwidth($t, 0, $max, '…') : $t;
    }

    private static function pad(string $s, int $ancho): string
    {
        return $s.str_repeat(' ', max(0, $ancho - mb_strlen($s)));
    }
}
