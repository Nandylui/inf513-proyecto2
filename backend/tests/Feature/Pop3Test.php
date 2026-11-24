<?php

namespace Tests\Feature;

use App\Correo\BuzonPop3;
use App\Correo\Pop3Client;
use App\Models\CorreoLog;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Prueba el cliente POP3 real contra un servidor POP3 falso levantado en un puerto local. */
class Pop3Test extends TestCase
{
    private $proc;

    private string $archivo;

    private function levantar(array $mensajes, int $puerto): void
    {
        $this->archivo = tempnam(sys_get_temp_dir(), 'pop3');
        file_put_contents($this->archivo, json_encode($mensajes));
        $this->proc = proc_open([PHP_BINARY, __DIR__.'/../Support/pop3_falso.php', (string) $puerto, $this->archivo], [], $pipes);
        usleep(400000);
    }

    protected function tearDown(): void
    {
        if ($this->proc) {
            proc_terminate($this->proc);
            proc_close($this->proc);
        }
        parent::tearDown();
    }

    private function correo(string $de, string $asunto, string $cuerpo = 'texto'): string
    {
        return "From: Consejero <{$de}>\r\nTo: grupo07sa@tecnoweb.org.bo\r\nSubject: {$asunto}\r\nMessage-ID: <".uniqid().">\r\n\r\n{$cuerpo}\r\n.punto inicial";
    }

    public function test_cliente_pop3_lista_recupera_y_elimina(): void
    {
        $this->levantar([$this->correo('a@b.com', 'LISSES[]'), $this->correo('c@d.com', 'AYUDA[]')], 18110);
        $pop = new Pop3Client('127.0.0.1', 18110, 5);
        $pop->conectar('grupo07sa', 'clave-ok');
        $this->assertSame(2, $pop->estado()['cantidad']);
        $this->assertSame([1, 2], array_keys($pop->listar()));
        $this->assertStringContainsString('.punto inicial', $pop->recuperar(1));   // des-stuffing correcto
        $pop->eliminar(1);
        $pop->cerrar();
        $this->assertSame([1], json_decode(file_get_contents($this->archivo.'.borrados'), true));
    }

    public function test_clave_incorrecta_lanza_error_controlado(): void
    {
        $this->levantar([], 18111);
        $this->expectExceptionMessage('Error POP3');
        (new Pop3Client('127.0.0.1', 18111, 5))->conectar('grupo07sa', 'mala');
    }

    public function test_servidor_caido_lanza_error_controlado(): void
    {
        $this->expectExceptionMessage('No se pudo conectar');
        (new Pop3Client('127.0.0.1', 18199, 2))->conectar('x', 'y');
    }

    public function test_buzon_procesa_responde_por_smtp_y_borra_los_mensajes(): void
    {
        Mail::fake();
        config(['consejo.correo.pop3_host' => '127.0.0.1', 'consejo.correo.pop3_puerto' => 18112, 'consejo.correo.pop3_clave' => 'clave-ok']);
        $this->levantar([
            $this->correo('secretario@consejo.test', 'LISUSU["PRESIDENTE"]'),
            $this->correo('desconocido@x.com', 'LISUSU["*"]'),
            $this->correo('mailer-daemon@x.com', 'Undelivered'),
        ], 18112);

        $n = app(BuzonPop3::class)->revisar();

        $this->assertSame(3, $n);
        $this->assertSame([1, 2, 3], json_decode(file_get_contents($this->archivo.'.borrados'), true));
        $this->assertSame(2, CorreoLog::count());                         // el rebote automático se ignora
        $this->assertTrue(CorreoLog::where('remitente', 'secretario@consejo.test')->value('exito'));
        Mail::assertNothingQueued();
    }
}
