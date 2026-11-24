<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Usuario;
use App\Support\Permisos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** RF-19: notificaciones a consejeros sobre convocatorias, plazos y resultados. */
class NotificacionService
{
    public static function notificar(Usuario $usuario, string $tipo, string $asunto, string $mensaje): Notificacion
    {
        $enviada = false;
        if (config('consejo.correo.notificaciones_por_correo')) {
            try {
                Mail::raw($mensaje, function ($m) use ($usuario, $asunto) {
                    $m->to($usuario->correo)->subject('[Consejo Facultativo] '.$asunto);
                });
                $enviada = true;
            } catch (Throwable $e) {
                Log::channel('correo_error')->error('No se pudo enviar la notificación por correo', [
                    'destino' => $usuario->correo, 'error' => $e->getMessage(),
                ]);
            }
        }

        return Notificacion::create([
            'id_usuario' => $usuario->id_usuario,
            'tipo' => $tipo,
            'asunto' => mb_substr($asunto, 0, 200),
            'mensaje' => $mensaje,
            'enviada_correo' => $enviada,
        ]);
    }

    /** Notifica a todos los consejeros activos y a la secretaría. */
    public static function notificarConsejo(string $tipo, string $asunto, string $mensaje): int
    {
        $destinatarios = self::destinatariosConsejo();
        foreach ($destinatarios as $usuario) {
            self::notificar($usuario, $tipo, $asunto, $mensaje);
        }

        return $destinatarios->count();
    }

    /** @return Collection<int, Usuario> */
    public static function destinatariosConsejo(): Collection
    {
        return Usuario::with('rol')->where('estado', 'ACTIVO')
            ->whereHas('rol', fn ($q) => $q->whereIn('nombre', array_merge(Permisos::CONSEJEROS, ['SECRETARIO'])))
            ->get();
    }
}
