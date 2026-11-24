<?php

namespace App\Support;

use App\Exceptions\ReglaNegocioException;
use Carbon\Carbon;
use Throwable;

class Fechas
{
    /** Acepta "YYYY-MM-DD" o "YYYY-MM-DD HH:MM" (también con "T"). */
    public static function parsear(string $valor, string $campo = 'fecha'): Carbon
    {
        $valor = trim($valor);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/', $valor)) {
            throw ReglaNegocioException::invalida("El campo {$campo} debe tener formato AAAA-MM-DD o AAAA-MM-DD HH:MM (recibido: \"{$valor}\").");
        }
        try {
            return Carbon::parse($valor);
        } catch (Throwable) {
            throw ReglaNegocioException::invalida("El campo {$campo} no es una fecha válida (\"{$valor}\").");
        }
    }

    /** Suma días hábiles (lunes a viernes) a partir de una fecha. */
    public static function sumarDiasHabiles(Carbon $desde, int $dias): Carbon
    {
        $fecha = $desde->copy()->startOfDay();
        while ($dias > 0) {
            $fecha->addDay();
            if (! $fecha->isWeekend()) {
                $dias--;
            }
        }

        return $fecha;
    }
}
