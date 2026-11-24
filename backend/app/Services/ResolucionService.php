<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Acta;
use App\Models\Asistencia;
use App\Models\Documento;
use App\Models\Resolucion;
use App\Models\Usuario;
use App\Support\BuscaEntidades;
use Illuminate\Support\Collection;

/**
 * CU6 - Gestión del ciclo de vida de resoluciones, veto del Decano y generación de actas.
 * RF-14 (actas), RF-17 (aprobada -> promulgada/vetada -> publicada en Gaceta), RF-18 (veto), Art. 57 y 64.
 */
class ResolucionService
{
    use BuscaEntidades;

    public function __construct(private MiembroService $miembros, private VotacionService $votaciones) {}

    public function listar(?string $filtro = null): Collection
    {
        $filtro = mb_strtoupper(trim((string) $filtro));
        $q = Resolucion::with(['documento', 'sesion'])->orderByDesc('fecha_aprobacion')->orderByDesc('id_resolucion');
        if (in_array($filtro, ['APROBADA', 'PROMULGADA', 'VETADA', 'MANTENIDA', 'PUBLICADA', 'RECHAZADA', 'ARCHIVADA'], true)) {
            $q->where('estado', $filtro);
        }

        return $q->get();
    }

    public function obtener(int|string $id): Resolucion
    {
        return $this->resolucion($id)->load(['documento.tipoTramite', 'sesion']);
    }

    /** Art. 64 a): el Decano promulga en un término máximo de 5 días hábiles. */
    public function promulgar(int|string $id, Usuario $actor): array
    {
        $res = $this->resolucion($id);
        if ($res->estado !== 'APROBADA') {
            throw ReglaNegocioException::invalida("La resolución está {$res->estado}: solo se promulgan resoluciones APROBADAS.");
        }
        $fueraDePlazo = $res->fecha_limite_promulgacion && now()->toDateString() > $res->fecha_limite_promulgacion->toDateString();
        $res->update(['estado' => 'PROMULGADA', 'fecha_promulgacion' => now()]);
        NotificacionService::notificarConsejo('RESOLUCION', "Resolución {$res->numero} promulgada", "El Decano promulgó la resolución {$res->numero}. Está pendiente su publicación en la Gaceta Universitaria.");
        AuditoriaService::registrar($actor, 'RESOLUCION_PROMULGADA', 'resolucion', $res->id_resolucion, ['fuera_de_plazo' => $fueraDePlazo]);

        return [
            'resolucion' => $res->fresh(),
            'advertencia' => $fueraDePlazo ? 'La promulgación se realizó fuera del plazo de 5 días hábiles (Art. 64 inc. a).' : null,
        ];
    }

    /** RF-18 / Art. 64 b): veto fundamentado, con efecto suspensivo, dentro de 5 días hábiles. */
    public function vetar(int|string $id, string $fundamento, Usuario $actor): Resolucion
    {
        $res = $this->resolucion($id);
        $fundamento = trim($fundamento);
        if ($res->estado !== 'APROBADA') {
            throw ReglaNegocioException::invalida("La resolución está {$res->estado}: solo se vetan resoluciones APROBADAS (sin promulgar).");
        }
        if (mb_strlen($fundamento) < 10) {
            throw ReglaNegocioException::invalida('El veto requiere la debida fundamentación (mínimo 10 caracteres).');
        }
        if ($res->fecha_limite_promulgacion && now()->toDateString() > $res->fecha_limite_promulgacion->toDateString()) {
            throw ReglaNegocioException::invalida("Plazo vencido: el veto debía registrarse hasta el {$res->fecha_limite_promulgacion->format('d/m/Y')} (5 días hábiles, Art. 64 inc. b).");
        }

        $res->update(['estado' => 'VETADA', 'fecha_veto' => now(), 'veto_fundamento' => $fundamento]);
        NotificacionService::notificarConsejo(
            'VETO', "Veto a la resolución {$res->numero}",
            "El Decano vetó la resolución {$res->numero} con efecto suspensivo. Fundamento: {$fundamento}\n"
            .'El Consejo la tratará en la siguiente sesión extraordinaria y podrá mantenerla con el voto de 2/3 de sus miembros asistentes (Art. 64 inc. b).'
        );
        AuditoriaService::registrar($actor, 'RESOLUCION_VETADA', 'resolucion', $res->id_resolucion);

        return $res->fresh();
    }

    /** Art. 64 b): en sesión extraordinaria el Consejo mantiene la resolución con 2/3 de los asistentes. */
    public function resolverVeto(int|string $id, int $votosFavor, int $asistentes, Usuario $actor): Resolucion
    {
        $res = $this->resolucion($id);
        if ($res->estado !== 'VETADA') {
            throw ReglaNegocioException::invalida("La resolución está {$res->estado}: solo se resuelve el veto de resoluciones VETADAS.");
        }
        if ($asistentes < 1 || $votosFavor < 0 || $votosFavor > $asistentes) {
            throw ReglaNegocioException::invalida('Los votos a favor deben estar entre 0 y el número de asistentes (mayor a 0).');
        }
        $mantenida = $votosFavor >= $this->votaciones->votosRequeridos('DOS_TERCIOS', $asistentes);
        $res->update(['estado' => $mantenida ? 'MANTENIDA' : 'ARCHIVADA']);
        NotificacionService::notificarConsejo(
            'VETO', "Resolución {$res->numero}: veto ".($mantenida ? 'superado' : 'confirmado'),
            $mantenida ? "El Consejo mantuvo la resolución con {$votosFavor} de {$asistentes} votos (2/3)." : "El Consejo no alcanzó 2/3 ({$votosFavor} de {$asistentes}): prevalece el veto y la resolución se archiva."
        );
        AuditoriaService::registrar($actor, 'VETO_RESUELTO', 'resolucion', $res->id_resolucion, ['favor' => $votosFavor, 'asistentes' => $asistentes, 'mantenida' => $mantenida]);

        return $res->fresh();
    }

    /** Art. 57: entra en vigencia tras aprobación, promulgación y publicación en la Gaceta. */
    public function publicar(int|string $id, string $gaceta, Usuario $actor): Resolucion
    {
        $res = $this->resolucion($id);
        if (! in_array($res->estado, ['PROMULGADA', 'MANTENIDA'], true)) {
            throw ReglaNegocioException::invalida("La resolución está {$res->estado}: solo se publican resoluciones PROMULGADAS o MANTENIDAS.");
        }
        $res->update([
            'estado' => 'PUBLICADA', 'fecha_publicacion' => now(),
            'gaceta_referencia' => $this->requerido($gaceta, 'referencia de la Gaceta Universitaria'),
        ]);
        NotificacionService::notificarConsejo('RESOLUCION', "Resolución {$res->numero} publicada", "La resolución {$res->numero} fue publicada en la Gaceta Universitaria ({$res->gaceta_referencia}) y entra en vigencia.");
        AuditoriaService::registrar($actor, 'RESOLUCION_PUBLICADA', 'resolucion', $res->id_resolucion, ['gaceta' => $res->gaceta_referencia]);

        return $res->fresh();
    }

    // ------------------------------------------------------------------ Actas (RF-14)

    public function generarActa(int|string $idSesion, Usuario $actor): Acta
    {
        $sesion = $this->sesion($idSesion)->load('puntos');
        if ($sesion->estado === 'CANCELADA') {
            throw ReglaNegocioException::invalida('La sesión fue cancelada: no genera acta.');
        }
        $quorum = $this->miembros->quorum($sesion, true);
        $panel = $this->miembros->panel($sesion);
        $asistencias = Asistencia::with('usuario.rol')->where('id_sesion', $sesion->id_sesion)->orderBy('hora_ingreso')->get();
        $habilitaciones = $sesion->habilitaciones()->get();

        $l = [];
        $l[] = "ACTA DE SESIÓN {$sesion->tipo} N° {$sesion->id_sesion} DEL CONSEJO FACULTATIVO";
        $l[] = "Fecha y hora: {$sesion->fecha_hora->format('d/m/Y H:i')}    Lugar: {$sesion->lugar}";
        $l[] = 'Convocada por: '.($sesion->convocada_por === 'PRESIDENTE' ? 'el Presidente del Consejo' : "solicitud de {$sesion->numero_solicitantes} miembros (Art. 55)").'    Carácter: '.($sesion->publica ? 'pública (Art. 56)' : 'reservada');
        $l[] = '';
        $l[] = "1. QUÓRUM: {$quorum['presentes']} presentes de {$quorum['total_miembros']} miembros; requerido {$quorum['requerido']} (mayoría absoluta, Art. 53). ".($quorum['alcanzado'] ? 'QUÓRUM ALCANZADO.' : 'SIN QUÓRUM.');
        $l[] = '';
        $l[] = '2. ASISTENTES:';
        foreach ($asistencias as $a) {
            $l[] = sprintf('   - %s (%s) - ingreso %s por %s%s', $a->usuario->nombre_completo, $a->usuario->nombreRol(), $a->hora_ingreso?->format('H:i'), $a->metodo, $a->es_suplente ? ' [SUPLENTE ACREDITADO]' : '');
        }
        if ($asistencias->isEmpty()) {
            $l[] = '   (sin asistentes registrados)';
        }
        $ausentes = array_filter($panel, fn ($f) => ! $f['presente']);
        if ($ausentes) {
            $l[] = '   Ausentes: '.implode('; ', array_map(fn ($f) => "{$f['habilitado']['nombre']} ({$f['carrera']})", $ausentes));
        }
        $l[] = '';
        $l[] = '3. REEMPLAZOS (RF-05):';
        foreach ($habilitaciones as $h) {
            $l[] = '   - '.$h->constancia;
        }
        if ($habilitaciones->isEmpty()) {
            $l[] = '   (sin reemplazos)';
        }
        $l[] = '';
        $l[] = '4. ORDEN DEL DÍA:';
        foreach ($sesion->puntos as $p) {
            $l[] = "   {$p->numero}. {$p->titulo}";
        }
        if ($sesion->puntos->isEmpty()) {
            $l[] = '   (sin puntos registrados)';
        }
        $l[] = '';
        $l[] = '5. DOCUMENTOS TRATADOS Y RESOLUCIONES:';
        $docs = Documento::with('tipoTramite')->where('id_sesion', $sesion->id_sesion)->orderBy('id_documento')->get();
        foreach ($docs as $d) {
            $r = Resolucion::where('id_documento', $d->id_documento)->first();
            if ($r) {
                $l[] = "   - [{$r->numero}] {$d->titulo}: {$r->resultado} - {$r->votos_favor} a favor, {$r->votos_contra} en contra, {$r->votos_blanco} en blanco (presentes {$r->presentes}; mayoría {$r->mayoria_requerida}).";
            } else {
                $l[] = "   - {$d->titulo}: estado {$d->estado} (sin votación en esta sesión).";
            }
        }
        if ($docs->isEmpty()) {
            $l[] = '   (sin documentos)';
        }
        $l[] = '';
        $l[] = 'Generada el '.now()->format('d/m/Y H:i').' por '.$actor->nombre_completo.' ('.$actor->nombreRol().').';

        $contenido = implode("\n", $l);
        $acta = Acta::updateOrCreate(
            ['id_sesion' => $sesion->id_sesion],
            ['contenido' => $contenido, 'hash' => hash('sha256', $contenido), 'generado_por' => $actor->id_usuario]
        );
        AuditoriaService::registrar($actor, 'ACTA_GENERADA', 'sesion', $sesion->id_sesion);

        return $acta;
    }

    public function acta(int|string $idSesion): Acta
    {
        return Acta::where('id_sesion', $this->sesion($idSesion)->id_sesion)->first()
            ?? throw ReglaNegocioException::noEncontrado("La sesión {$idSesion} aún no tiene acta generada.");
    }
}
