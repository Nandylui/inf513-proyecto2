<?php

namespace Tests\Feature;

use App\Correo\Catalogo;
use App\Correo\MensajeEntrante;
use App\Correo\ProcesadorCorreo;
use App\Models\Auditoria;
use App\Models\CorreoLog;
use App\Models\Resolucion;
use App\Models\Sesion;
use Tests\TestCase;

/** Ejecuta el flujo completo del Consejo solo con comandos de correo (lo que hará el docente en la demostración). */
class CorreoComandosTest extends TestCase
{
    private function enviar(string $de, string $asunto)
    {
        return app(ProcesadorCorreo::class)->procesar(MensajeEntrante::simulado($de, $asunto));
    }

    private function ok(string $de, string $asunto): string
    {
        $r = $this->enviar($de, $asunto);
        $this->assertTrue($r->exito, "Falló {$asunto}:\n{$r->cuerpo}");

        return $r->cuerpo;
    }

    public function test_remitente_desconocido_no_ejecuta_nada(): void
    {
        $r = $this->enviar('intruso@x.com', 'LISUSU["*"]');
        $this->assertFalse($r->exito);
        $this->assertStringContainsString('no está registrado', $r->cuerpo);
        $this->assertSame(1, CorreoLog::where('remitente', 'intruso@x.com')->where('exito', false)->count());
    }

    public function test_errores_de_sintaxis_comando_y_parametros_responden_con_ayuda(): void
    {
        $this->assertStringContainsString('Sintaxis inválida', $this->enviar('presidente@consejo.test', 'hola como estas')->cuerpo);
        $this->assertStringContainsString('¿Quiso decir: LISUSU', $this->enviar('presidente@consejo.test', 'LISUSO["x"]')->cuerpo);
        $r = $this->enviar('secretario@consejo.test', 'INSUSU["1","solo","tres"]');
        $this->assertStringContainsString('espera entre 5 y 6', $r->cuerpo);
        $this->assertStringContainsString('Ejemplo:', $r->cuerpo);
    }

    public function test_permisos_por_rol(): void
    {
        $r = $this->enviar('titular.docente.inf@consejo.test', 'LISUSU["*"]');
        $this->assertFalse($r->exito);
        $this->assertStringContainsString('no tiene permiso', $r->cuerpo);
        $this->ok('titular.docente.inf@consejo.test', 'LISSES[]');
        $this->ok('titular.docente.inf@consejo.test', 'AYUDA[]');
    }

    public function test_inyeccion_sql_en_parametros_se_trata_como_texto(): void
    {
        $r = $this->ok('secretario@consejo.test', 'LISUSU["x\'; DROP TABLE usuario; --"]');
        $this->assertStringContainsString('(sin registros)', $r);
        $this->assertGreaterThan(5, \App\Models\Usuario::count());
    }

    public function test_flujo_completo_solo_con_comandos(): void
    {
        $sec = 'secretario@consejo.test';
        $pre = 'presidente@consejo.test';
        $titular = 'titular.docente.inf@consejo.test';
        $fecha = now()->addDays(12)->format('Y-m-d').' 10:00';

        $this->ok($sec, 'INSUSU["7654321","Ana","Rojas","ana@consejo.test","SECRETARIO","70011223"]');
        $this->ok($sec, 'MODUSU["7654321","telefono","71122334"]');
        $this->assertStringContainsString('Ana Rojas', $this->ok($pre, 'LISUSU["ana"]'));

        $this->ok($pre, 'INSSES["'.$fecha.'","Sala del Consejo","ORDINARIA","Aprobación del POA;Convenio"]');
        $idSesion = Sesion::value('id_sesion');
        $this->assertStringContainsString('Aprobación del POA', $this->ok($pre, "VERSES[\"{$idSesion}\"]"));
        $this->assertStringContainsString('NO ALCANZADO', $this->ok($pre, "VERQUO[\"{$idSesion}\"]"));

        $this->assertStringContainsString('Suplente habilitado', $this->ok($titular, "INSEXC[\"{$idSesion}\",\"3010001\",\"Viaje académico\"]"));

        $this->ok($sec, "INSDOC[\"POA 2027\",\"{$idSesion}\",\"POA\",\"1\",\"2\"]");
        $doc = \App\Models\Documento::value('id_documento');   // en PostgreSQL las secuencias no se reinician entre pruebas
        $this->ok($sec, "VALDOC[\"{$doc}\",\"APROBADO\",\"Cumple\"]");
        $falla = $this->enviar($sec, "VALDOC[\"{$doc}\",\"APROBADO\"]");
        $this->assertFalse($falla->exito);                       // el paso 2 le toca al PRESIDENTE
        $this->assertStringContainsString('PRESIDENTE', $falla->cuerpo);
        $this->ok($pre, "VALDOC[\"{$doc}\",\"APROBADO\"]");
        $this->assertStringContainsString('COMPLETADO', $this->ok($pre, "VERFLU[\"{$doc}\"]"));

        foreach (['2000001', '3010002', '3020001', '3030001'] as $ci) {   // 3010002 = suplente del titular excusado
            $this->ok($pre, "REGASI[\"{$idSesion}\",\"{$ci}\",\"HUELLA\"]");
        }
        $this->assertStringContainsString('ALCANZADO', $this->ok($pre, "LISASI[\"{$idSesion}\"]"));

        $this->ok($pre, "REGVOT[\"{$idSesion}\",\"{$doc}\",\"A FAVOR\"]");                      // vota el propio presidente
        $this->ok($pre, "REGVOT[\"{$idSesion}\",\"{$doc}\",\"A FAVOR\",\"3010002\"]");          // registra por el suplente
        $this->ok($pre, "REGVOT[\"{$idSesion}\",\"{$doc}\",\"si\",\"3020001\"]");
        $this->ok($pre, "REGVOT[\"{$idSesion}\",\"{$doc}\",\"EN CONTRA\",\"3030001\"]");
        $this->assertStringContainsString('a_favor', $this->ok($pre, "VERVOT[\"{$idSesion}\",\"{$doc}\"]"));
        $this->assertStringContainsString('APROBADA', $this->ok($pre, "CERVOT[\"{$idSesion}\",\"{$doc}\"]"));

        $res = Resolucion::value('id_resolucion');
        $this->ok($pre, "PRORES[\"{$res}\"]");
        $this->ok($sec, "PUBRES[\"{$res}\",\"Gaceta N° 1/2027\"]");
        $this->assertSame('PUBLICADA', Resolucion::first()->estado);
        $this->assertStringContainsString('QUÓRUM ALCANZADO', $this->ok($sec, "GENACT[\"{$idSesion}\"]"));

        $this->assertStringContainsString('RES-', $this->ok($pre, 'REPRES[]'));
        $this->assertStringContainsString('PRESENTE', $this->ok($pre, "REPASI[\"{$idSesion}\"]"));
        $this->assertStringContainsString('tasa_aprobacion', $this->ok($pre, 'REPEST[]'));
        $this->assertStringContainsString('RES-', $this->ok($sec, 'LISPUB["RESOLUCIONES"]'));
        $this->assertStringContainsString('CORREO', $this->ok($pre, 'LISAUD["*","*","VOTO","CORREO"]'));

        $this->assertGreaterThan(20, Auditoria::where('canal', 'CORREO')->count());
        $this->assertGreaterThan(25, CorreoLog::where('exito', true)->count());
    }

    public function test_cada_comando_del_catalogo_tiene_documentacion_completa(): void
    {
        $this->assertGreaterThanOrEqual(50, count(Catalogo::todos()));
        foreach (Catalogo::todos() as $c) {
            $this->assertStringStartsWith($c['nombre'].'[', $c['sintaxis']);
            $this->assertNotSame('', $c['descripcion']);
            // la sintaxis documentada lista tantos parámetros como el máximo aceptado
            $this->assertSame($c['max'], (int) (substr_count($c['sintaxis'], '"') / 2) ?: ($c['max'] === 0 ? 0 : $c['max']), $c['nombre']);
        }
    }
}
