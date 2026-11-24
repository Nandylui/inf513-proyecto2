<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Acta;
use App\Models\Auditoria;
use App\Models\Notificacion;
use App\Models\Resolucion;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Support\BuscaEntidades;
use Illuminate\Support\Collection;

/**
 * CU7 - Fiscalizar, auditar y publicar información del Consejo (Gaceta).
 * RF-19 (notificaciones), RF-21 (consulta pública, Art. 56), RF-22 (auditoría del sistema).
 */
class FiscalizacionService
{
    use BuscaEntidades;

    public function auditoria(array $f = [], int $limite = 200): Collection
    {
        $q = Auditoria::with('usuario')->orderByDesc('fecha')->orderByDesc('id_auditoria')->limit(min($limite, 1000));
        if (! empty($f['ci'])) {
            $q->where('id_usuario', $this->usuarioPorCi($f['ci'])->id_usuario);
        }
        if (! empty($f['entidad'])) {
            $q->where('entidad', mb_strtolower($f['entidad']));
        }
        if (! empty($f['accion'])) {
            $q->whereRaw('UPPER(accion) LIKE ?', ['%'.mb_strtoupper($f['accion']).'%']);
        }
        if (! empty($f['canal'])) {
            $q->where('canal', mb_strtoupper($f['canal']));
        }

        return $q->get();
    }

    public function notificaciones(Usuario $usuario, bool $soloNoLeidas = false): Collection
    {
        $q = Notificacion::where('id_usuario', $usuario->id_usuario)->orderByDesc('fecha')->orderByDesc('id_notificacion')->limit(100);
        if ($soloNoLeidas) {
            $q->where('leida', false);
        }

        return $q->get();
    }

    public function marcarLeida(int|string $id, Usuario $usuario): Notificacion
    {
        $n = Notificacion::where('id_notificacion', $this->entero($id, 'id_notificacion'))->where('id_usuario', $usuario->id_usuario)->first()
            ?? throw ReglaNegocioException::noEncontrado('Notificación no encontrada.');
        $n->update(['leida' => true]);

        return $n;
    }

    /** Envío manual (ci o "*" = todo el Consejo). */
    public function enviar(string $destino, string $asunto, string $mensaje, ?Usuario $actor = null): int
    {
        $asunto = $this->requerido($asunto, 'asunto');
        $mensaje = $this->requerido($mensaje, 'mensaje');
        $destino = trim($destino);
        if ($destino === '*' || $destino === '') {
            $n = NotificacionService::notificarConsejo('AVISO', $asunto, $mensaje);
        } else {
            NotificacionService::notificar($this->usuarioPorCi($destino), 'AVISO', $asunto, $mensaje);
            $n = 1;
        }
        AuditoriaService::registrar($actor, 'NOTIFICACION_ENVIADA', null, null, ['destino' => $destino, 'asunto' => $asunto]);

        return $n;
    }

    // -------- Publicidad de sesiones (Art. 56: las sesiones son públicas) -------------

    public function sesionesPublicas(): Collection
    {
        return Sesion::where('publica', true)->orderByDesc('fecha_hora')->get(['id_sesion', 'fecha_hora', 'lugar', 'tipo', 'estado', 'orden_del_dia']);
    }

    public function actasPublicas(): Collection
    {
        return Acta::with('sesion:id_sesion,fecha_hora,tipo,lugar')
            ->whereHas('sesion', fn ($q) => $q->where('publica', true))
            ->orderByDesc('id_acta')->get(['id_acta', 'id_sesion', 'hash', 'updated_at']);
    }

    public function actaPublica(int|string $idSesion): Acta
    {
        $sesion = $this->sesion($idSesion);
        if (! $sesion->publica) {
            throw ReglaNegocioException::prohibido('La sesión no es pública.');
        }

        return Acta::with('sesion:id_sesion,fecha_hora,tipo,lugar')->where('id_sesion', $sesion->id_sesion)->first()
            ?? throw ReglaNegocioException::noEncontrado('La sesión aún no tiene acta publicada.');
    }

    /** Resoluciones aprobadas (se excluyen las rechazadas y las archivadas por veto). */
    public function resolucionesPublicas(): Collection
    {
        return Resolucion::with('documento:id_documento,titulo')
            ->whereIn('estado', ['APROBADA', 'PROMULGADA', 'MANTENIDA', 'PUBLICADA'])
            ->orderByDesc('fecha_aprobacion')->get([
                'id_resolucion', 'numero', 'id_documento', 'id_sesion', 'mayoria_requerida', 'presentes',
                'votos_favor', 'votos_contra', 'votos_blanco', 'estado', 'fecha_aprobacion', 'fecha_publicacion', 'gaceta_referencia',
            ]);
    }
}
