<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia;
use App\Models\Habilitacion;
use App\Models\Mandato;
use App\Models\Resolucion;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Models\Voto;
use App\Support\BuscaEntidades;
use App\Support\Fechas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU5 - Gestión de asistencia biométrica, quórum y ejecución de votaciones.
 * RF-06 (asistencia por huella/reconocimiento facial), RF-07 (quórum en tiempo real),
 * RF-15 (votación y cálculo del resultado), RF-16 (mayoría según el tipo de trámite).
 */
class VotacionService
{
    use BuscaEntidades;

    public function __construct(private MiembroService $miembros) {}

    public function asistentes(int|string $idSesion): Collection
    {
        return Asistencia::with('usuario.rol')->where('id_sesion', $this->sesion($idSesion)->id_sesion)->orderBy('hora_ingreso')->get();
    }

    /**
     * RF-06: registra la asistencia (el reloj biométrico C5 envía el CI/identificador del consejero).
     *
     * @return array{asistencia:Asistencia,quorum:array}
     */
    public function registrarAsistencia(int|string $idSesion, string $ciConsejero, string $metodo = 'HUELLA', ?Usuario $actor = null, string $dispositivo = 'C5'): array
    {
        $sesion = $this->sesion($idSesion);
        $usuario = $this->usuarioPorCi($ciConsejero);
        $metodo = mb_strtoupper(trim($metodo) ?: 'HUELLA');

        if (! in_array($metodo, ['HUELLA', 'FACIAL', 'MANUAL'], true)) {
            throw ReglaNegocioException::invalida('El método de asistencia debe ser HUELLA, FACIAL o MANUAL.');
        }
        if (! in_array($sesion->estado, ['PROGRAMADA', 'EN_CURSO'], true)) {
            throw ReglaNegocioException::invalida("La sesión está {$sesion->estado}: no admite registro de asistencia.");
        }
        if ($usuario->estado !== 'ACTIVO' || ! $usuario->esConsejero()) {
            throw ReglaNegocioException::invalida('Solo los consejeros activos (titulares, suplentes habilitados y Presidente) registran asistencia.');
        }
        if (Asistencia::where('id_sesion', $sesion->id_sesion)->where('id_usuario', $usuario->id_usuario)->exists()) {
            throw ReglaNegocioException::invalida('La asistencia de este consejero ya fue registrada en la sesión.');
        }

        $idMandato = null;
        $esSuplente = false;
        if ($usuario->tieneRol('TITULAR_DOCENTE', 'TITULAR_ESTUDIANTE')) {
            $mandato = Mandato::where('estado', 'VIGENTE')->where('id_titular', $usuario->id_usuario)->first()
                ?? throw ReglaNegocioException::invalida('El titular no tiene un mandato vigente.');
            if (Habilitacion::where('id_sesion', $sesion->id_sesion)->where('id_titular', $usuario->id_usuario)->exists()) {
                throw ReglaNegocioException::invalida('El titular presentó excusa (o su suplente fue habilitado): debe asistir el suplente habilitado.');
            }
            $idMandato = $mandato->id_mandato;
        } elseif ($usuario->tieneRol('SUPLENTE_DOCENTE', 'SUPLENTE_ESTUDIANTE')) {
            $mandato = Mandato::where('estado', 'VIGENTE')->where('id_suplente', $usuario->id_usuario)->first()
                ?? throw ReglaNegocioException::invalida('El suplente no está asignado a un mandato vigente.');
            if (! Habilitacion::where('id_sesion', $sesion->id_sesion)->where('id_habilitado', $usuario->id_usuario)->exists()) {
                throw ReglaNegocioException::invalida('El suplente no está habilitado para esta sesión: el titular no presentó excusa ni fue reemplazado.');
            }
            $idMandato = $mandato->id_mandato;
            $esSuplente = true;
        }

        return DB::transaction(function () use ($sesion, $usuario, $idMandato, $esSuplente, $metodo, $dispositivo, $actor) {
            $asistencia = Asistencia::create([
                'id_sesion' => $sesion->id_sesion, 'id_usuario' => $usuario->id_usuario, 'id_mandato' => $idMandato,
                'metodo' => $metodo, 'dispositivo' => $dispositivo, 'es_suplente' => $esSuplente,
            ]);
            $quorum = $this->miembros->quorum($sesion, true);
            // Al lograrse el quórum la sesión puede instalarse (pasa a EN_CURSO).
            if ($quorum['alcanzado'] && $sesion->estado === 'PROGRAMADA') {
                $sesion->update(['estado' => 'EN_CURSO']);
            }
            AuditoriaService::registrar($actor, 'ASISTENCIA_REGISTRADA', 'sesion', $sesion->id_sesion, ['ci' => $usuario->ci, 'metodo' => $metodo]);

            return ['asistencia' => $asistencia->load('usuario.rol'), 'quorum' => $quorum];
        });
    }

    public function quorum(int|string $idSesion): array
    {
        return $this->miembros->quorum($this->sesion($idSesion), true);
    }

    /** RF-15: voto de un consejero presente sobre un documento aprobado por el workflow. */
    public function votar(int|string $idSesion, string $ciConsejero, int|string $idDocumento, string $voto, ?Usuario $actor = null): Voto
    {
        $sesion = $this->sesion($idSesion);
        $usuario = $this->usuarioPorCi($ciConsejero);
        $doc = $this->documento($idDocumento);
        $voto = $this->normalizarVoto($voto);

        if ($sesion->estado !== 'EN_CURSO') {
            throw ReglaNegocioException::invalida('Solo se vota en sesiones EN_CURSO (con quórum alcanzado).');
        }
        if ($doc->id_sesion !== $sesion->id_sesion) {
            throw ReglaNegocioException::invalida('El documento no pertenece al orden del día de esta sesión.');
        }
        if ($doc->estado !== 'APROBADO') {
            throw ReglaNegocioException::invalida("El documento está {$doc->estado}: solo se votan documentos APROBADOS por el flujo de validación.");
        }
        if (! Asistencia::where('id_sesion', $sesion->id_sesion)->where('id_usuario', $usuario->id_usuario)->exists()) {
            throw ReglaNegocioException::invalida('El consejero no registró su asistencia: no puede votar.');
        }
        if (Voto::where('id_documento', $doc->id_documento)->where('id_usuario', $usuario->id_usuario)->exists()) {
            throw ReglaNegocioException::invalida('Este consejero ya emitió su voto para el documento.');
        }

        $v = Voto::create(['id_sesion' => $sesion->id_sesion, 'id_documento' => $doc->id_documento, 'id_usuario' => $usuario->id_usuario, 'voto' => $voto]);
        AuditoriaService::registrar($actor, 'VOTO_REGISTRADO', 'documento', $doc->id_documento, ['ci' => $usuario->ci]);   // el contenido del voto no se audita

        return $v;
    }

    /** Conteo en vivo de la votación. */
    public function conteo(int|string $idSesion, int|string $idDocumento): array
    {
        $sesion = $this->sesion($idSesion);
        $doc = $this->documento($idDocumento);
        $presentes = Asistencia::where('id_sesion', $sesion->id_sesion)->count();
        $votos = Voto::where('id_documento', $doc->id_documento)->get();
        $mayoria = $doc->tipoTramite?->mayoria ?? 'ABSOLUTA';

        return [
            'id_documento' => $doc->id_documento, 'titulo' => $doc->titulo, 'mayoria' => $mayoria,
            'presentes' => $presentes,
            'a_favor' => $votos->where('voto', 'A FAVOR')->count(),
            'en_contra' => $votos->where('voto', 'EN CONTRA')->count(),
            'blanco' => $votos->where('voto', 'BLANCO')->count(),
            'pendientes' => max(0, $presentes - $votos->count()),
            'requerido' => $this->votosRequeridos($mayoria, $presentes),
        ];
    }

    /** RF-15 / RF-16: cierra la votación, calcula el resultado y genera la resolución. */
    public function cerrarVotacion(int|string $idSesion, int|string $idDocumento, ?Usuario $actor = null): Resolucion
    {
        $sesion = $this->sesion($idSesion);
        $doc = $this->documento($idDocumento)->load('tipoTramite');

        if ($sesion->estado !== 'EN_CURSO') {
            throw ReglaNegocioException::invalida('La sesión debe estar EN_CURSO para cerrar una votación.');
        }
        if ($doc->id_sesion !== $sesion->id_sesion || $doc->estado !== 'APROBADO') {
            throw ReglaNegocioException::invalida('El documento debe pertenecer a la sesión y estar APROBADO por el flujo (aún sin votar).');
        }
        $c = $this->conteo($sesion->id_sesion, $doc->id_documento);
        if ($c['a_favor'] + $c['en_contra'] + $c['blanco'] === 0) {
            throw ReglaNegocioException::invalida('No hay votos registrados para este documento.');
        }

        return DB::transaction(function () use ($sesion, $doc, $c, $actor) {
            $aprobada = $c['a_favor'] >= $c['requerido'];
            $anio = now()->year;
            $secuencia = Resolucion::whereYear('fecha_aprobacion', $anio)->count() + 1;

            $res = Resolucion::create([
                'numero' => sprintf('RES-%d-%03d', $anio, $secuencia),
                'id_documento' => $doc->id_documento, 'id_sesion' => $sesion->id_sesion, 'id_tipo_tramite' => $doc->id_tipo_tramite,
                'mayoria_requerida' => $c['mayoria'], 'presentes' => $c['presentes'],
                'votos_favor' => $c['a_favor'], 'votos_contra' => $c['en_contra'], 'votos_blanco' => $c['blanco'],
                'resultado' => $aprobada ? 'APROBADA' : 'RECHAZADA', 'estado' => $aprobada ? 'APROBADA' : 'RECHAZADA',
                'fecha_aprobacion' => now(),
                'fecha_limite_promulgacion' => $aprobada
                    ? Fechas::sumarDiasHabiles(now(), (int) config('consejo.reglas.dias_habiles_promulgacion'))->toDateString() : null,
            ]);
            $doc->update(['estado' => 'VOTADO']);

            NotificacionService::notificarConsejo(
                'RESOLUCION', "Resultado de votación: {$res->numero}",
                "Documento \"{$doc->titulo}\": {$res->resultado} ({$c['a_favor']} a favor, {$c['en_contra']} en contra, {$c['blanco']} en blanco; "
                ."presentes: {$c['presentes']}; mayoría {$c['mayoria']}, requeridos {$c['requerido']})."
            );
            AuditoriaService::registrar($actor, 'VOTACION_CERRADA', 'resolucion', $res->id_resolucion, ['resultado' => $res->resultado]);

            return $res;
        });
    }

    /** Votos necesarios: mayoría absoluta de presentes (Art. 53) o dos tercios (Art. 58 incs. i, l, m). */
    public function votosRequeridos(string $mayoria, int $presentes): int
    {
        if ($presentes <= 0) {
            return 0;
        }

        return $mayoria === 'DOS_TERCIOS' ? (int) ceil($presentes * 2 / 3) : intdiv($presentes, 2) + 1;
    }

    private function normalizarVoto(string $voto): string
    {
        $v = mb_strtoupper(trim(strtr($voto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', '_' => ' '])));
        $mapa = ['A FAVOR' => 'A FAVOR', 'FAVOR' => 'A FAVOR', 'SI' => 'A FAVOR', 'EN CONTRA' => 'EN CONTRA', 'CONTRA' => 'EN CONTRA', 'NO' => 'EN CONTRA', 'BLANCO' => 'BLANCO', 'ABSTENCION' => 'BLANCO'];

        return $mapa[$v] ?? throw ReglaNegocioException::invalida('Voto inválido. Use: A FAVOR, EN CONTRA o BLANCO.');
    }
}
