<?php

namespace App\Correo;

use App\Exceptions\ReglaNegocioException;
use App\Models\CorreoLog;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Support\Contexto;
use App\Support\Permisos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Núcleo del Sistema Vía Mail: recibe un correo, identifica al remitente, valida sintaxis, parámetros y permisos,
 * ejecuta el comando dentro de una transacción y arma la respuesta. Nunca lanza excepciones hacia afuera:
 * cualquier problema se convierte en un mensaje de error para el remitente y una línea en error.log.
 *
 * Seguridad: todas las consultas pasan por Eloquent/Query Builder (sentencias preparadas con parámetros enlazados),
 * por lo que ningún valor del asunto se concatena a una sentencia SQL.
 */
class ProcesadorCorreo
{
    public function procesar(MensajeEntrante $msg): Respuesta
    {
        Contexto::porCorreo();
        $remitente = $msg->remitente;
        $comando = null;

        try {
            if ($msg->esAutomatico() || $remitente === mb_strtolower(config('consejo.correo.direccion'))) {
                $r = new Respuesta($remitente, '', '', true, null, false);
                $this->acceso($remitente, $msg->asunto, null, 'IGNORADO (correo automático)');

                return $r;
            }

            $usuario = $this->identificar($remitente);
            $analisis = ComandoParser::analizar($msg->asunto);
            $comando = $analisis['comando'];
            $params = $analisis['parametros'];

            $def = Catalogo::buscar($comando);
            if (! $def) {
                $sug = Catalogo::parecidos($comando);

                throw ReglaNegocioException::noEncontrado("El comando {$comando} no existe.".($sug ? ' ¿Quiso decir: '.implode(', ', $sug).'?' : '').' Envíe AYUDA[] para ver el listado.');
            }

            if (! $usuario) {
                if (! ($def['publico'] && config('consejo.correo.permitir_anonimos'))) {
                    throw ReglaNegocioException::prohibido("El correo {$remitente} no está registrado en el sistema del Consejo. Solicite su alta al Secretario.");
                }
            } elseif ($usuario->estado !== 'ACTIVO') {
                throw ReglaNegocioException::prohibido('Su usuario está desactivado.');
            }
            if ($usuario && $def['permiso']) {
                Permisos::exigir($usuario, $def['permiso']);
            }
            $n = count($params);
            if ($n < $def['min'] || $n > $def['max']) {
                $esperado = $def['min'] === $def['max'] ? "{$def['min']}" : "entre {$def['min']} y {$def['max']}";

                throw ReglaNegocioException::invalida("El comando {$comando} espera {$esperado} parámetro(s) y recibió {$n}.\nSintaxis: {$def['sintaxis']}\nEjemplo: {$def['ejemplo']}");
            }

            Contexto::$canal = 'CORREO';
            $resultado = DB::transaction(fn () => ($def['handler'])($params, $usuario));
            $cuerpo = $resultado->render();
            $resp = $this->respuesta($msg, true, $comando, $cuerpo);
            AuditoriaService::registrar($usuario, 'CORREO_'.$comando, 'correo', null, ['exito' => true]);
        } catch (ReglaNegocioException $e) {
            $resp = $this->respuesta($msg, false, $comando, "No se pudo ejecutar el comando.\n\n".$e->getMessage());
            $this->error("[{$remitente}] {$msg->asunto} -> {$e->getMessage()}", false);
        } catch (\Throwable $e) {
            $resp = $this->respuesta($msg, false, $comando, "Ocurrió un error interno al procesar su comando. El incidente quedó registrado; intente nuevamente o contacte al administrador.");
            $this->error("[{$remitente}] {$msg->asunto} -> ".get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString(), true);
        }

        $this->acceso($remitente, $msg->asunto, $comando, $resp->exito ? 'OK' : 'ERROR');
        $this->registrar($msg, $resp);

        return $resp;
    }

    private function identificar(string $correo): ?Usuario
    {
        return Usuario::with('rol')->whereRaw('LOWER(correo) = ?', [mb_strtolower($correo)])->first();
    }

    private function respuesta(MensajeEntrante $msg, bool $exito, ?string $comando, string $cuerpo): Respuesta
    {
        $pie = "\n\n--\nConsejo Facultativo FICCT - ".config('consejo.grupo').' - Sistema Vía Mail'."\nAsunto recibido: ".mb_strimwidth($msg->asunto, 0, 120, '…');
        $asunto = ($exito ? '[OK] ' : '[ERROR] ').($comando ?: 'COMANDO').' - '.config('consejo.grupo');

        return new Respuesta($msg->remitente, $asunto, $cuerpo.$pie, $exito, $comando);
    }

    private function acceso(string $remitente, string $asunto, ?string $comando, string $estado): void
    {
        Log::channel('correo_access')->info("remitente={$remitente} comando=".($comando ?? '-')." estado={$estado} asunto=\"".mb_strimwidth($asunto, 0, 160, '…').'"');
    }

    private function error(string $mensaje, bool $grave): void
    {
        Log::channel('correo_error')->{$grave ? 'error' : 'warning'}($mensaje);
    }

    private function registrar(MensajeEntrante $msg, Respuesta $r): void
    {
        try {
            CorreoLog::create([
                'remitente' => mb_strimwidth($msg->remitente, 0, 190), 'asunto' => mb_strimwidth($msg->asunto, 0, 500),
                'comando' => $r->comando ? mb_substr($r->comando, 0, 20) : null, 'exito' => $r->exito,
                'respuesta' => mb_strimwidth($r->cuerpo, 0, 4000, '…'),
            ]);
        } catch (\Throwable $e) {
            $this->error('No se pudo guardar correo_log: '.$e->getMessage(), true);
        }
    }
}
