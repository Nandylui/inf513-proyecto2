<?php

namespace Tests\Feature;

use App\Models\Documento;
use App\Models\Sesion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    private function como(string $correo): void
    {
        Sanctum::actingAs($this->usuario($correo), ['*']);
    }

    public function test_login_me_y_logout(): void
    {
        $this->postJson('/api/auth/login', ['correo' => 'presidente@consejo.test', 'password' => 'mala'])->assertStatus(401);
        $r = $this->postJson('/api/auth/login', ['correo' => 'PRESIDENTE@consejo.test', 'password' => 'Consejo2026*'])->assertOk()->assertJsonStructure(['token', 'usuario' => ['permisos']]);
        $this->withHeader('Authorization', 'Bearer '.$r['token'])->getJson('/api/auth/me')->assertOk()->assertJsonPath('rol', 'PRESIDENTE');
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson('/api/sesiones')->assertStatus(401);
    }

    public function test_permisos_por_rol_en_la_api(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $this->getJson('/api/usuarios')->assertStatus(403)->assertJsonPath('message', fn ($m) => str_contains($m, 'permiso'));
        $this->getJson('/api/sesiones')->assertOk();
        $this->postJson('/api/sesiones', [])->assertStatus(403);
    }

    public function test_validacion_devuelve_422_legible(): void
    {
        $this->como('secretario@consejo.test');
        $this->postJson('/api/usuarios', ['ci' => '1'])->assertStatus(422)->assertJsonValidationErrors(['nombres', 'correo']);
    }

    public function test_flujo_api_con_archivos(): void
    {
        Storage::fake();
        $fecha = now()->addDays(15)->format('Y-m-d').' 09:00';

        $this->como('presidente@consejo.test');
        $id = $this->postJson('/api/sesiones', ['fecha_hora' => $fecha, 'lugar' => 'Sala', 'tipo' => 'ORDINARIA', 'orden_del_dia' => ['Punto A', 'Punto B']])->assertCreated()->json('id_sesion');
        $this->getJson("/api/sesiones/{$id}/quorum")->assertOk()->assertJsonPath('requerido', 4);
        $this->getJson('/api/panel?sesion='.$id)->assertOk()->assertJsonCount(7);

        // excusa multipart del propio titular -> habilita al suplente
        $this->como('titular.docente.inf@consejo.test');
        $this->post("/api/sesiones/{$id}/excusas", ['motivo' => 'Viaje académico', 'firmado' => true, 'archivo' => UploadedFile::fake()->create('excusa.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('habilitacion.habilitado.ci', '3010002');
        $this->postJson("/api/sesiones/{$id}/excusas", ['motivo' => 'otra'])->assertStatus(422);   // ya presentó una

        // documento con archivo adjunto
        $this->como('secretario@consejo.test');
        $doc = $this->post('/api/documentos', ['titulo' => 'POA 2027', 'id_sesion' => $id, 'tipo' => 'POA', 'archivo' => UploadedFile::fake()->create('poa.pdf', 200, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->json('id_documento');
        $this->get("/api/documentos/{$doc}/descargar")->assertOk();
        $this->post('/api/documentos', ['titulo' => 'x', 'id_sesion' => $id, 'archivo' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->postJson("/api/documentos/{$doc}/validar", ['decision' => 'APROBADO'])->assertOk();
        $this->como('presidente@consejo.test');
        $this->postJson("/api/documentos/{$doc}/validar", ['decision' => 'APROBADO'])->assertOk()->assertJsonPath('estado', 'APROBADO');
        $this->getJson("/api/documentos/{$doc}/trazabilidad")->assertOk()->assertJsonCount(2, 'pasos');

        // asistencia y votación
        foreach (['2000001', '3010002', '3020001', '3030001'] as $ci) {
            $this->postJson("/api/sesiones/{$id}/asistencias", ['ci' => $ci])->assertCreated();
        }
        $this->assertSame('EN_CURSO', Sesion::find($id)->estado);
        $this->postJson("/api/sesiones/{$id}/documentos/{$doc}/votos", ['voto' => 'A FAVOR'])->assertCreated();
        $this->postJson("/api/sesiones/{$id}/documentos/{$doc}/votos", ['voto' => 'A FAVOR'])->assertStatus(422);   // ya votó
        $this->como('titular.docente.sis@consejo.test');
        $this->postJson("/api/sesiones/{$id}/documentos/{$doc}/votos", ['voto' => 'A FAVOR'])->assertCreated();
        $this->postJson("/api/sesiones/{$id}/documentos/{$doc}/votos", ['voto' => 'A FAVOR', 'ci' => '3030001'])->assertStatus(403);   // no puede votar por otro
        $this->como('titular.docente.red@consejo.test');
        $this->postJson("/api/sesiones/{$id}/documentos/{$doc}/votos", ['voto' => 'A FAVOR'])->assertCreated();

        $this->como('presidente@consejo.test');
        $res = $this->postJson("/api/sesiones/{$id}/documentos/{$doc}/cerrar")->assertSuccessful()->assertJsonPath('resultado', 'APROBADA')->json('id_resolucion');
        $this->postJson("/api/resoluciones/{$res}/promulgar")->assertOk();
        $this->postJson("/api/resoluciones/{$res}/publicar", ['gaceta' => 'Gaceta 1/2027'])->assertOk()->assertJsonPath('estado', 'PUBLICADA');
        $this->postJson("/api/sesiones/{$id}/acta")->assertCreated();

        // público, sin token
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/publico/resoluciones')->assertOk()->assertJsonCount(1);
        $this->getJson("/api/publico/actas/{$id}")->assertOk()->assertJsonStructure(['contenido', 'hash']);
        $this->getJson('/api/publico/sesiones')->assertOk();
    }

    public function test_reportes_y_csv(): void
    {
        $this->como('secretario@consejo.test');
        $this->getJson('/api/reportes/estadisticas')->assertOk()->assertJsonStructure(['titulo', 'resumen', 'columnas', 'filas']);
        $this->getJson('/api/reportes/asistencia?sesion=CONSEJEROS')->assertOk();
        $r = $this->get('/api/reportes/estadisticas/csv')->assertOk();
        $this->assertStringContainsString('Indicador;Valor', $r->streamedContent());
    }

    public function test_consola_de_correo_en_la_api(): void
    {
        $this->como('secretario@consejo.test');
        $this->getJson('/api/correo/comandos')->assertOk()->assertJsonMissing(['handler'])->assertJsonPath('0.nombre', fn ($n) => is_string($n));
        $this->postJson('/api/correo/simular', ['asunto' => 'LISUSU["PRESIDENTE"]'])->assertOk()->assertJsonPath('exito', true);
        $this->getJson('/api/correo/log')->assertStatus(403);
        $this->como('presidente@consejo.test');
        $this->getJson('/api/correo/log')->assertOk()->assertJsonCount(1);
    }
}
