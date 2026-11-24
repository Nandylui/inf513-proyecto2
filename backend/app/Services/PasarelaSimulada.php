<?php

namespace App\Services;

use App\Models\MetodoPago;
use Illuminate\Support\Str;

/**
 * Pasarela de pagos SIMULADA: no se realiza ningún cobro real ni se contacta a ningún banco.
 * Reglas de prueba (documentadas en la pantalla de pagos):
 *   - Tarjeta terminada en 0002  -> rechazada por el emisor
 *   - Tarjeta terminada en 9995  -> fondos insuficientes
 *   - Tarjeta vencida            -> rechazada
 *   - Monto mayor a Bs 5.000     -> excede el límite de la pasarela de demostración
 *   - Cualquier otro caso        -> aprobada, con código de autorización SIM-XXXXXXXX
 */
class PasarelaSimulada
{
    public const LIMITE = 5000.00;

    /** @return array{aprobado: bool, codigo: ?string, motivo: ?string} */
    public static function cobrar(MetodoPago $metodo, float $monto): array
    {
        if ($monto > self::LIMITE) {
            return self::rechazo('El monto excede el límite de la pasarela de demostración (Bs '.number_format(self::LIMITE, 2, ',', '.').').');
        }

        if ($metodo->tipo === 'TARJETA') {
            if (self::vencida($metodo->vencimiento)) {
                return self::rechazo('La tarjeta está vencida.');
            }
            if ($metodo->ultimos4 === '0002') {
                return self::rechazo('Tarjeta rechazada por el emisor (simulación).');
            }
            if ($metodo->ultimos4 === '9995') {
                return self::rechazo('Fondos insuficientes (simulación).');
            }
        }

        return ['aprobado' => true, 'codigo' => 'SIM-'.strtoupper(Str::random(8)), 'motivo' => null];
    }

    public static function vencida(?string $mmaa): bool
    {
        if (! $mmaa || ! preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $mmaa, $m)) {
            return false;
        }
        $fin = \Carbon\Carbon::create(2000 + (int) $m[2], (int) $m[1], 1)->endOfMonth();

        return $fin->isPast();
    }

    private static function rechazo(string $motivo): array
    {
        return ['aprobado' => false, 'codigo' => null, 'motivo' => $motivo];
    }
}
