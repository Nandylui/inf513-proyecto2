<?php

namespace App\Support;

use App\Exceptions\ReglaNegocioException;
use App\Models\Documento;
use App\Models\Mandato;
use App\Models\Resolucion;
use App\Models\Sesion;
use App\Models\TipoTramite;
use App\Models\Usuario;

/** Búsquedas con mensajes de error claros (se reutilizan desde la API y desde los comandos de correo). */
trait BuscaEntidades
{
    protected function usuarioPorCi(string $ci): Usuario
    {
        return Usuario::with('rol')->where('ci', trim($ci))->first()
            ?? throw ReglaNegocioException::noEncontrado("No existe un usuario con CI {$ci}.");
    }

    protected function usuarioPorCorreo(string $correo): ?Usuario
    {
        return Usuario::with('rol')->whereRaw('LOWER(correo) = ?', [mb_strtolower(trim($correo))])->first();
    }

    protected function sesion(int|string $id): Sesion
    {
        return Sesion::find($this->entero($id, 'id_sesion'))
            ?? throw ReglaNegocioException::noEncontrado("No existe la sesión {$id}.");
    }

    protected function documento(int|string $id): Documento
    {
        return Documento::find($this->entero($id, 'id_documento'))
            ?? throw ReglaNegocioException::noEncontrado("No existe el documento {$id}.");
    }

    protected function resolucion(int|string $id): Resolucion
    {
        return Resolucion::find($this->entero($id, 'id_resolucion'))
            ?? throw ReglaNegocioException::noEncontrado("No existe la resolución {$id}.");
    }

    protected function mandato(int|string $id): Mandato
    {
        return Mandato::find($this->entero($id, 'id_mandato'))
            ?? throw ReglaNegocioException::noEncontrado("No existe el mandato {$id}.");
    }

    protected function tipoTramite(int|string|null $valor): TipoTramite
    {
        $valor = trim((string) $valor) === '' ? 'GENERAL' : trim((string) $valor);
        $tipo = ctype_digit($valor)
            ? TipoTramite::find((int) $valor)
            : TipoTramite::whereRaw('UPPER(nombre) = ?', [mb_strtoupper($valor)])->first();

        return $tipo ?? throw ReglaNegocioException::noEncontrado("No existe el tipo de trámite \"{$valor}\".");
    }

    protected function entero(int|string $valor, string $campo): int
    {
        if (! is_int($valor) && ! ctype_digit(trim((string) $valor))) {
            throw ReglaNegocioException::invalida("El campo {$campo} debe ser un número entero (recibido: \"{$valor}\").");
        }

        return (int) $valor;
    }

    protected function requerido(mixed $valor, string $campo): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            throw ReglaNegocioException::invalida("El campo {$campo} es obligatorio.");
        }

        return $valor;
    }
}
