<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\Resolucion;
use App\Services\DocumentoService;
use App\Services\MiembroService;
use App\Services\ResolucionService;
use App\Services\SesionService;
use App\Services\VotacionService;
use Tests\TestCase;

/** Recorre el ciclo completo del Consejo con los servicios del dominio (CU1 a CU6). */
class FlujoConsejoTest extends TestCase
{
    private function crearSesionFutura(string $tipo = 'ORDINARIA', int $dias = 10)
    {
        return app(SesionService::class)->convocar([
            'fecha_hora' => now()->addDays($dias)->format('Y-m-d').' 10:00',
            'lugar' => 'Sala del Consejo FICCT', 'tipo' => $tipo,
            'orden_del_dia' => 'Aprobación del POA; Convenio interuniversitario',
        ], $this->presidente());
    }

    public function test_panel_y_quorum_de_miembros_vigentes(): void
    {
        $miembros = app(MiembroService::class);
        $sesion = $this->crearSesionFutura();

        $panel = $miembros->panel($sesion);
        $this->assertCount(7, $panel);                    // Presidente + 6 mandatos (3 carreras x 2 estamentos)
        $q = $miembros->quorum($sesion);
        $this->assertSame(7, $q['total_miembros']);
        $this->assertSame(4, $q['requerido']);            // mayoría absoluta de 7
        $this->assertFalse($q['alcanzado']);
        $this->assertCount(2, $sesion->puntos);
    }

    public function test_excusa_habilita_al_suplente_y_no_deja_asistir_al_titular(): void
    {
        $sesion = $this->crearSesionFutura();
        $res = app(SesionService::class)->registrarExcusa($sesion->id_sesion, '3010001', 'Viaje académico', $this->usuario('titular.docente.inf@consejo.test'));

        $this->assertNotNull($res['habilitacion']);
        $this->assertStringContainsString('reemplaza al titular', $res['habilitacion']->constancia);

        $votaciones = app(VotacionService::class);
        try {
            $votaciones->registrarAsistencia($sesion->id_sesion, '3010001');
            $this->fail('El titular excusado no debe poder asistir.');
        } catch (ReglaNegocioException $e) {
            $this->assertStringContainsString('excusa', $e->getMessage());
        }
        $r = $votaciones->registrarAsistencia($sesion->id_sesion, '3010002');   // el suplente sí
        $this->assertTrue($r['asistencia']->es_suplente);
    }

    public function test_ciclo_completo_hasta_la_publicacion_y_el_acta(): void
    {
        $sesiones = app(SesionService::class);
        $documentos = app(DocumentoService::class);
        $votaciones = app(VotacionService::class);
        $resoluciones = app(ResolucionService::class);

        $sesion = $this->crearSesionFutura();

        // CU4: documento POA (flujo: SECRETARIO -> PRESIDENTE)
        $doc = $documentos->registrar(['titulo' => 'POA 2027', 'id_sesion' => $sesion->id_sesion, 'tipo' => 'POA', 'punto' => 1], $this->secretario());
        $this->assertSame('RECIBIDO', $doc->estado);

        try {   // el paso 1 es del SECRETARIO: el Presidente no puede saltarse el orden
            $documentos->validar($doc->id_documento, $this->presidente(), 'APROBADO');
            $this->fail('Debe respetar el rol del paso.');
        } catch (ReglaNegocioException $e) {
            $this->assertSame(403, $e->estado);
        }
        $documentos->validar($doc->id_documento, $this->secretario(), 'APROBADO', 'Cumple requisitos');
        $doc = $documentos->validar($doc->id_documento, $this->presidente(), 'APROBADO');
        $this->assertSame('APROBADO', $doc->estado);

        $traza = $documentos->trazabilidad($doc->id_documento);
        $this->assertSame(['COMPLETADO', 'COMPLETADO'], array_column($traza['pasos'], 'estado'));

        // CU5: asistencia de 4 de 7 -> quórum -> la sesión se instala
        $ci = ['2000001', '2000002' /* secretario no cuenta */, '3010001', '3020001', '3030001', '4010001'];
        foreach (['2000001', '3010001', '3020001', '3030001'] as $c) {
            $r = $votaciones->registrarAsistencia($sesion->id_sesion, $c, 'HUELLA');
        }
        $this->assertTrue($r['quorum']['alcanzado']);
        $this->assertSame('EN_CURSO', $sesion->fresh()->estado);

        // votación: 3 a favor, 1 en contra -> aprobada por mayoría absoluta (requiere 3 de 4)
        $votaciones->votar($sesion->id_sesion, '2000001', $doc->id_documento, 'A FAVOR');
        $votaciones->votar($sesion->id_sesion, '3010001', $doc->id_documento, 'a favor');
        $votaciones->votar($sesion->id_sesion, '3020001', $doc->id_documento, 'SI');
        $votaciones->votar($sesion->id_sesion, '3030001', $doc->id_documento, 'EN CONTRA');
        try {
            $votaciones->votar($sesion->id_sesion, '3030001', $doc->id_documento, 'BLANCO');
            $this->fail('No debe permitir votar dos veces.');
        } catch (ReglaNegocioException) {
        }
        $res = $votaciones->cerrarVotacion($sesion->id_sesion, $doc->id_documento, $this->presidente());
        $this->assertSame('APROBADA', $res->resultado);
        $this->assertStringStartsWith('RES-'.now()->year.'-001', $res->numero);
        $this->assertNotNull($res->fecha_limite_promulgacion);

        // CU6: promulgar y publicar en la Gaceta
        $resoluciones->promulgar($res->id_resolucion, $this->presidente());
        $res = $resoluciones->publicar($res->id_resolucion, 'Gaceta N° 12/2026', $this->secretario());
        $this->assertSame('PUBLICADA', $res->estado);

        $acta = $resoluciones->generarActa($sesion->id_sesion, $this->secretario());
        $this->assertStringContainsString('QUÓRUM ALCANZADO', $acta->contenido);
        $this->assertStringContainsString($res->numero, $acta->contenido);
        $this->assertSame(64, strlen($acta->hash));
    }

    public function test_dos_tercios_para_creacion_de_carrera_y_veto_del_decano(): void
    {
        $documentos = app(DocumentoService::class);
        $votaciones = app(VotacionService::class);
        $resoluciones = app(ResolucionService::class);
        $sesion = $this->crearSesionFutura();

        $doc = $documentos->registrar(['titulo' => 'Creación de la carrera de Ciberseguridad', 'id_sesion' => $sesion->id_sesion, 'tipo' => 'CREACION_CARRERA'], $this->secretario());
        $documentos->validar($doc->id_documento, $this->secretario(), 'APROBADO');
        $documentos->validar($doc->id_documento, $this->usuario('titular.docente.inf@consejo.test'), 'APROBADO');
        $documentos->validar($doc->id_documento, $this->usuario('titular.estudiante.inf@consejo.test'), 'APROBADO');
        $documentos->validar($doc->id_documento, $this->presidente(), 'APROBADO');

        $doc2 = $documentos->registrar(['titulo' => 'Convenio con la UAB', 'id_sesion' => $sesion->id_sesion, 'tipo' => 'LICENCIA'], $this->secretario());
        $documentos->validar($doc2->id_documento, $this->secretario(), 'APROBADO');

        foreach (['2000001', '3010001', '3020001', '3030001', '4010001', '4020001'] as $c) {
            $votaciones->registrarAsistencia($sesion->id_sesion, $c);
        }
        // 6 presentes -> dos tercios = 4 votos. Solo 3 a favor: se rechaza aunque supere la mitad.
        foreach (['2000001', '3010001', '3020001'] as $c) {
            $votaciones->votar($sesion->id_sesion, $c, $doc->id_documento, 'A FAVOR');
        }
        foreach (['3030001', '4010001'] as $c) {
            $votaciones->votar($sesion->id_sesion, $c, $doc->id_documento, 'EN CONTRA');
        }
        $this->assertSame(4, $votaciones->conteo($sesion->id_sesion, $doc->id_documento)['requerido']);
        $res = $votaciones->cerrarVotacion($sesion->id_sesion, $doc->id_documento);
        $this->assertSame('RECHAZADA', $res->resultado);

        // Veto: otra resolución aprobada y luego vetada por el Decano
        foreach (['2000001', '3010001', '3020001', '3030001'] as $c) {
            $votaciones->votar($sesion->id_sesion, $c, $doc2->id_documento, 'A FAVOR');
        }
        $res2 = $votaciones->cerrarVotacion($sesion->id_sesion, $doc2->id_documento);
        $this->assertSame('APROBADA', $res2->estado);

        $veto = $resoluciones->vetar($res2->id_resolucion, 'Contraviene el presupuesto aprobado de la gestión.', $this->presidente());
        $this->assertSame('VETADA', $veto->estado);
        $mantenida = $resoluciones->resolverVeto($res2->id_resolucion, 4, 6, $this->presidente());   // 4/6 = 2/3
        $this->assertSame('MANTENIDA', $mantenida->estado);
    }

    public function test_documentos_requieren_48_horas_de_anticipacion(): void
    {
        $sesion = app(SesionService::class)->convocar([
            'fecha_hora' => now()->addHours(30)->format('Y-m-d H:i'), 'lugar' => 'Sala', 'tipo' => 'ORDINARIA',
        ], $this->presidente());

        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('Plazo vencido');
        app(DocumentoService::class)->registrar(['titulo' => 'Tarde', 'id_sesion' => $sesion->id_sesion], $this->secretario());
    }

    public function test_extraordinaria_requiere_presidente_o_tres_miembros(): void
    {
        $this->expectException(ReglaNegocioException::class);
        app(SesionService::class)->convocar([
            'fecha_hora' => now()->addDays(5)->format('Y-m-d H:i'), 'lugar' => 'Sala', 'tipo' => 'EXTRAORDINARIA', 'solicitantes' => 2,
        ], $this->secretario());
    }

    public function test_mandato_no_permite_reeleccion_ni_mas_de_dos_anios(): void
    {
        $miembros = app(MiembroService::class);
        $miembros->concluir(\App\Models\Mandato::where('carrera', 'INFORMATICA')->where('estamento', 'DOCENTE')->value('id_mandato'));
        try {   // el mismo titular no puede volver al mismo cargo
            $miembros->registrar('3010001', '3010002', 'INFORMATICA', 'DOCENTE', now()->toDateString(), now()->addYears(2)->toDateString());
            $this->fail('Debe impedir la reelección.');
        } catch (ReglaNegocioException $e) {
            $this->assertStringContainsString('reelección', $e->getMessage());
        }
        $this->expectException(ReglaNegocioException::class);
        $this->expectExceptionMessage('2 años');
        $miembros->registrar('3020001', 'NINGUNO', 'REDES', 'DOCENTE', '2027-01-01', '2029-06-01');
    }
}
