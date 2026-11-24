<?php

namespace App\Support;

/** Contexto de ejecución (canal e IP) para la bitácora de auditoría. */
class Contexto
{
    public static string $canal = 'WEB';

    public static ?string $ip = null;

    public static function porCorreo(): void
    {
        self::$canal = 'CORREO';
        self::$ip = null;
    }

    public static function web(?string $ip = null): void
    {
        self::$canal = 'WEB';
        self::$ip = $ip;
    }
}
