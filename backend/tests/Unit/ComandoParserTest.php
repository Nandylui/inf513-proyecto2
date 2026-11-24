<?php

namespace Tests\Unit;

use App\Correo\ComandoParser;
use App\Correo\MensajeEntrante;
use App\Exceptions\ReglaNegocioException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ComandoParserTest extends TestCase
{
    public static function validos(): array
    {
        return [
            'básico' => ['LISUSU["*"]', 'LISUSU', ['*']],
            'sin parámetros' => ['LISROL[]', 'LISROL', []],
            'sin corchetes' => ['AYUDA', 'AYUDA', []],
            'minúsculas y espacios' => ['  insusu [ "1","Ana" , "Ríos" ]  ', 'INSUSU', ['1', 'Ana', 'Ríos']],
            'prefijo Re:' => ['Re: RE: VERSES["4"]', 'VERSES', ['4']],
            'prefijo Fwd y etiqueta' => ['Fwd: [EXTERNO] LISSES["PROGRAMADA"]', 'LISSES', ['PROGRAMADA']],
            'comillas tipográficas' => ['INSSES[“2027-03-15 10:00”,“Sala”,“ORDINARIA”]', 'INSSES', ['2027-03-15 10:00', 'Sala', 'ORDINARIA']],
            'comillas simples' => ["VERDOC['7']", 'VERDOC', ['7']],
            'sin comillas' => ['VERQUO[3]', 'VERQUO', ['3']],
            'coma dentro del valor' => ['INSDOC["Informe, anexos y planos","2"]', 'INSDOC', ['Informe, anexos y planos', '2']],
            'corchete dentro del valor' => ['VETRES["1","Art. [64] inc. b"]', 'VETRES', ['1', 'Art. [64] inc. b']],
            'comilla escapada' => ['VETRES["1","dijo \"no\" al pto"]', 'VETRES', ['1', 'dijo "no" al pto']],
            'parámetro vacío' => ['LISDOC["1","","POA"]', 'LISDOC', ['1', '', 'POA']],
            'unicode invisible' => ["\u{200B}LISUSU[\"x\"]", 'LISUSU', ['x']],
        ];
    }

    #[DataProvider('validos')]
    public function test_analiza_asuntos_validos(string $asunto, string $comando, array $params): void
    {
        $r = ComandoParser::analizar($asunto);
        $this->assertSame($comando, $r['comando']);
        $this->assertSame($params, $r['parametros']);
    }

    public static function invalidos(): array
    {
        return [['   '], ['123["x"]'], ['LISUSU["x"'], ['LISUSU["x]'], ['LISUSU["a" "b"]'], ['hola como estas'], ['LI["x"]']];
    }

    #[DataProvider('invalidos')]
    public function test_asuntos_invalidos_dan_error_claro_y_no_excepcion_tecnica(string $asunto): void
    {
        $this->expectException(ReglaNegocioException::class);
        ComandoParser::analizar($asunto);
    }

    public function test_decodifica_cabeceras_rfc2047_y_extrae_remitente(): void
    {
        $crudo = "From: =?UTF-8?B?Sm9zw6kgUMOpcmV6?= <Jose.Perez@Consejo.TEST>\r\nSubject: =?UTF-8?Q?LISSES=5B=22PROGRAMADA=22=5D?=\r\n\tcontinuacion\r\nMessage-ID: <abc@x>\r\n\r\ncuerpo";
        $m = MensajeEntrante::desdeCrudo($crudo);
        $this->assertSame('jose.perez@consejo.test', $m->remitente);
        $this->assertSame('José Pérez', $m->nombreRemitente);
        $this->assertStringStartsWith('LISSES["PROGRAMADA"]', $m->asunto);
    }

    public function test_detecta_correos_automaticos(): void
    {
        $this->assertTrue(MensajeEntrante::desdeCrudo("From: MAILER-DAEMON@x.com\r\nSubject: rebote\r\n\r\n")->esAutomatico());
        $this->assertTrue(MensajeEntrante::desdeCrudo("From: a@b.com\r\nAuto-Submitted: auto-replied\r\nSubject: x\r\n\r\n")->esAutomatico());
        $this->assertFalse(MensajeEntrante::desdeCrudo("From: a@b.com\r\nSubject: x\r\n\r\n")->esAutomatico());
    }
}
