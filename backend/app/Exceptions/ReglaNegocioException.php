<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violación de una regla de negocio o de autorización. Es la excepción "esperada" del
 * sistema: la API la traduce a JSON y el procesador de correos a un mensaje de error.
 */
class ReglaNegocioException extends RuntimeException
{
    public function __construct(string $message, public int $estado = 422)
    {
        parent::__construct($message);
    }

    public static function invalida(string $mensaje): self
    {
        return new self($mensaje, 422);
    }

    public static function prohibido(string $mensaje): self
    {
        return new self($mensaje, 403);
    }

    public static function noEncontrado(string $mensaje): self
    {
        return new self($mensaje, 404);
    }
}
