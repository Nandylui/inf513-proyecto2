<?php

namespace App\Support;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Matriz única de permisos (RF-01: funciones limitadas según rol y estamento).
 *
 * La matriz vive en la base de datos (tablas permiso / rol_permiso) para que el administrador pueda
 * modificarla desde el sistema. Si las tablas aún no existen o están vacías (p. ej. durante la primera
 * migración) se usa la matriz por defecto definida en este archivo, que también es la que siembra la BD.
 * La usan la API REST (middleware `permiso:`), el menú dinámico y los comandos de correo.
 */
class Permisos
{
    public const ROLES = [
        'ADMINISTRADOR', 'PRESIDENTE', 'SECRETARIO',
        'TITULAR_DOCENTE', 'SUPLENTE_DOCENTE', 'TITULAR_ESTUDIANTE', 'SUPLENTE_ESTUDIANTE',
    ];

    public const CONSEJEROS = ['PRESIDENTE', 'TITULAR_DOCENTE', 'SUPLENTE_DOCENTE', 'TITULAR_ESTUDIANTE', 'SUPLENTE_ESTUDIANTE'];

    /** Permisos que el rol ADMINISTRADOR nunca puede perder (evita quedarse sin acceso al sistema). */
    public const INTOCABLES_ADMIN = ['rbac.gestionar', 'usuarios.ver', 'usuarios.gestionar', 'bitacora.ver'];

    /** Segundos que se conserva la matriz en memoria (los procesos largos, como el lector de correo, la refrescan). */
    private const TTL = 30;

    private static ?array $cache = null;

    private static int $cargadoEn = 0;

    /** Catálogo: código => [módulo, descripción]. */
    public static function catalogo(): array
    {
        return [
            'usuarios.ver' => ['Usuarios', 'Consultar usuarios y roles'],
            'usuarios.gestionar' => ['Usuarios', 'Crear, modificar y desactivar usuarios'],
            'miembros.ver' => ['Miembros', 'Ver mandatos, panel de miembros y quórum'],
            'miembros.gestionar' => ['Miembros', 'Registrar y concluir mandatos; habilitar suplentes'],
            'sesion.ver' => ['Sesiones', 'Consultar sesiones y excusas'],
            'sesion.convocar' => ['Sesiones', 'Convocar, modificar y cambiar el estado de sesiones'],
            'sesion.excusa' => ['Sesiones', 'Presentar excusas de asistencia'],
            'documento.ver' => ['Documentos', 'Consultar documentos y su trazabilidad'],
            'documento.registrar' => ['Documentos', 'Registrar documentos y nuevas versiones'],
            'documento.validar' => ['Documentos', 'Validar documentos en el flujo del trámite'],
            'tramite.configurar' => ['Documentos', 'Configurar tipos de trámite y flujos de validación'],
            'asistencia.ver' => ['Votación', 'Ver asistencia, quórum y conteo de votos'],
            'asistencia.registrar' => ['Votación', 'Registrar asistencia de consejeros'],
            'votar' => ['Votación', 'Emitir voto en documentos aprobados'],
            'votacion.cerrar' => ['Votación', 'Cerrar votaciones y generar la resolución'],
            'resolucion.ver' => ['Resoluciones', 'Consultar resoluciones y actas'],
            'resolucion.promulgar' => ['Resoluciones', 'Promulgar resoluciones (Art. 64)'],
            'resolucion.vetar' => ['Resoluciones', 'Vetar resoluciones (Art. 64)'],
            'resolucion.mantener' => ['Resoluciones', 'Resolver un veto (dos tercios)'],
            'resolucion.publicar' => ['Resoluciones', 'Publicar resoluciones en la Gaceta'],
            'acta.generar' => ['Resoluciones', 'Generar actas de sesión'],
            'auditoria.ver' => ['Fiscalización', 'Consultar la auditoría de acciones del negocio'],
            'notificacion.enviar' => ['Fiscalización', 'Enviar avisos a los miembros del Consejo'],
            'reporte.ver' => ['Reportes', 'Ver reportes y estadísticas del Consejo'],
            'pago.propio' => ['Pagos', 'Gestionar métodos de pago propios y pagar aranceles'],
            'pago.gestionar' => ['Pagos', 'Administrar conceptos de pago y ver todas las transacciones'],
            'bitacora.ver' => ['Seguridad', 'Consultar la bitácora de accesos y el panel de tráfico'],
            'rbac.gestionar' => ['Seguridad', 'Administrar la matriz de acceso y el menú dinámico'],
            'buscar' => ['General', 'Usar la búsqueda global'],
        ];
    }

    /** Matriz por defecto: acción => roles autorizados. '*' = cualquier usuario autenticado. */
    public static function porDefecto(): array
    {
        $gestion = ['ADMINISTRADOR', 'SECRETARIO'];
        $directiva = ['ADMINISTRADOR', 'PRESIDENTE', 'SECRETARIO'];

        return [
            'usuarios.ver' => $directiva,
            'usuarios.gestionar' => $gestion,
            'miembros.ver' => ['*'],
            'miembros.gestionar' => $gestion,
            'sesion.ver' => ['*'],
            'sesion.convocar' => $directiva,
            'sesion.excusa' => array_merge($gestion, self::CONSEJEROS),
            'documento.ver' => ['*'],
            'documento.registrar' => ['*'],
            'documento.validar' => ['*'],          // el rol exigido lo define el flujo del trámite
            'tramite.configurar' => $directiva,
            'asistencia.ver' => ['*'],
            'asistencia.registrar' => $directiva,
            'votar' => self::CONSEJEROS,
            'votacion.cerrar' => ['PRESIDENTE', 'SECRETARIO'],
            'resolucion.ver' => ['*'],
            'resolucion.promulgar' => ['PRESIDENTE'],   // el Decano preside el Consejo
            'resolucion.vetar' => ['PRESIDENTE'],
            'resolucion.mantener' => ['PRESIDENTE', 'SECRETARIO'],
            'resolucion.publicar' => ['PRESIDENTE', 'SECRETARIO'],
            'acta.generar' => ['PRESIDENTE', 'SECRETARIO'],
            'auditoria.ver' => ['ADMINISTRADOR', 'PRESIDENTE'],
            'notificacion.enviar' => $directiva,
            'reporte.ver' => ['*'],
            'pago.propio' => ['*'],
            'pago.gestionar' => $gestion,
            'bitacora.ver' => ['ADMINISTRADOR', 'PRESIDENTE'],
            'rbac.gestionar' => ['ADMINISTRADOR'],
            'buscar' => ['*'],
        ];
    }

    /** Acción => roles autorizados (desde la BD; con respaldo en la matriz por defecto). */
    public static function matriz(): array
    {
        if (self::$cache !== null && (time() - self::$cargadoEn) < self::TTL) {
            return self::$cache;
        }

        $matriz = null;
        try {
            if (Schema::hasTable('permiso') && DB::table('permiso')->exists()) {
                $matriz = [];
                foreach (DB::table('permiso')->pluck('codigo') as $codigo) {
                    $matriz[$codigo] = [];
                }
                $filas = DB::table('rol_permiso')
                    ->join('rol', 'rol.id_rol', '=', 'rol_permiso.id_rol')
                    ->join('permiso', 'permiso.id_permiso', '=', 'rol_permiso.id_permiso')
                    ->get(['permiso.codigo', 'rol.nombre']);
                foreach ($filas as $f) {
                    $matriz[$f->codigo][] = $f->nombre;
                }
            }
        } catch (Throwable) {
            $matriz = null;
        }

        self::$cargadoEn = time();

        return self::$cache = $matriz ?? self::porDefecto();
    }

    public static function limpiarCache(): void
    {
        self::$cache = null;
        self::$cargadoEn = 0;
    }

    public static function puede(Usuario $usuario, string $accion): bool
    {
        $roles = self::matriz()[$accion] ?? [];

        return in_array('*', $roles, true) || $usuario->tieneRol(...$roles);
    }

    /** Códigos de permiso concedidos al rol del usuario. */
    public static function delUsuario(Usuario $usuario): array
    {
        $rol = $usuario->nombreRol();

        return array_values(array_keys(array_filter(
            self::matriz(),
            fn ($roles) => in_array('*', $roles, true) || in_array($rol, $roles, true)
        )));
    }

    public static function exigir(Usuario $usuario, string $accion): void
    {
        if (! self::puede($usuario, $accion)) {
            throw ReglaNegocioException::prohibido(
                "Su rol ({$usuario->nombreRol()}) no tiene permiso para esta operación [{$accion}]."
            );
        }
    }
}
