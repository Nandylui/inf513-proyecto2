<?php

namespace App\Correo;

use App\Exceptions\ReglaNegocioException;

/**
 * Analiza el asunto del correo con la sintaxis  COMANDO["p1","p2",...]  (estilo del Sistema Vía Mail).
 *
 * Es tolerante con: mayúsculas/minúsculas, prefijos "Re:"/"Fwd:", comillas tipográficas “ ” ‘ ’,
 * comillas simples, parámetros sin comillas (números), espacios y saltos de línea, y comillas
 * escapadas dentro del valor (\"). Una sintaxis inválida produce un mensaje claro, nunca un fallo.
 */
class ComandoParser
{
    private const ABRE = ['"' => '"', '“' => '”', '”' => '”', '„' => '”', "'" => "'", '‘' => '’', '’' => '’'];

    /** @return array{comando:string,parametros:array<int,string>} */
    public static function analizar(string $asunto): array
    {
        $texto = self::limpiar($asunto);
        if ($texto === '') {
            throw ReglaNegocioException::invalida('El asunto del correo está vacío. Use el formato COMANDO["parametro1","parametro2"]. Envíe AYUDA para ver los comandos.');
        }
        if (! preg_match('/^([A-Za-z]{3,12})\s*(?:\[(.*)\])?$/su', $texto, $m)) {
            throw ReglaNegocioException::invalida("Sintaxis inválida en el asunto: \"".mb_strimwidth($texto, 0, 80, '…')."\". Formato esperado: COMANDO[\"parametro1\",\"parametro2\"] (los corchetes deben cerrarse).");
        }

        return ['comando' => mb_strtoupper($m[1]), 'parametros' => isset($m[2]) ? self::parametros($m[2]) : []];
    }

    /** Quita prefijos de respuesta/reenvío, espacios invisibles y saltos de línea. */
    public static function limpiar(string $asunto): string
    {
        $asunto = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $asunto);
        $asunto = trim(preg_replace('/\s+/u', ' ', $asunto));
        while (preg_match('/^(re|rv|fw|fwd|enc)\s*:\s*/iu', $asunto)) {
            $asunto = preg_replace('/^(re|rv|fw|fwd|enc)\s*:\s*/iu', '', $asunto);
        }
        $asunto = preg_replace('/^\[[^\]]*\]\s*(?=[A-Za-z]{3,12}\s*(\[|$))/u', '', $asunto);   // etiquetas tipo [EXTERNO]

        return trim($asunto);
    }

    /** @return array<int,string> */
    public static function parametros(string $interior): array
    {
        $chars = preg_split('//u', trim($interior), -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        if ($n === 0) {
            return [];
        }
        $params = [];
        $i = 0;
        while ($i <= $n) {
            while ($i < $n && ctype_space($chars[$i])) {
                $i++;
            }
            if ($i < $n && isset(self::ABRE[$chars[$i]])) {
                $cierre = self::ABRE[$chars[$i]];
                $i++;
                $valor = '';
                $cerrado = false;
                while ($i < $n) {
                    $c = $chars[$i];
                    if ($c === '\\' && $i + 1 < $n && (isset(self::ABRE[$chars[$i + 1]]) || $chars[$i + 1] === '\\')) {
                        $valor .= $chars[$i + 1];
                        $i += 2;
                        continue;
                    }
                    if ($c === $cierre || ($cierre === '”' && $c === '“')) {
                        $cerrado = true;
                        $i++;
                        break;
                    }
                    $valor .= $c;
                    $i++;
                }
                if (! $cerrado) {
                    throw ReglaNegocioException::invalida('Falta cerrar unas comillas en los parámetros del comando.');
                }
                while ($i < $n && ctype_space($chars[$i])) {
                    $i++;
                }
                if ($i < $n && $chars[$i] !== ',') {
                    throw ReglaNegocioException::invalida('Después de un parámetro entre comillas debe ir una coma. Revise: '.mb_strimwidth($valor, 0, 30, '…'));
                }
                $params[] = trim($valor);
            } else {
                $valor = '';
                while ($i < $n && $chars[$i] !== ',') {
                    $valor .= $chars[$i];
                    $i++;
                }
                $params[] = trim($valor);
            }
            $i++;   // salta la coma
            if ($i === $n + 1) {
                break;
            }
        }

        return $params;
    }
}
