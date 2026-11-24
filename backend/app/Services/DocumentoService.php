<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Documento;
use App\Models\DocumentoVersion;
use App\Models\FlujoPaso;
use App\Models\PuntoOrdenDia;
use App\Models\Rol;
use App\Models\TipoTramite;
use App\Models\Usuario;
use App\Models\Validacion;
use App\Support\BuscaEntidades;
use App\Support\Permisos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * CU4 - Repositorio documental, anticipación y flujo de aprobación (workflow).
 * RF-09 (48 h de anticipación), RF-10 (repositorio versionado), RF-11 (organización y prioridad),
 * RF-12 (workflow configurable), RF-13 (registro de validaciones y firmantes), RF-16 (tipos de trámite).
 */
class DocumentoService
{
    use BuscaEntidades;

    public function listar(array $filtros = []): Collection
    {
        $q = Documento::with(['remitente', 'tipoTramite', 'sesion'])
            ->orderBy('prioridad')->orderBy('fecha_recepcion');

        $sesion = $filtros['sesion'] ?? null;
        $estado = mb_strtoupper(trim((string) ($filtros['estado'] ?? '')));
        $tipo = $filtros['tipo'] ?? null;
        $texto = trim((string) ($filtros['q'] ?? ''));

        if ($sesion !== null && $sesion !== '' && $sesion !== '*') {
            $q->where('id_sesion', $this->entero($sesion, 'id_sesion'));
        }
        if (in_array($estado, ['RECIBIDO', 'EN_REVISION', 'OBSERVADO', 'APROBADO', 'RECHAZADO', 'VOTADO'], true)) {
            $q->where('estado', $estado);
        }
        if ($tipo) {
            $q->where('id_tipo_tramite', $this->tipoTramite($tipo)->id_tipo_tramite);
        }
        if ($texto !== '') {
            $q->whereRaw('LOWER(titulo) LIKE ?', ['%'.mb_strtolower($texto).'%']);
        }

        return $q->get();
    }

    public function obtener(int|string $id): Documento
    {
        return $this->documento($id)->load(['remitente', 'sesion', 'tipoTramite.pasos.rol', 'versiones.subidoPor', 'validaciones.usuario', 'validaciones.rol']);
    }

    /**
     * @param array{titulo:string,id_sesion:int|string,tipo?:string|int|null,punto?:int|string|null,prioridad?:int|string|null,descripcion?:?string,ci_remitente?:?string} $d
     */
    public function registrar(array $d, Usuario $actor, ?UploadedFile $archivo = null): Documento
    {
        $titulo = $this->requerido($d['titulo'] ?? '', 'título');
        $sesion = $this->sesion($d['id_sesion'] ?? '');
        $remitente = $actor;
        $ciRem = trim((string) ($d['ci_remitente'] ?? ''));
        if ($ciRem !== '' && $ciRem !== $actor->ci) {
            if (! $actor->tieneRol('SECRETARIO', 'ADMINISTRADOR')) {
                throw ReglaNegocioException::prohibido('Solo el Secretario o el Administrador pueden registrar documentos en nombre de otro usuario.');
            }
            $remitente = $this->usuarioPorCi($ciRem);
        }
        $tipo = $this->tipoTramite($d['tipo'] ?? null);
        $prioridad = (int) ($d['prioridad'] ?? 3);
        if ($prioridad < 1 || $prioridad > 5) {
            throw ReglaNegocioException::invalida('La prioridad debe estar entre 1 (alta) y 5 (baja).');
        }

        $this->validarAnticipacion($sesion);

        $punto = null;
        if (($d['punto'] ?? '') !== '') {
            $punto = PuntoOrdenDia::where('id_sesion', $sesion->id_sesion)->where('numero', $this->entero($d['punto'], 'punto'))->first()
                ?? throw ReglaNegocioException::noEncontrado("La sesión {$sesion->id_sesion} no tiene el punto {$d['punto']} en su orden del día.");
        }

        $sinPasos = ! FlujoPaso::where('id_tipo_tramite', $tipo->id_tipo_tramite)->exists();

        return DB::transaction(function () use ($titulo, $d, $sesion, $punto, $tipo, $prioridad, $remitente, $actor, $archivo, $sinPasos) {
            $doc = Documento::create([
                'titulo' => $titulo, 'descripcion' => $d['descripcion'] ?? null,
                'id_remitente' => $remitente->id_usuario, 'id_sesion' => $sesion->id_sesion,
                'id_punto' => $punto?->id_punto, 'id_tipo_tramite' => $tipo->id_tipo_tramite,
                'estado' => $sinPasos ? 'APROBADO' : 'RECIBIDO', 'prioridad' => $prioridad, 'version_actual' => 1, 'paso_actual' => 1,
            ]);
            $this->crearVersion($doc, 1, $actor, $archivo, 'Versión inicial');
            AuditoriaService::registrar($actor, 'DOCUMENTO_REGISTRADO', 'documento', $doc->id_documento, ['sesion' => $sesion->id_sesion, 'tipo' => $tipo->nombre]);

            return $doc->load(['remitente', 'tipoTramite', 'sesion']);
        });
    }

    /** RF-10: nueva versión del documento (el historial se conserva, sin sobrescribir). */
    public function subirVersion(int|string $id, Usuario $actor, ?string $comentario = null, ?UploadedFile $archivo = null): Documento
    {
        $doc = $this->documento($id);
        if (! in_array($doc->estado, ['RECIBIDO', 'EN_REVISION', 'OBSERVADO'], true)) {
            throw ReglaNegocioException::invalida("El documento está {$doc->estado}: no admite nuevas versiones.");
        }
        if ($doc->id_remitente !== $actor->id_usuario && ! $actor->tieneRol('SECRETARIO', 'ADMINISTRADOR')) {
            throw ReglaNegocioException::prohibido('Solo el remitente, el Secretario o el Administrador pueden subir versiones.');
        }
        $this->validarAnticipacion($doc->sesion);

        return DB::transaction(function () use ($doc, $actor, $comentario, $archivo) {
            $numero = $doc->version_actual + 1;
            $this->crearVersion($doc, $numero, $actor, $archivo, $comentario);
            $doc->version_actual = $numero;
            if ($doc->estado === 'OBSERVADO') {
                $doc->estado = 'EN_REVISION';    // el documento corregido vuelve a revisión en el mismo paso
            }
            $doc->save();
            AuditoriaService::registrar($actor, 'DOCUMENTO_VERSION', 'documento', $doc->id_documento, ['version' => $numero]);

            return $doc->load(['remitente', 'tipoTramite', 'sesion', 'versiones']);
        });
    }

    /**
     * RF-12 / RF-13: valida el documento en el paso actual del flujo del trámite.
     */
    public function validar(int|string $id, Usuario $actor, string $decision, ?string $observaciones = null): Documento
    {
        $doc = $this->documento($id)->load('tipoTramite');
        $decision = mb_strtoupper(trim($decision));
        if (! in_array($decision, ['APROBADO', 'OBSERVADO', 'RECHAZADO'], true)) {
            throw ReglaNegocioException::invalida('La decisión debe ser APROBADO, OBSERVADO o RECHAZADO.');
        }
        if (! in_array($doc->estado, ['RECIBIDO', 'EN_REVISION'], true)) {
            throw ReglaNegocioException::invalida("El documento está {$doc->estado} y no puede validarse en este momento.");
        }
        $observaciones = trim((string) $observaciones);
        if ($decision !== 'APROBADO' && mb_strlen($observaciones) < 5) {
            throw ReglaNegocioException::invalida('Debe indicar las observaciones o el motivo (mínimo 5 caracteres).');
        }

        $pasos = FlujoPaso::with('rol')->where('id_tipo_tramite', $doc->id_tipo_tramite)->orderBy('orden')->get();
        $paso = $pasos->firstWhere('orden', $doc->paso_actual)
            ?? throw ReglaNegocioException::invalida('El flujo del trámite no tiene un paso pendiente para este documento.');
        if (! $actor->tieneRol($paso->rol->nombre)) {
            throw ReglaNegocioException::prohibido("El paso {$paso->orden} del flujo \"{$doc->tipoTramite->nombre}\" debe ser validado por el rol {$paso->rol->nombre}.");
        }

        return DB::transaction(function () use ($doc, $actor, $decision, $observaciones, $paso, $pasos) {
            Validacion::create([
                'id_documento' => $doc->id_documento, 'id_usuario' => $actor->id_usuario, 'id_rol' => $paso->id_rol,
                'paso' => $paso->orden, 'decision' => $decision, 'observaciones' => $observaciones !== '' ? $observaciones : null,
            ]);

            if ($decision === 'APROBADO') {
                if ($paso->orden >= $pasos->max('orden')) {
                    $doc->estado = 'APROBADO';
                } else {
                    $doc->estado = 'EN_REVISION';
                    $doc->paso_actual = $paso->orden + 1;
                }
            } else {
                $doc->estado = $decision;       // OBSERVADO | RECHAZADO
            }
            $doc->save();

            NotificacionService::notificar(
                $doc->remitente, 'VALIDACION', "Documento #{$doc->id_documento}: {$decision}",
                "Su documento \"{$doc->titulo}\" fue {$decision} en el paso {$paso->orden} ({$paso->rol->nombre}).".($observaciones !== '' ? "\nObservaciones: {$observaciones}" : '')
            );
            AuditoriaService::registrar($actor, 'DOCUMENTO_VALIDADO', 'documento', $doc->id_documento, ['paso' => $paso->orden, 'decision' => $decision]);

            return $doc->load(['remitente', 'tipoTramite', 'sesion', 'validaciones.usuario']);
        });
    }

    /** RF-13: recorrido completo: qué roles/usuarios validaron, cuándo y con qué observaciones. */
    public function trazabilidad(int|string $id): array
    {
        $doc = $this->obtener($id);
        $pasos = $doc->tipoTramite ? $doc->tipoTramite->pasos : collect();
        $validaciones = $doc->validaciones;

        return [
            'documento' => ['id_documento' => $doc->id_documento, 'titulo' => $doc->titulo, 'estado' => $doc->estado,
                'tipo' => $doc->tipoTramite?->nombre, 'mayoria' => $doc->tipoTramite?->mayoria, 'version_actual' => $doc->version_actual],
            'pasos' => $pasos->map(function (FlujoPaso $p) use ($doc, $validaciones) {
                $v = $validaciones->where('paso', $p->orden)->last();
                $estado = $v && $v->decision === 'APROBADO' ? 'COMPLETADO'
                    : ($p->orden === $doc->paso_actual && in_array($doc->estado, ['RECIBIDO', 'EN_REVISION', 'OBSERVADO', 'RECHAZADO'], true) ? ($v ? $v->decision : 'ACTUAL') : 'PENDIENTE');

                return [
                    'orden' => $p->orden, 'rol' => $p->rol->nombre, 'estado' => $estado,
                    'validado_por' => $v?->usuario?->nombre_completo, 'fecha' => $v?->fecha?->format('Y-m-d H:i'),
                    'observaciones' => $v?->observaciones,
                ];
            })->values()->all(),
            'historial' => $validaciones->map(fn (Validacion $v) => [
                'paso' => $v->paso, 'rol' => $v->rol->nombre, 'usuario' => $v->usuario->nombre_completo,
                'decision' => $v->decision, 'observaciones' => $v->observaciones, 'fecha' => $v->fecha?->format('Y-m-d H:i'),
            ])->values()->all(),
        ];
    }

    public function tipos(): Collection
    {
        return TipoTramite::with('pasos.rol')->orderBy('id_tipo_tramite')->get();
    }

    /** RF-12 / RF-16: define el flujo (roles en orden) y la mayoría requerida de un tipo de trámite. */
    public function configurarTipo(int|string $id, array $roles, ?string $mayoria = null, ?Usuario $actor = null): TipoTramite
    {
        $tipo = $this->tipoTramite($id);
        if ($mayoria !== null && $mayoria !== '') {
            $mayoria = mb_strtoupper($mayoria);
            if (! in_array($mayoria, ['ABSOLUTA', 'DOS_TERCIOS'], true)) {
                throw ReglaNegocioException::invalida('La mayoría debe ser ABSOLUTA o DOS_TERCIOS.');
            }
        }
        $roles = array_values(array_filter(array_map(fn ($r) => mb_strtoupper(trim((string) $r)), $roles), fn ($r) => $r !== ''));
        foreach ($roles as $r) {
            if (! in_array($r, Permisos::ROLES, true)) {
                throw ReglaNegocioException::invalida("Rol \"{$r}\" inválido en el flujo.");
            }
        }

        DB::transaction(function () use ($tipo, $roles, $mayoria) {
            if ($mayoria) {
                $tipo->update(['mayoria' => $mayoria]);
            }
            FlujoPaso::where('id_tipo_tramite', $tipo->id_tipo_tramite)->delete();
            foreach ($roles as $i => $r) {
                FlujoPaso::create(['id_tipo_tramite' => $tipo->id_tipo_tramite, 'orden' => $i + 1, 'id_rol' => Rol::where('nombre', $r)->value('id_rol')]);
            }
        });
        AuditoriaService::registrar($actor, 'TRAMITE_CONFIGURADO', 'tipo_tramite', $tipo->id_tipo_tramite, ['flujo' => $roles, 'mayoria' => $mayoria]);

        return $tipo->load('pasos.rol');
    }

    public function rutaArchivo(int|string $id, int|string|null $version = null): array
    {
        $doc = $this->documento($id);
        $v = $version === null || $version === ''
            ? DocumentoVersion::where('id_documento', $doc->id_documento)->orderByDesc('numero')->first()
            : DocumentoVersion::where('id_documento', $doc->id_documento)->where('numero', $this->entero($version, 'versión'))->first();
        if (! $v || ! $v->archivo_path || ! Storage::exists($v->archivo_path)) {
            throw ReglaNegocioException::noEncontrado('La versión solicitada no tiene un archivo almacenado.');
        }

        return ['path' => $v->archivo_path, 'nombre' => $v->archivo_nombre ?? basename($v->archivo_path)];
    }

    /** RF-09: la documentación se entrega con al menos 48 horas de anticipación. */
    private function validarAnticipacion(?\App\Models\Sesion $sesion): void
    {
        if (! $sesion) {
            return;
        }
        if (! in_array($sesion->estado, ['PROGRAMADA'], true)) {
            throw ReglaNegocioException::invalida("La sesión {$sesion->id_sesion} está {$sesion->estado}: ya no recibe documentación.");
        }
        $horas = (int) config('consejo.reglas.horas_anticipacion_documentos');
        $limite = $sesion->fecha_hora->copy()->subHours($horas);
        if (now()->gt($limite)) {
            throw ReglaNegocioException::invalida(
                "Plazo vencido: la documentación debe entregarse con {$horas} horas de anticipación (hasta el {$limite->format('d/m/Y H:i')})."
            );
        }
    }

    private function crearVersion(Documento $doc, int $numero, Usuario $actor, ?UploadedFile $archivo, ?string $comentario): DocumentoVersion
    {
        $path = $hash = $nombre = null;
        if ($archivo) {
            $nombre = $archivo->getClientOriginalName();
            $path = $archivo->store("documentos/{$doc->id_documento}");
            $hash = hash_file('sha256', $archivo->getRealPath());
        }

        return DocumentoVersion::create([
            'id_documento' => $doc->id_documento, 'numero' => $numero, 'archivo_nombre' => $nombre,
            'archivo_path' => $path, 'archivo_hash' => $hash, 'id_subido_por' => $actor->id_usuario, 'comentario' => $comentario,
        ]);
    }
}
