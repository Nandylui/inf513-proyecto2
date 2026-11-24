<?php

namespace Database\Seeders;

use App\Models\Asistencia;
use App\Models\BitacoraAcceso;
use App\Models\Documento;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\PuntoOrdenDia;
use App\Models\Resolucion;
use App\Models\Sesion;
use App\Models\TipoTramite;
use App\Models\Usuario;
use App\Models\VisitaPagina;
use App\Models\ConceptoPago;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Historial de demostración: sesiones ya realizadas, documentos votados, resoluciones, pagos simulados y
 * tráfico en la bitácora, para que el panel analítico y los reportes tengan datos desde la primera ejecución.
 * Ejecutar solo: php artisan db:seed --class=DemoHistoricoSeeder
 */
class DemoHistoricoSeeder extends Seeder
{
    public function run(): void
    {
        if (Sesion::where('lugar', 'like', '%(demo)%')->exists()) {
            return;   // ya sembrado
        }
        mt_srand(2026);

        $presidente = Usuario::where('correo', 'presidente@consejo.test')->first();
        $secretario = Usuario::where('correo', 'secretario@consejo.test')->first();
        if (! $presidente || ! $secretario) {
            return;   // requiere DemoSeeder
        }
        $consejeros = Usuario::with('rol')->where('estado', 'ACTIVO')->get()->filter->esConsejero()->values();
        $tipos = TipoTramite::pluck('id_tipo_tramite', 'nombre');

        $plan = [
            // [días atrás, tipo, presentes, [[título, trámite, mayoría, favor, contra, estado resolución], ...]]
            [75, 'ORDINARIA', 6, [['Aprobación del POA 2026', 'POA', 'ABSOLUTA', 6, 0, 'PUBLICADA'], ['Convenio con la Facultad Politécnica', 'CONVENIO', 'ABSOLUTA', 5, 1, 'PUBLICADA']]],
            [45, 'ORDINARIA', 5, [['Carga horaria docente semestre II/2026', 'CARGA_HORARIA', 'ABSOLUTA', 5, 0, 'PROMULGADA'], ['Presupuesto extraordinario de laboratorios', 'PRESUPUESTO', 'ABSOLUTA', 2, 3, 'RECHAZADA']]],
            [20, 'ORDINARIA', 7, [['Proyecto curricular de Ingeniería Informática', 'PROYECTO_CURRICULAR', 'ABSOLUTA', 6, 1, 'PROMULGADA'], ['Creación de la Unidad de Posgrado', 'CREACION_CARRERA', 'DOS_TERCIOS', 5, 2, 'VETADA']]],
            [8, 'EXTRAORDINARIA', 6, [['Asignación de becas trabajo 2026', 'BECAS_TRABAJO', 'ABSOLUTA', 6, 0, 'APROBADA']]],
        ];

        $numero = 0;
        foreach ($plan as $i => [$dias, $tipo, $presentes, $docs]) {
            $fecha = now()->subDays($dias)->setTime(9, 0);
            $sesion = Sesion::create([
                'fecha_hora' => $fecha, 'lugar' => 'Sala del Consejo Facultativo FICCT (demo)', 'tipo' => $tipo, 'estado' => 'FINALIZADA',
                'convocada_por' => 'PRESIDENTE', 'id_convocante' => $presidente->id_usuario, 'publica' => true,
                'quorum_requerido' => 4, 'quorum_presentes' => $presentes, 'quorum_alcanzado' => true,
                'orden_del_dia' => collect($docs)->pluck(0)->implode("\n"),
            ]);
            $puntos = [];
            foreach ($docs as $k => $d) {
                $puntos[$k] = PuntoOrdenDia::create(['id_sesion' => $sesion->id_sesion, 'numero' => $k + 1, 'titulo' => $d[0]]);
            }
            $asistentes = $consejeros->take($presentes);
            foreach ($asistentes as $u) {
                Asistencia::create([
                    'id_sesion' => $sesion->id_sesion, 'id_usuario' => $u->id_usuario, 'metodo' => 'HUELLA', 'dispositivo' => 'C5',
                    'es_suplente' => str_starts_with($u->nombreRol(), 'SUPLENTE'), 'hora_ingreso' => $fecha->copy()->addMinutes(mt_rand(-10, 20)),
                ]);
            }
            foreach ($docs as $k => [$titulo, $tramite, $mayoria, $favor, $contra, $estadoRes]) {
                $doc = Documento::create([
                    'titulo' => $titulo, 'descripcion' => 'Documento de demostración presentado al Consejo.', 'id_remitente' => $secretario->id_usuario,
                    'id_sesion' => $sesion->id_sesion, 'id_punto' => $puntos[$k]->id_punto, 'id_tipo_tramite' => $tipos[$tramite] ?? null,
                    'estado' => $estadoRes === 'APROBADA' && $dias < 10 ? 'VOTADO' : 'VOTADO', 'prioridad' => mt_rand(1, 4), 'version_actual' => 1, 'paso_actual' => 1,
                    'fecha_recepcion' => $fecha->copy()->subDays(5),
                ]);
                $votantes = $asistentes->filter(fn ($u) => $u->esConsejero())->values();
                foreach ($votantes as $n => $u) {
                    $voto = $n < $favor ? 'A FAVOR' : ($n < $favor + $contra ? 'EN CONTRA' : 'BLANCO');
                    DB::table('votacion')->insert(['id_sesion' => $sesion->id_sesion, 'id_documento' => $doc->id_documento, 'id_usuario' => $u->id_usuario, 'voto' => $voto, 'fecha' => $fecha->copy()->addMinutes(60 + $n)]);
                }
                $blanco = max(0, $votantes->count() - $favor - $contra);
                $aprobada = $estadoRes !== 'RECHAZADA';
                Resolucion::create([
                    'numero' => sprintf('RES-%d-%03d', $fecha->year, ++$numero), 'id_documento' => $doc->id_documento, 'id_sesion' => $sesion->id_sesion,
                    'id_tipo_tramite' => $tipos[$tramite] ?? null, 'mayoria_requerida' => $mayoria, 'presentes' => $votantes->count(),
                    'votos_favor' => $favor, 'votos_contra' => $contra, 'votos_blanco' => $blanco, 'resultado' => $aprobada ? 'APROBADA' : 'RECHAZADA',
                    'estado' => $estadoRes, 'fecha_aprobacion' => $fecha->copy()->addHours(3),
                    'fecha_limite_promulgacion' => $aprobada ? $fecha->copy()->addDays(7)->toDateString() : null,
                    'fecha_promulgacion' => in_array($estadoRes, ['PROMULGADA', 'PUBLICADA']) ? $fecha->copy()->addDays(3) : null,
                    'fecha_veto' => $estadoRes === 'VETADA' ? $fecha->copy()->addDays(2) : null,
                    'veto_fundamento' => $estadoRes === 'VETADA' ? 'Falta de informe técnico previo (Art. 64 b).' : null,
                    'fecha_publicacion' => $estadoRes === 'PUBLICADA' ? $fecha->copy()->addDays(6) : null,
                    'gaceta_referencia' => $estadoRes === 'PUBLICADA' ? sprintf('Gaceta N° %d/%d', 10 + $numero, $fecha->year) : null,
                ]);
            }
        }

        // Una sesión próxima, para que el calendario y las excusas tengan con qué trabajar.
        $prox = Sesion::create([
            'fecha_hora' => now()->addDays(12)->setTime(9, 0), 'lugar' => 'Sala del Consejo Facultativo FICCT (demo)', 'tipo' => 'ORDINARIA', 'estado' => 'PROGRAMADA',
            'convocada_por' => 'PRESIDENTE', 'id_convocante' => $presidente->id_usuario, 'publica' => true, 'orden_del_dia' => "Informe de gestión\nAprobación de calendario académico",
        ]);
        PuntoOrdenDia::create(['id_sesion' => $prox->id_sesion, 'numero' => 1, 'titulo' => 'Informe de gestión']);
        PuntoOrdenDia::create(['id_sesion' => $prox->id_sesion, 'numero' => 2, 'titulo' => 'Aprobación de calendario académico']);

        $this->pagos($consejeros);
        $this->trafico($consejeros);
    }

    private function pagos($consejeros): void
    {
        $conceptos = ConceptoPago::where('activo', true)->get();
        if ($conceptos->isEmpty()) {
            return;
        }
        foreach ($consejeros->take(5) as $u) {
            $m = MetodoPago::create(['id_usuario' => $u->id_usuario, 'tipo' => 'TARJETA', 'alias' => 'Tarjeta demo', 'titular' => mb_strtoupper($u->nombre_completo), 'marca' => 'VISA', 'ultimos4' => '4242', 'vencimiento' => now()->addYears(2)->format('m/y'), 'predeterminado' => true]);
            foreach (range(1, mt_rand(1, 3)) as $_) {
                $c = $conceptos->random();
                $f = now()->subDays(mt_rand(1, 85))->setTime(mt_rand(8, 18), mt_rand(0, 59));
                $ok = mt_rand(1, 10) > 2;
                Pago::create([
                    'id_usuario' => $u->id_usuario, 'id_concepto' => $c->id_concepto, 'id_metodo' => $m->id_metodo, 'tipo' => 'UNICO', 'monto' => $c->monto, 'moneda' => 'BOB',
                    'estado' => $ok ? 'APROBADO' : 'RECHAZADO', 'referencia' => 'PAG-'.$f->format('Ymd').'-'.strtoupper(Str::random(6)),
                    'codigo_autorizacion' => $ok ? 'SIM-'.strtoupper(Str::random(8)) : null, 'motivo_rechazo' => $ok ? null : 'Fondos insuficientes (simulación).',
                    'detalle_metodo' => 'VISA ****4242', 'created_at' => $f, 'updated_at' => $f,
                ]);
            }
        }
    }

    private function trafico($consejeros): void
    {
        $recursos = ['GET api/sesiones' => 30, 'GET api/documentos' => 22, 'GET api/resoluciones' => 15, 'GET api/miembros' => 9, 'GET api/reportes/estadisticas' => 12,
            'GET api/dashboard' => 26, 'GET api/sesiones/{id}' => 18, 'POST api/sesiones/{idSesion}/documentos/{idDocumento}/votos' => 6, 'GET api/pagos' => 8];
        $ips = ['190.129.54.21', '181.115.200.7', '200.87.33.140', '186.121.9.18', '10.0.0.15'];
        for ($d = 13; $d >= 0; $d--) {
            foreach ($consejeros->random(min(4, $consejeros->count())) as $u) {
                $f = now()->subDays($d)->setTime(mt_rand(7, 20), mt_rand(0, 59));
                $ip = $ips[array_rand($ips)];
                BitacoraAcceso::create(['id_usuario' => $u->id_usuario, 'correo' => $u->correo, 'evento' => 'LOGIN_OK', 'recurso' => 'POST api/auth/login', 'metodo' => 'POST', 'estado_http' => 200, 'ip' => $ip, 'user_agent' => 'Mozilla/5.0 (demo)', 'fecha' => $f]);
                foreach (array_rand($recursos, 3) as $rec) {
                    for ($n = 0, $max = mt_rand(1, (int) ceil($recursos[$rec] / 6)); $n < $max; $n++) {
                        BitacoraAcceso::create(['id_usuario' => $u->id_usuario, 'correo' => $u->correo, 'evento' => 'ACCESO', 'recurso' => $rec, 'metodo' => explode(' ', $rec)[0], 'estado_http' => 200, 'ip' => $ip, 'user_agent' => 'Mozilla/5.0 (demo)', 'fecha' => $f->copy()->addMinutes($n + 1)]);
                    }
                }
            }
            if (mt_rand(1, 10) > 4) {
                BitacoraAcceso::create(['correo' => 'intruso'.mt_rand(1, 3).'@correo.test', 'evento' => 'LOGIN_FALLIDO', 'recurso' => 'POST api/auth/login', 'metodo' => 'POST', 'estado_http' => 401, 'ip' => '45.33.'.mt_rand(1, 200).'.'.mt_rand(1, 200), 'detalle' => 'Usuario inexistente', 'fecha' => now()->subDays($d)->setTime(mt_rand(0, 6), mt_rand(0, 59))]);
            }
        }
        foreach (['/login' => 148, '/panel' => 96, '/sesiones' => 64, '/documentos' => 51, '/resoluciones' => 43, '/votacion' => 37, '/pagos' => 29, '/publico' => 72] as $p => $n) {
            VisitaPagina::updateOrCreate(['pagina' => $p], ['contador' => $n, 'ultima_visita' => now()->subHours(mt_rand(1, 40))]);
        }
    }
}
