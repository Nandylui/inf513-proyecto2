<?php

namespace App\Correo;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use App\Services\DocumentoService;
use App\Services\FiscalizacionService;
use App\Services\MiembroService;
use App\Services\ReporteService;
use App\Services\ResolucionService;
use App\Services\SesionService;
use App\Services\UsuarioService;
use App\Services\VotacionService;
use App\Support\Permisos;
use Closure;

/**
 * Catálogo de comandos del Sistema Vía Mail del Consejo Facultativo.
 * Cada comando se identifica por 6 letras (verbo + objeto), igual que en el estilo de la cátedra:
 *   LIS = listar, INS = insertar, MOD = modificar, ELI = eliminar/desactivar, VER = consultar, REG = registrar...
 */
class Catalogo
{
    public const CASOS_DE_USO = [
        1 => 'CU1 - Usuarios y roles',
        2 => 'CU2 - Miembros del Consejo y quórum',
        3 => 'CU3 - Convocatorias, excusas y suplentes',
        4 => 'CU4 - Repositorio documental y flujo de validación',
        5 => 'CU5 - Asistencia, quórum y votaciones',
        6 => 'CU6 - Resoluciones, veto y actas',
        7 => 'CU7 - Fiscalización, auditoría y Gaceta pública',
        8 => 'CU8 - Reportes y estadísticas',
        0 => 'Ayuda',
    ];

    /** @var array<string,array>|null */
    private static ?array $cache = null;

    /** @return array<string,array{cu:int,nombre:string,sintaxis:string,descripcion:string,ejemplo:string,permiso:?string,min:int,max:int,publico:bool,handler:Closure}> */
    public static function todos(): array
    {
        return self::$cache ??= self::construir();
    }

    public static function buscar(string $nombre): ?array
    {
        return self::todos()[mb_strtoupper($nombre)] ?? null;
    }

    /** Comandos con nombre parecido (para sugerir ante un error de tipeo). @return string[] */
    public static function parecidos(string $nombre): array
    {
        $nombre = mb_strtoupper($nombre);
        $r = [];
        foreach (array_keys(self::todos()) as $c) {
            $d = levenshtein($nombre, $c);
            if ($d <= 2 || str_starts_with($c, mb_substr($nombre, 0, 3)) && $d <= 4) {
                $r[$c] = $d;
            }
        }
        asort($r);

        return array_slice(array_keys($r), 0, 4);
    }

    private static function construir(): array
    {
        $c = [];
        $d = function (int $cu, string $nombre, string $sintaxis, string $desc, ?string $permiso, int $min, int $max, Closure $h, string $ejemplo = '', bool $publico = false) use (&$c) {
            $c[$nombre] = ['cu' => $cu, 'nombre' => $nombre, 'sintaxis' => $sintaxis, 'descripcion' => $desc, 'ejemplo' => $ejemplo ?: $sintaxis,
                'permiso' => $permiso, 'min' => $min, 'max' => $max, 'publico' => $publico, 'handler' => $h];
        };
        $s = fn (string $cls) => app($cls);
        $v = fn ($x) => Resultado::texto($x);
        $si = fn ($x) => in_array(mb_strtolower(trim((string) $x)), ['1', 'si', 'sí', 'true', 'verdadero', 'x'], true);

        // ------------------------------------------------------------------ CU1
        $d(1, 'LISUSU', 'LISUSU["filtro"]', 'Lista usuarios (filtro opcional: rol, CI, nombre o correo).', 'usuarios.ver', 0, 1,
            fn ($p) => Resultado::tabla('Usuarios', ['CI', 'Nombre', 'Correo', 'Rol', 'Estado'],
                $s(UsuarioService::class)->listar($p[0] ?? null)->map(fn ($u) => [$u->ci, $u->nombre_completo, $u->correo, $u->nombreRol(), $u->estado])->all()),
            'LISUSU["TITULAR_DOCENTE"]');
        $d(1, 'INSUSU', 'INSUSU["ci","nombres","apellidos","correo","rol","telefono"]', 'Registra un usuario. Roles: ADMINISTRADOR, PRESIDENTE, SECRETARIO, TITULAR_DOCENTE, SUPLENTE_DOCENTE, TITULAR_ESTUDIANTE, SUPLENTE_ESTUDIANTE. Clave inicial = CI.', 'usuarios.gestionar', 5, 6,
            function ($p, $u) use ($s) {
                $n = $s(UsuarioService::class)->crear(['ci' => $p[0], 'nombres' => $p[1], 'apellidos' => $p[2], 'correo' => $p[3], 'rol' => $p[4], 'telefono' => $p[5] ?? null], $u);

                return Resultado::mensaje('Usuario registrado', 'La clave inicial es el CI; debe cambiarse al ingresar al sistema web.', ['CI' => $n->ci, 'Nombre' => $n->nombre_completo, 'Correo' => $n->correo, 'Rol' => $n->nombreRol()]);
            }, 'INSUSU["7654321","Ana","Rojas Vaca","ana@consejo.test","SECRETARIO","70011223"]');
        $d(1, 'MODUSU', 'MODUSU["ci","campo","valor"]', 'Modifica un dato del usuario. Campos: nombres, apellidos, correo, telefono, rol, estado, password.', 'usuarios.gestionar', 3, 3,
            function ($p, $u) use ($s) {
                $n = $s(UsuarioService::class)->modificar($p[0], $p[1], $p[2], $u);

                return Resultado::mensaje('Usuario modificado', '', ['CI' => $n->ci, 'Nombre' => $n->nombre_completo, 'Correo' => $n->correo, 'Rol' => $n->nombreRol(), 'Estado' => $n->estado]);
            }, 'MODUSU["7654321","telefono","71122334"]');
        $d(1, 'ELIUSU', 'ELIUSU["ci"]', 'Desactiva un usuario (no se borra: se conserva el historial y la auditoría).', 'usuarios.gestionar', 1, 1,
            function ($p, $u) use ($s) {
                $n = $s(UsuarioService::class)->desactivar($p[0], $u);

                return Resultado::mensaje('Usuario desactivado', '', ['CI' => $n->ci, 'Nombre' => $n->nombre_completo, 'Estado' => $n->estado]);
            });
        $d(1, 'LISROL', 'LISROL[]', 'Lista los roles y las acciones que cada uno puede ejecutar.', null, 0, 0,
            function () {
                $filas = [];
                foreach (Permisos::ROLES as $rol) {
                    $acc = [];
                    foreach (Permisos::matriz() as $a => $roles) {
                        if (in_array($rol, $roles, true)) {
                            $acc[] = $a;
                        }
                    }
                    $filas[] = [$rol, count($acc).' acciones específicas + las de uso general'];
                }

                return Resultado::tabla('Roles del sistema', ['Rol', 'Alcance'], $filas);
            });
        $d(1, 'VERPER', 'VERPER[]', 'Muestra su perfil y su estado en el Consejo.', null, 0, 0,
            fn ($p, $u) => Resultado::mensaje('Mi perfil', '', ['CI' => $u->ci, 'Nombre' => $u->nombre_completo, 'Correo' => $u->correo, 'Rol' => $u->nombreRol(), 'Estado' => $u->estado]));

        // ------------------------------------------------------------------ CU2
        $d(2, 'LISMAN', 'LISMAN["filtro"]', 'Lista mandatos (titular y suplente por carrera/estamento). Filtro: VIGENTE, CONCLUIDO o carrera.', 'miembros.ver', 0, 1,
            fn ($p) => Resultado::tabla('Mandatos del Consejo', ['ID', 'Carrera', 'Estamento', 'Titular', 'Suplente', 'Inicio', 'Fin', 'Estado'],
                $s(MiembroService::class)->listar($p[0] ?? null)->map(fn ($m) => [$m->id_mandato, $m->carrera, $m->estamento, $m->titular?->nombre_completo, $m->suplente?->nombre_completo, $m->fecha_inicio->toDateString(), $m->fecha_fin->toDateString(), $m->estado])->all()),
            'LISMAN["VIGENTE"]');
        $d(2, 'INSMAN', 'INSMAN["ci_titular","ci_suplente","carrera","estamento","inicio","fin"]', 'Registra un mandato (máx. 2 años, sin reelección, 1 titular + 1 suplente por carrera y estamento). Use "NINGUNO" si no hay suplente.', 'miembros.gestionar', 6, 6,
            function ($p, $u) use ($s) {
                $m = $s(MiembroService::class)->registrar($p[0], mb_strtoupper($p[1]) === 'NINGUNO' || $p[1] === '' ? null : $p[1], $p[2], $p[3], $p[4], $p[5], $u);

                return Resultado::mensaje('Mandato registrado', '', ['ID' => $m->id_mandato, 'Carrera' => $m->carrera, 'Estamento' => $m->estamento, 'Vigencia' => $m->fecha_inicio->toDateString().' a '.$m->fecha_fin->toDateString()]);
            }, 'INSMAN["3010001","3010002","INFORMATICA","DOCENTE","2027-01-01","2028-12-31"]');
        $d(2, 'MODMAN', 'MODMAN["id_mandato","campo","valor"]', 'Modifica un mandato. Campos: suplente (CI), fecha_fin.', 'miembros.gestionar', 3, 3,
            function ($p, $u) use ($s) {
                $m = $s(MiembroService::class)->modificar($p[0], $p[1], $p[2], $u);

                return Resultado::mensaje('Mandato modificado', '', ['ID' => $m->id_mandato, 'Carrera' => $m->carrera, 'Suplente' => $m->suplente?->nombre_completo, 'Fin' => $m->fecha_fin->toDateString()]);
            });
        $d(2, 'CONMAN', 'CONMAN["id_mandato"]', 'Concluye un mandato (pasa al historial).', 'miembros.gestionar', 1, 1,
            function ($p, $u) use ($s) {
                $m = $s(MiembroService::class)->concluir($p[0], $u);

                return Resultado::mensaje('Mandato concluido', '', ['ID' => $m->id_mandato, 'Estado' => $m->estado]);
            });
        $d(2, 'PANSES', 'PANSES["id_sesion"]', 'Panel del Consejo: titulares, suplentes y quién está habilitado (con sesión) o presente.', 'miembros.ver', 0, 1,
            function ($p) use ($s) {
                $sesion = isset($p[0]) && $p[0] !== '' ? $s(SesionService::class)->obtener($p[0]) : null;
                $filas = array_map(fn ($f) => [$f['carrera'], $f['estamento'], $f['titular']['nombre'] ?? '-', $f['suplente']['nombre'] ?? '-', $f['habilitado']['nombre'] ?? '-', $f['condicion'], $sesion ? ($f['presente'] ? 'SI' : 'NO') : '-'], $s(MiembroService::class)->panel($sesion));

                return Resultado::tabla('Panel del Consejo'.($sesion ? " - sesión #{$sesion->id_sesion}" : ''), ['Carrera', 'Estamento', 'Titular', 'Suplente', 'Habilitado', 'Condición', 'Presente'], $filas);
            });
        $d(2, 'VERQUO', 'VERQUO["id_sesion"]', 'Calcula el quórum de una sesión (mayoría absoluta de miembros, Art. 53).', 'miembros.ver', 1, 1,
            function ($p) use ($s) {
                $q = $s(MiembroService::class)->quorum($s(SesionService::class)->obtener($p[0]));

                return Resultado::mensaje('Quórum - sesión #'.$q['id_sesion'], $q['alcanzado'] ? 'QUÓRUM ALCANZADO' : 'QUÓRUM NO ALCANZADO', ['Miembros' => $q['total_miembros'], 'Requeridos' => $q['requerido'], 'Presentes' => $q['presentes'], 'Faltan' => $q['faltan'], 'Regla' => $q['regla']]);
            });

        // ------------------------------------------------------------------ CU3
        $filaSesion = fn ($x) => [$x->id_sesion, $x->fecha_hora->format('Y-m-d H:i'), $x->tipo, $x->estado, $x->lugar];
        $d(3, 'LISSES', 'LISSES["filtro"]', 'Lista sesiones (filtro opcional: estado, tipo o lugar).', 'sesion.ver', 0, 1,
            fn ($p) => Resultado::tabla('Sesiones del Consejo', ['ID', 'Fecha', 'Tipo', 'Estado', 'Lugar'], $s(SesionService::class)->listar($p[0] ?? null)->map($filaSesion)->all()), 'LISSES["PROGRAMADA"]');
        $d(3, 'VERSES', 'VERSES["id_sesion"]', 'Detalle de una sesión: orden del día, habilitaciones y quórum.', 'sesion.ver', 1, 1,
            function ($p) use ($s) {
                $x = $s(SesionService::class)->obtener($p[0]);
                $r = Resultado::tabla("Sesión #{$x->id_sesion}", ['N°', 'Punto', 'Descripción'], $x->puntos->map(fn ($t) => [$t->numero, $t->titulo, $t->descripcion])->all(),
                    ['Fecha' => $x->fecha_hora, 'Lugar' => $x->lugar, 'Tipo' => $x->tipo, 'Estado' => $x->estado, 'Convocada por' => $x->convocada_por, 'Quórum' => is_null($x->quorum_alcanzado) ? 'sin calcular' : ($x->quorum_alcanzado ? 'ALCANZADO' : 'NO ALCANZADO'), 'Habilitaciones de suplentes' => $x->habilitaciones->count()]);

                return $r;
            });
        $d(3, 'INSSES', 'INSSES["fecha_hora","lugar","tipo","orden_del_dia","solicitantes"]', 'Convoca una sesión. Fecha: AAAA-MM-DD HH:MM. Tipo: ORDINARIA o EXTRAORDINARIA. Los puntos del orden del día se separan con ";". Extraordinaria por miembros: indique solicitantes >= 3.', 'sesion.convocar', 3, 5,
            function ($p, $u) use ($s) {
                $x = $s(SesionService::class)->convocar(['fecha_hora' => $p[0], 'lugar' => $p[1], 'tipo' => $p[2], 'orden_del_dia' => $p[3] ?? null, 'solicitantes' => $p[4] ?? 0], $u);

                return Resultado::mensaje('Sesión convocada', 'Se notificó a todos los miembros del Consejo.', ['ID' => $x->id_sesion, 'Fecha' => $x->fecha_hora, 'Lugar' => $x->lugar, 'Tipo' => $x->tipo, 'Puntos' => $x->puntos->count()]);
            }, 'INSSES["2027-03-15 10:00","Sala del Consejo FICCT","ORDINARIA","Aprobación del POA;Informe de gestión"]');
        $d(3, 'MODSES', 'MODSES["id_sesion","campo","valor"]', 'Modifica una sesión PROGRAMADA. Campos: lugar, fecha_hora, tipo.', 'sesion.convocar', 3, 3,
            function ($p, $u) use ($s) {
                $x = $s(SesionService::class)->modificar($p[0], $p[1], $p[2], $u);

                return Resultado::tabla('Sesión modificada', ['ID', 'Fecha', 'Tipo', 'Estado', 'Lugar'], [$filaSesion($x)]);
            });
        $d(3, 'ESTSES', 'ESTSES["id_sesion","estado"]', 'Cambia el estado: EN_CURSO (exige quórum), FINALIZADA o CANCELADA.', 'sesion.convocar', 2, 2,
            function ($p, $u) use ($s) {
                $x = $s(SesionService::class)->cambiarEstado($p[0], $p[1], $u);

                return Resultado::tabla('Estado actualizado', ['ID', 'Fecha', 'Tipo', 'Estado', 'Lugar'], [$filaSesion($x)]);
            });
        $d(3, 'INSPUN', 'INSPUN["id_sesion","titulo","descripcion"]', 'Agrega un punto al orden del día.', 'sesion.convocar', 2, 3,
            function ($p, $u) use ($s) {
                $t = $s(SesionService::class)->agregarPunto($p[0], $p[1], $p[2] ?? null, $u);

                return Resultado::mensaje('Punto agregado', '', ['Sesión' => $t->id_sesion, 'Punto' => $t->numero.'. '.$t->titulo]);
            });
        $d(3, 'LISEXC', 'LISEXC["id_sesion"]', 'Lista las excusas de una sesión.', 'sesion.ver', 1, 1,
            fn ($p) => Resultado::tabla('Excusas - sesión #'.$p[0], ['Titular', 'Motivo', 'Registrada', 'Firmada', 'Sello SHA-256'],
                $s(SesionService::class)->excusas($p[0])->map(fn ($e) => [$e->titular?->nombre_completo, $e->motivo, $e->fecha_registro, $e->firmado_digitalmente, $e->archivo_hash ? mb_substr($e->archivo_hash, 0, 12).'…' : '-'])->all()));
        $d(3, 'INSEXC', 'INSEXC["id_sesion","ci_titular","motivo"]', 'Registra la excusa de un titular; el suplente se habilita automáticamente. El titular puede enviarla desde su propio correo.', 'sesion.excusa', 3, 3,
            function ($p, $u) use ($s) {
                $r = $s(SesionService::class)->registrarExcusa($p[0], $p[1], $p[2], $u);
                $h = $r['habilitacion'];

                return Resultado::mensaje('Excusa registrada', $h ? 'El suplente quedó habilitado para la sesión.' : ($r['advertencia'] ?? 'No hay suplente vigente.'), ['Excusa' => $r['excusa']->id_excusa, 'Suplente habilitado' => $h?->habilitado?->nombre_completo ?? 'ninguno', 'Constancia' => $h?->constancia]);
            }, 'INSEXC["1","3010001","Viaje académico"]');
        $d(3, 'HABSUP', 'HABSUP["id_sesion","ci_titular","motivo"]', 'Habilita manualmente al suplente (por ejemplo, ausencia sin excusa previa).', 'miembros.gestionar', 3, 3,
            function ($p, $u) use ($s) {
                $h = $s(SesionService::class)->habilitarSuplente($p[0], $p[1], $p[2], $u);

                return Resultado::mensaje('Suplente habilitado', $h->constancia, ['Sesión' => $h->id_sesion, 'Habilitado' => $h->habilitado?->nombre_completo]);
            });
        $d(3, 'VERHAB', 'VERHAB["id_sesion"]', 'Muestra quiénes están habilitados para asistir a la sesión.', 'sesion.ver', 1, 1,
            fn ($p) => Resultado::tabla('Habilitados - sesión #'.$p[0], ['Carrera', 'Estamento', 'Titular', 'Habilitado', 'Condición'],
                array_map(fn ($h) => [$h['carrera'] ?? '-', $h['estamento'] ?? '-', $h['titular'] ?? '-', $h['habilitado'] ?? '-', $h['condicion'] ?? '-'], $s(SesionService::class)->habilitados($p[0]))));

        // ------------------------------------------------------------------ CU4
        $filaDoc = fn ($x) => [$x->id_documento, $x->titulo, $x->tipoTramite?->nombre, $x->estado, $x->prioridad, $x->sesion ? '#'.$x->sesion->id_sesion : '-', $x->remitente?->nombre_completo];
        $d(4, 'LISDOC', 'LISDOC["sesion","estado","tipo","texto"]', 'Lista documentos del repositorio, ordenados por prioridad. Use "*" para omitir un filtro.', 'documento.ver', 0, 4,
            function ($p) use ($s, $filaDoc) {
                $f = ['sesion' => $p[0] ?? null, 'estado' => ($p[1] ?? '') === '*' ? null : ($p[1] ?? null), 'tipo' => ($p[2] ?? '') === '*' ? null : ($p[2] ?? null), 'q' => $p[3] ?? null];

                return Resultado::tabla('Documentos', ['ID', 'Título', 'Tipo', 'Estado', 'Prior.', 'Sesión', 'Remitente'], $s(DocumentoService::class)->listar($f)->map($filaDoc)->all());
            }, 'LISDOC["1","APROBADO"]');
        $d(4, 'VERDOC', 'VERDOC["id_documento"]', 'Detalle del documento y su historial de validaciones.', 'documento.ver', 1, 1,
            function ($p) use ($s) {
                $x = $s(DocumentoService::class)->obtener($p[0]);

                return Resultado::tabla("Documento #{$x->id_documento}", ['Paso', 'Rol', 'Usuario', 'Decisión', 'Observaciones'],
                    $x->validaciones->map(fn ($vl) => [$vl->paso, $vl->rol->nombre, $vl->usuario->nombre_completo, $vl->decision, $vl->observaciones])->all(),
                    ['Título' => $x->titulo, 'Tipo' => $x->tipoTramite?->nombre, 'Estado' => $x->estado, 'Prioridad' => $x->prioridad, 'Versión' => $x->version_actual, 'Paso actual' => $x->paso_actual, 'Remitente' => $x->remitente?->nombre_completo, 'Recibido' => $x->fecha_recepcion]);
            });
        $d(4, 'INSDOC', 'INSDOC["titulo","id_sesion","tipo","punto","prioridad","descripcion"]', 'Registra un documento para una sesión (debe presentarse con 48 h de anticipación). Prioridad 1 (alta) a 5 (baja).', 'documento.registrar', 2, 6,
            function ($p, $u) use ($s) {
                $x = $s(DocumentoService::class)->registrar(['titulo' => $p[0], 'id_sesion' => $p[1], 'tipo' => $p[2] ?? null, 'punto' => $p[3] ?? null, 'prioridad' => $p[4] ?? null, 'descripcion' => $p[5] ?? null], $u);

                return Resultado::mensaje('Documento registrado', 'Quedó en el flujo de validación de su tipo de trámite.', ['ID' => $x->id_documento, 'Título' => $x->titulo, 'Estado' => $x->estado, 'Tipo' => $x->tipoTramite?->nombre, 'Prioridad' => $x->prioridad]);
            }, 'INSDOC["POA 2027","1","POA","1","2"]');
        $d(4, 'SUBVER', 'SUBVER["id_documento","comentario"]', 'Registra una nueva versión del documento (el archivo se adjunta desde el sistema web).', 'documento.registrar', 1, 2,
            function ($p, $u) use ($s) {
                $x = $s(DocumentoService::class)->subirVersion($p[0], $u, $p[1] ?? null);

                return Resultado::mensaje('Versión registrada', '', ['Documento' => $x->id_documento, 'Versión actual' => $x->version_actual, 'Estado' => $x->estado]);
            });
        $d(4, 'VALDOC', 'VALDOC["id_documento","decision","observaciones"]', 'Valida el documento en su paso del flujo. Decisión: APROBADO, OBSERVADO o RECHAZADO (las dos últimas exigen observaciones). Solo el rol del paso actual puede validar.', 'documento.validar', 2, 3,
            function ($p, $u) use ($s) {
                $x = $s(DocumentoService::class)->validar($p[0], $u, $p[1], $p[2] ?? null);

                return Resultado::mensaje('Validación registrada', '', ['Documento' => $x->id_documento, 'Estado' => $x->estado, 'Paso actual' => $x->paso_actual]);
            }, 'VALDOC["1","APROBADO","Cumple los requisitos"]');
        $d(4, 'VERFLU', 'VERFLU["id_documento"]', 'Trazabilidad: pasos del flujo, quién validó, cuándo y con qué observaciones.', 'documento.ver', 1, 1,
            function ($p) use ($s) {
                $t = $s(DocumentoService::class)->trazabilidad($p[0]);

                return Resultado::tabla('Trazabilidad - documento #'.$p[0], ['Paso', 'Rol', 'Estado', 'Validado por', 'Fecha', 'Observaciones'],
                    array_map(fn ($x) => [$x['orden'], $x['rol'], $x['estado'], $x['validado_por'], $x['fecha'], $x['observaciones']], $t['pasos']),
                    ['Documento' => $t['documento']['titulo'], 'Estado' => $t['documento']['estado'], 'Mayoría para votar' => $t['documento']['mayoria']]);
            });
        $d(4, 'LISTRA', 'LISTRA[]', 'Lista los tipos de trámite con su flujo de validación y la mayoría requerida.', 'documento.ver', 0, 0,
            fn () => Resultado::tabla('Tipos de trámite', ['ID', 'Trámite', 'Mayoría', 'Flujo (roles en orden)'],
                $s(DocumentoService::class)->tipos()->map(fn ($t) => [$t->id_tipo_tramite, $t->nombre, $t->mayoria, $t->pasos->map(fn ($x) => $x->rol->nombre)->implode(' > ')])->all()));
        $d(4, 'CONTRA', 'CONTRA["id_tipo","roles","mayoria"]', 'Configura el flujo de un trámite. Roles separados por ";" en orden. Mayoría: ABSOLUTA o DOS_TERCIOS.', 'tramite.configurar', 2, 3,
            function ($p, $u) use ($s) {
                $t = $s(DocumentoService::class)->configurarTipo($p[0], explode(';', $p[1]), $p[2] ?? null, $u);

                return Resultado::mensaje('Trámite configurado', '', ['Trámite' => $t->nombre, 'Mayoría' => $t->mayoria, 'Flujo' => $t->pasos->map(fn ($x) => $x->rol->nombre)->implode(' > ')]);
            }, 'CONTRA["CREACION_CARRERA","SECRETARIO;PRESIDENTE","DOS_TERCIOS"]');

        // ------------------------------------------------------------------ CU5
        $d(5, 'REGASI', 'REGASI["id_sesion","ci","metodo"]', 'Registra la asistencia de un consejero (simula la marca del reloj biométrico). Método: HUELLA, FACIAL o MANUAL. Al lograr quórum la sesión se instala.', 'asistencia.registrar', 2, 3,
            function ($p, $u) use ($s) {
                $r = $s(VotacionService::class)->registrarAsistencia($p[0], $p[1], $p[2] ?? 'HUELLA', $u);
                $q = $r['quorum'];

                return Resultado::mensaje('Asistencia registrada', $q['alcanzado'] ? 'QUÓRUM ALCANZADO: la sesión puede desarrollarse.' : "Quórum aún no alcanzado: faltan {$q['faltan']}.", ['Consejero' => $r['asistencia']->usuario->nombre_completo, 'Condición' => $r['asistencia']->es_suplente ? 'SUPLENTE' : 'TITULAR', 'Presentes' => "{$q['presentes']} de {$q['total_miembros']} (se requieren {$q['requerido']})"]);
            }, 'REGASI["1","3010001","HUELLA"]');
        $d(5, 'LISASI', 'LISASI["id_sesion"]', 'Lista los asistentes de la sesión y el estado del quórum.', 'asistencia.ver', 1, 1,
            function ($p) use ($s) {
                $q = $s(VotacionService::class)->quorum($p[0]);

                return Resultado::tabla('Asistencia - sesión #'.$p[0], ['CI', 'Consejero', 'Condición', 'Hora', 'Método'],
                    $s(VotacionService::class)->asistentes($p[0])->map(fn ($a) => [$a->usuario->ci, $a->usuario->nombre_completo, $a->es_suplente ? 'SUPLENTE' : 'TITULAR', $a->hora_ingreso?->format('H:i'), $a->metodo])->all(),
                    ['Quórum' => ($q['alcanzado'] ? 'ALCANZADO' : 'NO ALCANZADO')." ({$q['presentes']}/{$q['requerido']})"]);
            });
        $d(5, 'REGVOT', 'REGVOT["id_sesion","id_documento","voto","ci"]', 'Emite un voto: A FAVOR, EN CONTRA o BLANCO. Sin "ci" vota el propio remitente; el Presidente/Secretario pueden registrar el voto de otro consejero presente. El voto es secreto en la auditoría.', 'votar', 3, 4,
            function ($p, $u) use ($s) {
                $ci = trim($p[3] ?? '') !== '' ? $p[3] : $u->ci;
                if ($ci !== $u->ci && ! $u->tieneRol('PRESIDENTE', 'SECRETARIO')) {
                    throw ReglaNegocioException::prohibido('Solo puede votar con su propio CI; el Presidente o el Secretario pueden registrar votos de otros consejeros presentes.');
                }
                $vt = $s(VotacionService::class)->votar($p[0], $ci, $p[1], $p[2], $u);

                return Resultado::mensaje('Voto registrado', 'Su voto quedó registrado.', ['Sesión' => $vt->id_sesion, 'Documento' => $vt->id_documento]);
            }, 'REGVOT["1","1","A FAVOR"]');
        $d(5, 'VERVOT', 'VERVOT["id_sesion","id_documento"]', 'Conteo de la votación y mayoría requerida.', 'asistencia.ver', 2, 2,
            function ($p) use ($s) {
                $c = $s(VotacionService::class)->conteo($p[0], $p[1]);
                unset($c['id_documento']);

                return Resultado::mensaje('Conteo de votación', '', $c);
            });
        $d(5, 'CERVOT', 'CERVOT["id_sesion","id_documento"]', 'Cierra la votación, determina el resultado según la mayoría y genera la resolución numerada.', 'votacion.cerrar', 2, 2,
            function ($p, $u) use ($s) {
                $r = $s(VotacionService::class)->cerrarVotacion($p[0], $p[1], $u);

                return Resultado::mensaje('Votación cerrada: '.$r->resultado, 'Se generó la resolución y se notificó al Consejo.', ['Resolución' => $r->numero, 'Mayoría' => $r->mayoria_requerida, 'A favor' => $r->votos_favor, 'En contra' => $r->votos_contra, 'Blanco' => $r->votos_blanco, 'Presentes' => $r->presentes, 'Plazo para promulgar' => $r->fecha_limite_promulgacion?->toDateString()]);
            });

        // ------------------------------------------------------------------ CU6
        $filaRes = fn ($r) => [$r->id_resolucion, $r->numero, $r->documento?->titulo, $r->resultado, $r->estado, $r->votos_favor.'/'.$r->votos_contra.'/'.$r->votos_blanco, $r->fecha_limite_promulgacion?->toDateString()];
        $d(6, 'LISRES', 'LISRES["filtro"]', 'Lista resoluciones (filtro opcional: estado o número).', 'resolucion.ver', 0, 1,
            fn ($p) => Resultado::tabla('Resoluciones', ['ID', 'Número', 'Asunto', 'Resultado', 'Estado', 'A favor/Contra/Blanco', 'Límite promulgar'], $s(ResolucionService::class)->listar($p[0] ?? null)->map($filaRes)->all()));
        $d(6, 'VERRES', 'VERRES["id_resolucion"]', 'Detalle de una resolución.', 'resolucion.ver', 1, 1,
            function ($p) use ($s) {
                $r = $s(ResolucionService::class)->obtener($p[0]);

                return Resultado::mensaje("Resolución {$r->numero}", '', ['Asunto' => $r->documento?->titulo, 'Sesión' => '#'.$r->id_sesion, 'Resultado' => $r->resultado, 'Estado' => $r->estado, 'Mayoría' => $r->mayoria_requerida, 'Votos (F/C/B)' => "{$r->votos_favor}/{$r->votos_contra}/{$r->votos_blanco}", 'Límite promulgación' => $r->fecha_limite_promulgacion?->toDateString(), 'Promulgada' => $r->fecha_promulgacion, 'Veto' => $r->veto_fundamento, 'Gaceta' => $r->gaceta_referencia]);
            });
        $d(6, 'PRORES', 'PRORES["id_resolucion"]', 'Promulga la resolución (el Decano tiene 5 días hábiles, Art. 64 a).', 'resolucion.promulgar', 1, 1,
            function ($p, $u) use ($s) {
                $r = $s(ResolucionService::class)->promulgar($p[0], $u);

                return Resultado::mensaje('Resolución promulgada', $r['advertencia'] ?? '', ['Resolución' => $r['resolucion']->numero, 'Estado' => $r['resolucion']->estado]);
            });
        $d(6, 'VETRES', 'VETRES["id_resolucion","fundamento"]', 'Veta la resolución con fundamento (Art. 64 b): suspende su efecto.', 'resolucion.vetar', 2, 2,
            function ($p, $u) use ($s) {
                $r = $s(ResolucionService::class)->vetar($p[0], $p[1], $u);

                return Resultado::mensaje('Resolución vetada', 'El Consejo puede mantenerla con dos tercios de los miembros presentes.', ['Resolución' => $r->numero, 'Estado' => $r->estado]);
            });
        $d(6, 'MANRES', 'MANRES["id_resolucion","votos_favor","presentes"]', 'Resuelve el veto: si hay dos tercios la resolución se mantiene; si no, queda sin efecto.', 'resolucion.mantener', 3, 3,
            function ($p, $u) use ($s) {
                $r = $s(ResolucionService::class)->resolverVeto($p[0], (int) $p[1], (int) $p[2], $u);

                return Resultado::mensaje('Veto resuelto: '.$r->estado, '', ['Resolución' => $r->numero, 'Estado' => $r->estado]);
            });
        $d(6, 'PUBRES', 'PUBRES["id_resolucion","gaceta"]', 'Publica la resolución en la Gaceta Universitaria (Art. 57).', 'resolucion.publicar', 2, 2,
            function ($p, $u) use ($s) {
                $r = $s(ResolucionService::class)->publicar($p[0], $p[1], $u);

                return Resultado::mensaje('Resolución publicada', '', ['Resolución' => $r->numero, 'Gaceta' => $r->gaceta_referencia, 'Estado' => $r->estado]);
            }, 'PUBRES["1","Gaceta N° 12/2027"]');
        $d(6, 'GENACT', 'GENACT["id_sesion"]', 'Genera el acta de la sesión (asistentes, quórum, suplentes, resoluciones) con sello SHA-256.', 'acta.generar', 1, 1,
            function ($p, $u) use ($s) {
                $a = $s(ResolucionService::class)->generarActa($p[0], $u);

                return Resultado::mensaje("Acta de la sesión #{$a->id_sesion}", "\n".$a->contenido, ['Sello SHA-256' => $a->hash]);
            });
        $d(6, 'VERACT', 'VERACT["id_sesion"]', 'Consulta el acta de la sesión.', 'resolucion.ver', 1, 1,
            function ($p) use ($s) {
                $a = $s(ResolucionService::class)->acta($p[0]);

                return Resultado::mensaje("Acta de la sesión #{$a->id_sesion}", "\n".$a->contenido, ['Sello SHA-256' => $a->hash]);
            });

        // ------------------------------------------------------------------ CU7
        $d(7, 'LISAUD', 'LISAUD["ci","entidad","accion","canal"]', 'Bitácora de auditoría (últimos 200 eventos). Use "*" para omitir un filtro. Canal: WEB o CORREO.', 'auditoria.ver', 0, 4,
            function ($p) use ($s) {
                $f = fn ($i) => (isset($p[$i]) && $p[$i] !== '' && $p[$i] !== '*') ? $p[$i] : null;

                return Resultado::tabla('Auditoría', ['Fecha', 'Usuario', 'Acción', 'Entidad', 'Canal'],
                    $s(FiscalizacionService::class)->auditoria(['ci' => $f(0), 'entidad' => $f(1), 'accion' => $f(2), 'canal' => $f(3)])
                        ->map(fn ($a) => [$a->fecha, $a->usuario?->nombre_completo ?? 'sistema', $a->accion, $a->entidad ? $a->entidad.'#'.$a->id_entidad : '-', $a->canal])->all());
            }, 'LISAUD["*","*","VOTO","CORREO"]');
        $d(7, 'LISNOT', 'LISNOT["solo_no_leidas"]', 'Lista sus notificaciones (convocatorias, plazos, resoluciones). Escriba SI para ver solo las no leídas.', null, 0, 1,
            fn ($p, $u) => Resultado::tabla('Mis notificaciones', ['ID', 'Fecha', 'Tipo', 'Asunto', 'Leída'],
                $s(FiscalizacionService::class)->notificaciones($u, $si($p[0] ?? ''))->map(fn ($n) => [$n->id_notificacion, $n->fecha, $n->tipo, $n->asunto, $n->leida])->all()));
        $d(7, 'LEENOT', 'LEENOT["id_notificacion"]', 'Lee una notificación y la marca como leída.', null, 1, 1,
            function ($p, $u) use ($s) {
                $n = $s(FiscalizacionService::class)->marcarLeida($p[0], $u);

                return Resultado::mensaje($n->asunto, $n->mensaje, ['Fecha' => $n->fecha, 'Tipo' => $n->tipo]);
            });
        $d(7, 'ENVNOT', 'ENVNOT["destino","asunto","mensaje"]', 'Envía una notificación a un consejero (CI) o a todo el Consejo ("*").', 'notificacion.enviar', 3, 3,
            function ($p, $u) use ($s) {
                $n = $s(FiscalizacionService::class)->enviar($p[0], $p[1], $p[2], $u);

                return Resultado::mensaje('Notificación enviada', '', ['Destinatarios' => $n]);
            });
        $d(7, 'LISPUB', 'LISPUB["SESIONES|ACTAS|RESOLUCIONES"]', 'Consulta pública (Art. 56 y 57): sesiones, actas o resoluciones publicadas. No requiere estar registrado cuando el modo anónimo está habilitado.', null, 0, 1,
            function ($p) use ($s) {
                $f = $s(FiscalizacionService::class);

                return match (mb_strtoupper($p[0] ?? 'SESIONES')) {
                    'ACTAS' => Resultado::tabla('Actas públicas', ['Sesión', 'Fecha', 'Tipo', 'Sello SHA-256'], $f->actasPublicas()->map(fn ($a) => [$a->id_sesion, $a->sesion?->fecha_hora, $a->sesion?->tipo, mb_substr($a->hash, 0, 16).'…'])->all()),
                    'RESOLUCIONES' => Resultado::tabla('Resoluciones públicas', ['Número', 'Asunto', 'Estado', 'Votos F/C/B', 'Gaceta'], $f->resolucionesPublicas()->map(fn ($r) => [$r->numero, $r->documento?->titulo, $r->estado, "{$r->votos_favor}/{$r->votos_contra}/{$r->votos_blanco}", $r->gaceta_referencia])->all()),
                    'SESIONES' => Resultado::tabla('Sesiones públicas del Consejo', ['ID', 'Fecha', 'Tipo', 'Estado', 'Lugar'], $f->sesionesPublicas()->map(fn ($x) => [$x->id_sesion, $x->fecha_hora, $x->tipo, $x->estado, $x->lugar])->all()),
                    default => throw ReglaNegocioException::invalida('Use SESIONES, ACTAS o RESOLUCIONES.'),
                };
            }, 'LISPUB["RESOLUCIONES"]', true);
        $d(7, 'VERPUB', 'VERPUB["id_sesion"]', 'Acta pública de una sesión.', null, 1, 1,
            function ($p) use ($s) {
                $a = $s(FiscalizacionService::class)->actaPublica($p[0]);

                return Resultado::mensaje("Acta pública - sesión #{$a->id_sesion}", "\n".$a->contenido, ['Sello SHA-256' => $a->hash]);
            }, '', true);

        // ------------------------------------------------------------------ CU8
        $d(8, 'REPASI', 'REPASI["id_sesion | CONSEJEROS"]', 'Reporte de asistencia: por sesión (sin parámetro), de una sesión concreta, o por consejero (CONSEJEROS).', 'reporte.ver', 0, 1,
            fn ($p) => Resultado::reporte($s(ReporteService::class)->asistencia($p[0] ?? null)));
        $d(8, 'REPRES', 'REPRES["estado"]', 'Reporte de resoluciones aprobadas/rechazadas (filtro opcional por estado).', 'reporte.ver', 0, 1,
            fn ($p) => Resultado::reporte($s(ReporteService::class)->resoluciones($p[0] ?? null)));
        $d(8, 'REPTRA', 'REPTRA[]', 'Reporte de trámites: documentos por tipo y estado.', 'reporte.ver', 0, 0,
            fn () => Resultado::reporte($s(ReporteService::class)->tramites()));
        $d(8, 'REPVOT', 'REPVOT["id_sesion"]', 'Reporte de votaciones de una sesión.', 'reporte.ver', 1, 1,
            fn ($p) => Resultado::reporte($s(ReporteService::class)->votaciones($p[0])));
        $d(8, 'REPEST', 'REPEST[]', 'Estadísticas generales del Consejo.', 'reporte.ver', 0, 0,
            fn () => Resultado::reporte($s(ReporteService::class)->estadisticas()));

        // ------------------------------------------------------------------ Ayuda
        $d(0, 'AYUDA', 'AYUDA["comando"]', 'Muestra los comandos disponibles o el detalle de uno.', null, 0, 1,
            function ($p, $u) {
                if (isset($p[0]) && $p[0] !== '') {
                    $x = self::buscar($p[0]) ?? throw ReglaNegocioException::noEncontrado("No existe el comando {$p[0]}. Envíe AYUDA[] para ver el listado.");

                    return Resultado::mensaje("Comando {$x['nombre']}", $x['descripcion'], ['Sintaxis' => $x['sintaxis'], 'Ejemplo' => $x['ejemplo'], 'Permiso' => $x['permiso'] ? 'acción '.$x['permiso'] : 'cualquier usuario registrado', 'Parámetros' => $x['min'] === $x['max'] ? $x['min'] : "{$x['min']} a {$x['max']}"]);
                }
                $filas = [];
                foreach (self::todos() as $x) {
                    $ok = ! $u || ! $x['permiso'] || Permisos::puede($u, $x['permiso']);
                    $filas[] = [self::CASOS_DE_USO[$x['cu']] ?? '', $x['nombre'], $x['sintaxis'], $ok ? 'SI' : 'no'];
                }

                return Resultado::tabla('Comandos del Sistema Vía Mail - Consejo Facultativo ('.config('consejo.grupo').')', ['Caso de uso', 'Comando', 'Sintaxis', 'Permitido'], $filas, [], 'Envíe el comando en el ASUNTO del correo. Para el detalle: AYUDA["INSUSU"].');
            }, 'AYUDA[]', true);

        ksort($c);
        uasort($c, fn ($a, $b) => [$a['cu'] ?: 99, $a['nombre']] <=> [$b['cu'] ?: 99, $b['nombre']]);

        return $c;
    }
}
