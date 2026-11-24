<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\BuscarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CorreoController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\FiscalizacionController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MiembroController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\RbacController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ResolucionController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VisitaController;
use App\Http\Controllers\VotacionController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => ['sistema' => 'Consejo Facultativo FICCT', 'grupo' => config('consejo.grupo'), 'estado' => 'en línea']);

// ---- Público (Art. 56 y 57: sesiones públicas y Gaceta) ----
Route::prefix('publico')->group(function () {
    Route::get('sesiones', [FiscalizacionController::class, 'sesionesPublicas']);
    Route::get('actas', [FiscalizacionController::class, 'actasPublicas']);
    Route::get('actas/{idSesion}', [FiscalizacionController::class, 'actaPublica']);
    Route::get('resoluciones', [FiscalizacionController::class, 'resolucionesPublicas']);
});

// Contador de visitas por página (pie de página de cada vista; también en las páginas públicas)
Route::post('visitas', [VisitaController::class, 'registrar'])->middleware('throttle:120,1');

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/password', [AuthController::class, 'cambiarPassword']);
    Route::put('auth/preferencias', [AuthController::class, 'preferencias']);

    // Menú dinámico y búsqueda global
    Route::get('menu', [MenuController::class, 'index']);
    Route::get('buscar', [BuscarController::class, 'index'])->middleware('permiso:buscar');

    // Panel analítico
    Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permiso:reporte.ver');

    // Seguridad: bitácora, matriz de acceso (RBAC) y menú dinámico
    Route::get('bitacora', [BitacoraController::class, 'index'])->middleware('permiso:bitacora.ver');
    Route::get('bitacora/resumen', [BitacoraController::class, 'resumen'])->middleware('permiso:bitacora.ver');
    Route::get('visitas/resumen', [VisitaController::class, 'resumen'])->middleware('permiso:bitacora.ver');
    Route::middleware('permiso:rbac.gestionar')->prefix('rbac')->group(function () {
        Route::get('matriz', [RbacController::class, 'matriz']);
        Route::put('matriz', [RbacController::class, 'alternar']);
        Route::get('menus', [RbacController::class, 'menus']);
        Route::post('menus', [RbacController::class, 'crearMenu']);
        Route::put('menus/{id}', [RbacController::class, 'actualizarMenu'])->whereNumber('id');
        Route::delete('menus/{id}', [RbacController::class, 'eliminarMenu'])->whereNumber('id');
    });

    // Pagos electrónicos (pasarela simulada)
    Route::get('conceptos-pago', [PagoController::class, 'conceptos'])->middleware('permiso:pago.propio');
    Route::post('conceptos-pago', [PagoController::class, 'guardarConcepto'])->middleware('permiso:pago.gestionar');
    Route::put('conceptos-pago/{id}', [PagoController::class, 'guardarConcepto'])->middleware('permiso:pago.gestionar')->whereNumber('id');
    Route::delete('conceptos-pago/{id}', [PagoController::class, 'eliminarConcepto'])->middleware('permiso:pago.gestionar')->whereNumber('id');
    Route::middleware('permiso:pago.propio')->group(function () {
        Route::get('metodos-pago', [PagoController::class, 'metodos']);
        Route::post('metodos-pago', [PagoController::class, 'crearMetodo']);
        Route::delete('metodos-pago/{id}', [PagoController::class, 'eliminarMetodo'])->whereNumber('id');
        Route::post('metodos-pago/{id}/predeterminado', [PagoController::class, 'predeterminar'])->whereNumber('id');
        Route::get('pagos', [PagoController::class, 'misPagos']);
        Route::post('pagos', [PagoController::class, 'pagar']);
        Route::get('pagos/{id}/comprobante', [PagoController::class, 'comprobante'])->whereNumber('id');
        Route::get('planes-pago', [PagoController::class, 'planes']);
        Route::post('planes-pago', [PagoController::class, 'crearPlan']);
        Route::post('planes-pago/{id}/cancelar', [PagoController::class, 'cancelarPlan'])->whereNumber('id');
        Route::post('cuotas/{id}/pagar', [PagoController::class, 'pagarCuota'])->whereNumber('id');
    });
    Route::get('pagos-admin/transacciones', [PagoController::class, 'todos'])->middleware('permiso:pago.gestionar');
    Route::get('pagos-admin/resumen', [PagoController::class, 'resumen'])->middleware('permiso:pago.gestionar');

    // CU1
    Route::get('roles', [UsuarioController::class, 'roles']);
    Route::get('usuarios', [UsuarioController::class, 'index'])->middleware('permiso:usuarios.ver');
    Route::post('usuarios', [UsuarioController::class, 'store'])->middleware('permiso:usuarios.gestionar');
    Route::patch('usuarios/{ci}', [UsuarioController::class, 'update'])->middleware('permiso:usuarios.gestionar');
    Route::delete('usuarios/{ci}', [UsuarioController::class, 'destroy'])->middleware('permiso:usuarios.gestionar');

    // CU2
    Route::get('mandatos', [MiembroController::class, 'index'])->middleware('permiso:miembros.ver');
    Route::post('mandatos', [MiembroController::class, 'store'])->middleware('permiso:miembros.gestionar');
    Route::patch('mandatos/{id}', [MiembroController::class, 'update'])->middleware('permiso:miembros.gestionar');
    Route::post('mandatos/{id}/concluir', [MiembroController::class, 'concluir'])->middleware('permiso:miembros.gestionar');
    Route::get('panel', [MiembroController::class, 'panel'])->middleware('permiso:miembros.ver');
    Route::get('sesiones/{idSesion}/quorum', [MiembroController::class, 'quorum'])->middleware('permiso:miembros.ver');

    // CU3
    Route::get('sesiones', [SesionController::class, 'index'])->middleware('permiso:sesion.ver');
    Route::get('sesiones/{id}', [SesionController::class, 'show'])->middleware('permiso:sesion.ver');
    Route::post('sesiones', [SesionController::class, 'store'])->middleware('permiso:sesion.convocar');
    Route::patch('sesiones/{id}', [SesionController::class, 'update'])->middleware('permiso:sesion.convocar');
    Route::post('sesiones/{id}/estado', [SesionController::class, 'estado'])->middleware('permiso:sesion.convocar');
    Route::post('sesiones/{id}/puntos', [SesionController::class, 'punto'])->middleware('permiso:sesion.convocar');
    Route::get('sesiones/{id}/excusas', [SesionController::class, 'excusas'])->middleware('permiso:sesion.ver');
    Route::post('sesiones/{id}/excusas', [SesionController::class, 'excusar'])->middleware('permiso:sesion.excusa');
    Route::post('sesiones/{id}/habilitaciones', [SesionController::class, 'habilitar'])->middleware('permiso:miembros.gestionar');
    Route::get('sesiones/{id}/habilitados', [SesionController::class, 'habilitados'])->middleware('permiso:sesion.ver');

    // CU4
    Route::get('tramites', [DocumentoController::class, 'tipos'])->middleware('permiso:documento.ver');
    Route::put('tramites/{id}', [DocumentoController::class, 'configurarTipo'])->middleware('permiso:tramite.configurar');
    Route::get('documentos', [DocumentoController::class, 'index'])->middleware('permiso:documento.ver');
    Route::post('documentos', [DocumentoController::class, 'store'])->middleware('permiso:documento.registrar');
    Route::get('documentos/{id}', [DocumentoController::class, 'show'])->middleware('permiso:documento.ver');
    Route::post('documentos/{id}/versiones', [DocumentoController::class, 'version'])->middleware('permiso:documento.registrar');
    Route::post('documentos/{id}/validar', [DocumentoController::class, 'validar'])->middleware('permiso:documento.validar');
    Route::get('documentos/{id}/trazabilidad', [DocumentoController::class, 'trazabilidad'])->middleware('permiso:documento.ver');
    Route::get('documentos/{id}/descargar', [DocumentoController::class, 'descargar'])->middleware('permiso:documento.ver');

    // CU5
    Route::get('sesiones/{idSesion}/asistencias', [VotacionController::class, 'asistencias'])->middleware('permiso:asistencia.ver');
    Route::post('sesiones/{idSesion}/asistencias', [VotacionController::class, 'registrarAsistencia'])->middleware('permiso:asistencia.registrar');
    Route::get('sesiones/{idSesion}/documentos/{idDocumento}/conteo', [VotacionController::class, 'conteo'])->middleware('permiso:asistencia.ver');
    Route::post('sesiones/{idSesion}/documentos/{idDocumento}/votos', [VotacionController::class, 'votar'])->middleware('permiso:votar');
    Route::post('sesiones/{idSesion}/documentos/{idDocumento}/cerrar', [VotacionController::class, 'cerrar'])->middleware('permiso:votacion.cerrar');

    // CU6
    Route::get('resoluciones', [ResolucionController::class, 'index'])->middleware('permiso:resolucion.ver');
    Route::get('resoluciones/{id}', [ResolucionController::class, 'show'])->middleware('permiso:resolucion.ver');
    Route::post('resoluciones/{id}/promulgar', [ResolucionController::class, 'promulgar'])->middleware('permiso:resolucion.promulgar');
    Route::post('resoluciones/{id}/vetar', [ResolucionController::class, 'vetar'])->middleware('permiso:resolucion.vetar');
    Route::post('resoluciones/{id}/mantener', [ResolucionController::class, 'mantener'])->middleware('permiso:resolucion.mantener');
    Route::post('resoluciones/{id}/publicar', [ResolucionController::class, 'publicar'])->middleware('permiso:resolucion.publicar');
    Route::get('sesiones/{idSesion}/acta', [ResolucionController::class, 'acta'])->middleware('permiso:resolucion.ver');
    Route::post('sesiones/{idSesion}/acta', [ResolucionController::class, 'generarActa'])->middleware('permiso:acta.generar');

    // CU7
    Route::get('auditoria', [FiscalizacionController::class, 'auditoria'])->middleware('permiso:auditoria.ver');
    Route::get('notificaciones', [FiscalizacionController::class, 'notificaciones']);
    Route::post('notificaciones/{id}/leer', [FiscalizacionController::class, 'leer']);
    Route::post('notificaciones', [FiscalizacionController::class, 'enviar'])->middleware('permiso:notificacion.enviar');

    // CU8
    Route::prefix('reportes')->middleware('permiso:reporte.ver')->group(function () {
        Route::get('{tipo}/csv', [ReporteController::class, 'csv'])->whereIn('tipo', ['asistencia', 'resoluciones', 'tramites', 'votaciones', 'estadisticas']);
        Route::get('asistencia', [ReporteController::class, 'asistencia']);
        Route::get('resoluciones', [ReporteController::class, 'resoluciones']);
        Route::get('tramites', [ReporteController::class, 'tramites']);
        Route::get('votaciones/{idSesion}', [ReporteController::class, 'votaciones'])->whereNumber('idSesion');
        Route::get('estadisticas', [ReporteController::class, 'estadisticas']);
    });

    // Sistema Vía Mail (consola web)
    Route::get('correo/comandos', [CorreoController::class, 'catalogo']);
    Route::get('correo/log', [CorreoController::class, 'log'])->middleware('permiso:auditoria.ver');
    Route::post('correo/simular', [CorreoController::class, 'simular']);
});
