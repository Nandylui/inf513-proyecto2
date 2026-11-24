<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Documento;
use App\Models\Resolucion;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Models\Voto;
use App\Support\BuscaEntidades;

/**
 * CU8 - Reportes y estadísticas (RF-20: reportes de fiscalización).
 * Todos los reportes usan la misma estructura {titulo, resumen, columnas, filas}
 * para que la API, el frontend y las respuestas por correo los presenten igual.
 */
class ReporteService
{
    use BuscaEntidades;

    public function __construct(private MiembroService $miembros) {}

    /** Asistencia: por sesión (sin parámetro), de una sesión concreta, o por consejero ("CONSEJEROS"). */
    public function asistencia(int|string|null $parametro = null): array
    {
        $parametro = trim((string) $parametro);

        if (mb_strtoupper($parametro) === 'CONSEJEROS') {
            $sesiones = Sesion::whereIn('estado', ['EN_CURSO', 'FINALIZADA'])->pluck('id_sesion');
            $filas = [];
            foreach (Usuario::with('rol')->where('estado', 'ACTIVO')->orderBy('apellidos')->get()->filter->esConsejero() as $u) {
                $asistidas = Asistencia::where('id_usuario', $u->id_usuario)->whereIn('id_sesion', $sesiones)->count();
                $filas[] = [$u->ci, $u->nombre_completo, $u->nombreRol(), $asistidas, $sesiones->count(), $sesiones->count() ? round($asistidas * 100 / $sesiones->count()).'%' : '-'];
            }

            return $this->reporte('Asistencia por consejero', ['sesiones_consideradas' => $sesiones->count()], ['CI', 'Consejero', 'Rol', 'Asistió', 'Sesiones', '%'], $filas);
        }

        if ($parametro !== '' && $parametro !== '*') {
            $sesion = $this->sesion($parametro);
            $q = $this->miembros->quorum($sesion);
            $filas = [];
            $horas = Asistencia::where('id_sesion', $sesion->id_sesion)->get()->keyBy('id_usuario');
            foreach ($this->miembros->panel($sesion) as $f) {
                $a = $horas->get($f['habilitado']['id_usuario']);
                $filas[] = [$f['carrera'], $f['estamento'], $f['habilitado']['nombre'], $f['condicion'], $f['presente'] ? 'PRESENTE' : 'AUSENTE', $a?->hora_ingreso?->format('H:i') ?? '-', $a?->metodo ?? '-'];
            }

            return $this->reporte("Asistencia - sesión #{$sesion->id_sesion}", [
                'presentes' => $q['presentes'], 'requerido' => $q['requerido'], 'quorum' => $q['alcanzado'] ? 'ALCANZADO' : 'NO ALCANZADO',
            ], ['Carrera', 'Estamento', 'Consejero habilitado', 'Condición', 'Asistencia', 'Hora', 'Método'], $filas);
        }

        $filas = [];
        foreach (Sesion::orderByDesc('fecha_hora')->get() as $s) {
            $presentes = Asistencia::where('id_sesion', $s->id_sesion)->count();
            $filas[] = [$s->id_sesion, $s->fecha_hora->format('Y-m-d H:i'), $s->tipo, $s->estado, $presentes, $s->quorum_requerido ?? '-', is_null($s->quorum_alcanzado) ? '-' : ($s->quorum_alcanzado ? 'SI' : 'NO')];
        }

        return $this->reporte('Asistencia por sesión', ['sesiones' => count($filas)], ['Sesión', 'Fecha', 'Tipo', 'Estado', 'Presentes', 'Quórum req.', 'Quórum'], $filas);
    }

    /** Resoluciones aprobadas / rechazadas, opcionalmente filtradas por estado. */
    public function resoluciones(?string $estado = null): array
    {
        $estado = mb_strtoupper(trim((string) $estado));
        $q = Resolucion::with('documento')->orderByDesc('fecha_aprobacion')->orderByDesc('id_resolucion');
        if ($estado !== '' && $estado !== '*') {
            $q->where('estado', $estado);
        }
        $todas = $q->get();
        $filas = $todas->map(fn (Resolucion $r) => [
            $r->numero, $r->documento?->titulo, $r->id_sesion, $r->mayoria_requerida,
            "{$r->votos_favor}/{$r->votos_contra}/{$r->votos_blanco}", $r->resultado, $r->estado, $r->fecha_aprobacion?->format('Y-m-d'),
        ])->all();

        return $this->reporte('Resoluciones', [
            'total' => $todas->count(),
            'aprobadas' => $todas->where('resultado', 'APROBADA')->count(),
            'rechazadas' => $todas->where('resultado', 'RECHAZADA')->count(),
            'vetadas' => $todas->where('estado', 'VETADA')->count(),
            'publicadas' => $todas->where('estado', 'PUBLICADA')->count(),
        ], ['Número', 'Documento', 'Sesión', 'Mayoría', 'Favor/Contra/Blanco', 'Resultado', 'Estado', 'Fecha'], $filas);
    }

    /** Estado de los trámites (documentos) por tipo y estado. */
    public function tramites(): array
    {
        $docs = Documento::with('tipoTramite')->get();
        $estados = ['RECIBIDO', 'EN_REVISION', 'OBSERVADO', 'APROBADO', 'RECHAZADO', 'VOTADO'];
        $filas = [];
        foreach ($docs->groupBy(fn ($d) => $d->tipoTramite?->nombre ?? 'SIN TIPO') as $tipo => $grupo) {
            $filas[] = array_merge([$tipo, $grupo->count()], array_map(fn ($e) => $grupo->where('estado', $e)->count(), $estados));
        }

        return $this->reporte('Estado de trámites', ['documentos' => $docs->count()], array_merge(['Tipo de trámite', 'Total'], $estados), $filas);
    }

    public function votaciones(int|string $idSesion): array
    {
        $sesion = $this->sesion($idSesion);
        $presentes = Asistencia::where('id_sesion', $sesion->id_sesion)->count();
        $filas = [];
        foreach (Documento::where('id_sesion', $sesion->id_sesion)->orderBy('id_documento')->get() as $d) {
            $v = Voto::where('id_documento', $d->id_documento)->get();
            $r = Resolucion::where('id_documento', $d->id_documento)->first();
            $filas[] = [$d->id_documento, $d->titulo, $v->where('voto', 'A FAVOR')->count(), $v->where('voto', 'EN CONTRA')->count(), $v->where('voto', 'BLANCO')->count(), $r ? $r->numero.' - '.$r->resultado : 'SIN CERRAR'];
        }

        return $this->reporte("Votaciones - sesión #{$sesion->id_sesion}", ['presentes' => $presentes, 'documentos' => count($filas)], ['Doc.', 'Título', 'A favor', 'En contra', 'Blanco', 'Resultado'], $filas);
    }

    public function estadisticas(): array
    {
        $sesiones = Sesion::count();
        $res = Resolucion::count();
        $aprobadas = Resolucion::where('resultado', 'APROBADA')->count();
        $conQuorum = Sesion::where('quorum_alcanzado', true)->count();
        $resumen = [
            'usuarios_activos' => Usuario::where('estado', 'ACTIVO')->count(),
            'mandatos_vigentes' => $this->miembros->vigentes()->count(),
            'sesiones' => $sesiones,
            'sesiones_programadas' => Sesion::where('estado', 'PROGRAMADA')->count(),
            'sesiones_finalizadas' => Sesion::where('estado', 'FINALIZADA')->count(),
            'sesiones_con_quorum' => $conQuorum,
            'documentos' => Documento::count(),
            'documentos_en_revision' => Documento::whereIn('estado', ['RECIBIDO', 'EN_REVISION', 'OBSERVADO'])->count(),
            'resoluciones' => $res,
            'resoluciones_aprobadas' => $aprobadas,
            'tasa_aprobacion' => $res ? round($aprobadas * 100 / $res).'%' : '-',
            'resoluciones_vetadas' => Resolucion::where('estado', 'VETADA')->count(),
        ];

        return $this->reporte('Estadísticas generales del Consejo', $resumen, ['Indicador', 'Valor'], array_map(null, array_keys($resumen), array_values($resumen)));
    }

    private function reporte(string $titulo, array $resumen, array $columnas, array $filas): array
    {
        return ['titulo' => $titulo, 'resumen' => $resumen, 'columnas' => $columnas, 'filas' => array_values($filas)];
    }
}
