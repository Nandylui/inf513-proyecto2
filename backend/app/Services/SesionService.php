<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Excusa;
use App\Models\Habilitacion;
use App\Models\Mandato;
use App\Models\PuntoOrdenDia;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Support\BuscaEntidades;
use App\Support\Fechas;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CU3 - Gestión de convocatorias, excusas y habilitación de suplentes.
 * RF-04 (excusa con anticipación), RF-05 (habilitación automática del suplente),
 * RF-08 (convocatorias ordinarias/extraordinarias), RF-19 (notificaciones).
 */
class SesionService
{
    use BuscaEntidades;

    public function __construct(private MiembroService $miembros) {}

    public function listar(?string $filtro = null): Collection
    {
        $filtro = mb_strtoupper(trim((string) $filtro));
        $q = Sesion::with('puntos')->orderByDesc('fecha_hora');
        if (in_array($filtro, ['PROGRAMADA', 'EN_CURSO', 'FINALIZADA', 'CANCELADA'], true)) {
            $q->where('estado', $filtro);
        } elseif ($filtro === 'PROXIMAS') {
            $q->whereIn('estado', ['PROGRAMADA', 'EN_CURSO'])->where('fecha_hora', '>=', now()->startOfDay());
        }

        return $q->get();
    }

    public function obtener(int|string $id): Sesion
    {
        return $this->sesion($id)->load(['puntos', 'habilitaciones.titular', 'habilitaciones.habilitado']);
    }

    /**
     * @param array{fecha_hora:string,lugar:string,tipo:string,orden_del_dia?:string|array|null,solicitantes?:int|string|null} $d
     */
    public function convocar(array $d, Usuario $actor): Sesion
    {
        $fecha = Fechas::parsear($this->requerido($d['fecha_hora'] ?? '', 'fecha_hora'), 'fecha_hora');
        $lugar = $this->requerido($d['lugar'] ?? '', 'lugar');
        $tipo = mb_strtoupper($this->requerido($d['tipo'] ?? '', 'tipo'));

        if (! in_array($tipo, ['ORDINARIA', 'EXTRAORDINARIA'], true)) {
            throw ReglaNegocioException::invalida('El tipo de sesión debe ser ORDINARIA o EXTRAORDINARIA.');
        }
        if ($fecha->lte(now())) {
            throw ReglaNegocioException::invalida('La fecha de la sesión debe ser futura.');
        }

        // Art. 55: extraordinaria a convocatoria del Presidente o a solicitud de tres o más miembros.
        $solicitantes = (int) ($d['solicitantes'] ?? 0);
        $convocadaPor = 'PRESIDENTE';
        if ($tipo === 'EXTRAORDINARIA' && ! $actor->tieneRol('PRESIDENTE')) {
            if ($solicitantes < (int) config('consejo.reglas.minimo_solicitantes_extraordinaria')) {
                throw ReglaNegocioException::invalida(
                    'Una sesión extraordinaria la convoca el Presidente o se registra con la solicitud de 3 o más miembros (Art. 55). Indique "solicitantes" >= 3.'
                );
            }
            $convocadaPor = 'MIEMBROS';
        }

        $puntos = $this->normalizarPuntos($d['orden_del_dia'] ?? null);

        $sesion = DB::transaction(function () use ($fecha, $lugar, $tipo, $actor, $convocadaPor, $solicitantes, $puntos) {
            $sesion = Sesion::create([
                'fecha_hora' => $fecha, 'lugar' => $lugar, 'tipo' => $tipo, 'estado' => 'PROGRAMADA',
                'orden_del_dia' => $puntos ? implode("\n", array_map(fn ($p, $i) => ($i + 1).'. '.$p, $puntos, array_keys($puntos))) : null,
                'convocada_por' => $convocadaPor, 'id_convocante' => $actor->id_usuario,
                'numero_solicitantes' => $convocadaPor === 'MIEMBROS' ? $solicitantes : 0, 'publica' => true,
            ]);
            foreach ($puntos as $i => $titulo) {
                PuntoOrdenDia::create(['id_sesion' => $sesion->id_sesion, 'numero' => $i + 1, 'titulo' => $titulo]);
            }

            return $sesion;
        });

        $this->miembros->quorum($sesion, true);
        $plazo = $fecha->copy()->subHours((int) config('consejo.reglas.horas_anticipacion_documentos'))->format('d/m/Y H:i');
        NotificacionService::notificarConsejo(
            'CONVOCATORIA',
            "Convocatoria a sesión {$tipo} #{$sesion->id_sesion}",
            "Se convoca a sesión {$tipo} del Consejo Facultativo el {$fecha->format('d/m/Y H:i')} en {$lugar}.\n"
            .($sesion->orden_del_dia ? "Orden del día:\n{$sesion->orden_del_dia}\n" : '')
            ."Plazo para entregar documentación: hasta el {$plazo} (48 h de anticipación)."
        );
        AuditoriaService::registrar($actor, 'SESION_CONVOCADA', 'sesion', $sesion->id_sesion, ['tipo' => $tipo, 'convocada_por' => $convocadaPor]);

        return $sesion->load('puntos');
    }

    public function modificar(int|string $id, string $campo, string $valor, ?Usuario $actor = null): Sesion
    {
        $sesion = $this->sesion($id);
        if ($sesion->estado !== 'PROGRAMADA') {
            throw ReglaNegocioException::invalida('Solo se pueden modificar sesiones PROGRAMADAS.');
        }
        switch (mb_strtolower(trim($campo))) {
            case 'lugar':
                $sesion->lugar = $this->requerido($valor, 'lugar');
                break;
            case 'fecha_hora':
                $f = Fechas::parsear($valor, 'fecha_hora');
                if ($f->lte(now())) {
                    throw ReglaNegocioException::invalida('La nueva fecha debe ser futura.');
                }
                $sesion->fecha_hora = $f;
                break;
            case 'tipo':
                $t = mb_strtoupper(trim($valor));
                if (! in_array($t, ['ORDINARIA', 'EXTRAORDINARIA'], true)) {
                    throw ReglaNegocioException::invalida('El tipo de sesión debe ser ORDINARIA o EXTRAORDINARIA.');
                }
                $sesion->tipo = $t;
                break;
            default:
                throw ReglaNegocioException::invalida('Campo no modificable. Use: lugar, fecha_hora, tipo.');
        }
        $sesion->save();
        AuditoriaService::registrar($actor, 'SESION_MODIFICADA', 'sesion', $sesion->id_sesion, ['campo' => $campo]);

        return $sesion->load('puntos');
    }

    public function cambiarEstado(int|string $id, string $estado, ?Usuario $actor = null): Sesion
    {
        $sesion = $this->sesion($id);
        $estado = mb_strtoupper(trim($estado));
        $permitidas = [
            'PROGRAMADA' => ['EN_CURSO', 'CANCELADA'],
            'EN_CURSO' => ['FINALIZADA'],
        ];
        if (! in_array($estado, $permitidas[$sesion->estado] ?? [], true)) {
            throw ReglaNegocioException::invalida("No se puede pasar una sesión de {$sesion->estado} a {$estado}.");
        }
        if ($estado === 'EN_CURSO') {
            $q = $this->miembros->quorum($sesion, true);
            if (! $q['alcanzado']) {
                throw ReglaNegocioException::invalida("No se puede iniciar la sesión: quórum insuficiente ({$q['presentes']}/{$q['requerido']}).");
            }
        }
        $sesion->update(['estado' => $estado]);
        AuditoriaService::registrar($actor, 'SESION_'.$estado, 'sesion', $sesion->id_sesion);

        return $sesion->load('puntos');
    }

    public function agregarPunto(int|string $id, string $titulo, ?string $descripcion = null, ?Usuario $actor = null): PuntoOrdenDia
    {
        $sesion = $this->sesion($id);
        if (! in_array($sesion->estado, ['PROGRAMADA', 'EN_CURSO'], true)) {
            throw ReglaNegocioException::invalida('La sesión ya no admite puntos del orden del día.');
        }
        $numero = (int) PuntoOrdenDia::where('id_sesion', $sesion->id_sesion)->max('numero') + 1;
        $punto = PuntoOrdenDia::create([
            'id_sesion' => $sesion->id_sesion, 'numero' => $numero,
            'titulo' => $this->requerido($titulo, 'título'), 'descripcion' => $descripcion,
        ]);
        AuditoriaService::registrar($actor, 'PUNTO_AGREGADO', 'sesion', $sesion->id_sesion, ['punto' => $numero]);

        return $punto;
    }

    public function excusas(int|string $idSesion): Collection
    {
        return Excusa::with('titular')->where('id_sesion', $this->sesion($idSesion)->id_sesion)->orderBy('fecha_registro')->get();
    }

    /**
     * RF-04 + RF-05: registra la excusa del titular (con anticipación) y habilita al suplente.
     *
     * @return array{excusa:Excusa,habilitacion:?Habilitacion,advertencia:?string}
     */
    public function registrarExcusa(int|string $idSesion, string $ciTitular, string $motivo, Usuario $actor, ?UploadedFile $archivo = null, bool $firmado = false): array
    {
        $sesion = $this->sesion($idSesion);
        $titular = $this->usuarioPorCi($ciTitular);
        $motivo = $this->requerido($motivo, 'motivo');

        $esTercero = $actor->id_usuario !== $titular->id_usuario;
        if ($esTercero && ! $actor->tieneRol('SECRETARIO', 'ADMINISTRADOR')) {
            throw ReglaNegocioException::prohibido('Solo el propio titular, el Secretario o el Administrador pueden registrar una excusa.');
        }
        if (! $titular->tieneRol('TITULAR_DOCENTE', 'TITULAR_ESTUDIANTE')) {
            throw ReglaNegocioException::invalida('Solo los consejeros titulares pueden presentar excusa.');
        }
        if (! in_array($sesion->estado, ['PROGRAMADA'], true) || $sesion->fecha_hora->lte(now())) {
            throw ReglaNegocioException::invalida('La excusa debe registrarse con anticipación, antes de que la sesión comience.');
        }
        if (Excusa::where('id_titular', $titular->id_usuario)->where('id_sesion', $sesion->id_sesion)->exists()) {
            throw ReglaNegocioException::invalida('El titular ya presentó una excusa para esta sesión.');
        }
        $mandato = Mandato::with('suplente')->where('estado', 'VIGENTE')->where('id_titular', $titular->id_usuario)->first()
            ?? throw ReglaNegocioException::invalida('El titular no tiene un mandato vigente.');

        $datosArchivo = $this->guardarArchivo($archivo, 'excusas/'.$sesion->id_sesion);

        return DB::transaction(function () use ($sesion, $titular, $motivo, $actor, $mandato, $datosArchivo, $firmado) {
            $excusa = Excusa::create([
                'id_titular' => $titular->id_usuario, 'id_sesion' => $sesion->id_sesion, 'motivo' => $motivo,
                'archivo_nombre' => $datosArchivo['nombre'], 'archivo_path' => $datosArchivo['path'],
                'archivo_hash' => $datosArchivo['hash'], 'firmado_digitalmente' => $firmado,
            ]);

            $habilitacion = null;
            $advertencia = null;
            if ($mandato->suplente) {
                $habilitacion = $this->crearHabilitacion($sesion, $mandato, 'EXCUSA', "por excusa presentada ({$motivo})");
                NotificacionService::notificar(
                    $mandato->suplente, 'HABILITACION',
                    "Habilitado como consejero en sesión #{$sesion->id_sesion}",
                    "Usted fue habilitado como consejero suplente de {$titular->nombre_completo} ({$mandato->carrera}) para la sesión del {$sesion->fecha_hora->format('d/m/Y H:i')} en {$sesion->lugar}."
                );
            } else {
                $advertencia = 'El titular no tiene suplente asignado: no se pudo habilitar un reemplazo.';
            }

            $this->miembros->quorum($sesion, true);
            AuditoriaService::registrar($actor, 'EXCUSA_REGISTRADA', 'sesion', $sesion->id_sesion, ['titular' => $titular->ci, 'suplente_habilitado' => (bool) $habilitacion]);

            return ['excusa' => $excusa, 'habilitacion' => $habilitacion?->load(['titular', 'habilitado']), 'advertencia' => $advertencia];
        });
    }

    /** Habilitación manual del suplente acreditado en sala por ausencia del titular (Art. 53). */
    public function habilitarSuplente(int|string $idSesion, string $ciTitular, string $motivo, Usuario $actor): Habilitacion
    {
        $sesion = $this->sesion($idSesion);
        $titular = $this->usuarioPorCi($ciTitular);
        if (! in_array($sesion->estado, ['PROGRAMADA', 'EN_CURSO'], true)) {
            throw ReglaNegocioException::invalida('La sesión ya no admite habilitaciones.');
        }
        $mandato = Mandato::with('suplente')->where('estado', 'VIGENTE')->where('id_titular', $titular->id_usuario)->first()
            ?? throw ReglaNegocioException::invalida('El titular no tiene un mandato vigente.');
        if (! $mandato->suplente) {
            throw ReglaNegocioException::invalida('El titular no tiene suplente asignado.');
        }
        if (Habilitacion::where('id_sesion', $sesion->id_sesion)->where('id_titular', $titular->id_usuario)->exists()) {
            throw ReglaNegocioException::invalida('El suplente ya está habilitado para esta sesión.');
        }
        if (\App\Models\Asistencia::where('id_sesion', $sesion->id_sesion)->where('id_usuario', $titular->id_usuario)->exists()) {
            throw ReglaNegocioException::invalida('El titular ya registró su asistencia en esta sesión.');
        }

        $habilitacion = $this->crearHabilitacion($sesion, $mandato, 'AUSENCIA', 'por ausencia del titular, acreditado en sala (Art. 53)'.($motivo !== '' ? ": {$motivo}" : ''));
        $this->miembros->quorum($sesion, true);
        AuditoriaService::registrar($actor, 'SUPLENTE_HABILITADO', 'sesion', $sesion->id_sesion, ['titular' => $titular->ci]);

        return $habilitacion->load(['titular', 'habilitado']);
    }

    /** RF-03: panel de habilitados para una sesión. */
    public function habilitados(int|string $idSesion): array
    {
        return $this->miembros->panel($this->sesion($idSesion));
    }

    private function crearHabilitacion(Sesion $sesion, Mandato $mandato, string $motivo, string $detalle): Habilitacion
    {
        $constancia = "Se deja constancia que el consejero suplente {$mandato->suplente->nombre_completo} reemplaza al titular "
            ."{$mandato->titular->nombre_completo} ({$mandato->carrera} - {$mandato->estamento}) en la sesión #{$sesion->id_sesion} {$detalle}.";

        return Habilitacion::create([
            'id_sesion' => $sesion->id_sesion, 'id_mandato' => $mandato->id_mandato,
            'id_titular' => $mandato->id_titular, 'id_habilitado' => $mandato->id_suplente,
            'motivo' => $motivo, 'constancia' => $constancia,
        ]);
    }

    /** @return string[] */
    private function normalizarPuntos(string|array|null $orden): array
    {
        if (is_string($orden)) {
            $orden = preg_split('/[;|\n]+/', $orden) ?: [];
        }
        $puntos = [];
        foreach ((array) $orden as $p) {
            $p = trim(preg_replace('/^\s*\d+[\.\)\-]\s*/', '', (string) $p));
            if ($p !== '') {
                $puntos[] = $p;
            }
        }

        return $puntos;
    }

    /** @return array{nombre:?string,path:?string,hash:?string} */
    private function guardarArchivo(?UploadedFile $archivo, string $carpeta): array
    {
        if (! $archivo) {
            return ['nombre' => null, 'path' => null, 'hash' => null];
        }

        return [
            'nombre' => $archivo->getClientOriginalName(),
            'path' => $archivo->store($carpeta),
            'hash' => hash_file('sha256', $archivo->getRealPath()),   // sello de integridad
        ];
    }
}
