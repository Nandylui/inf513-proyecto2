<?php

namespace Tests\Feature;

use App\Models\BitacoraAcceso;
use App\Support\Permisos;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Requisitos del Proyecto No. 2: RBAC en BD, menú dinámico, bitácora, visitas, búsqueda, validación en español y preferencias. */
class SistemaWebTest extends TestCase
{
    private function como(string $correo): void
    {
        Sanctum::actingAs($this->usuario($correo), ['*']);
    }

    private function titulos(array $menu): array
    {
        $t = [];
        foreach ($menu as $m) {
            $t[] = $m['titulo'];
            foreach ($m['hijos'] as $h) {
                $t[] = $h['titulo'];
            }
        }

        return $t;
    }

    public function test_menu_dinamico_depende_del_rol(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $docente = $this->titulos($this->getJson('/api/menu')->assertOk()->json());
        $this->assertContains('Sesiones y excusas', $docente);
        $this->assertContains('Mis pagos', $docente);
        $this->assertNotContains('Usuarios y roles', $docente);
        $this->assertNotContains('Matriz de acceso', $docente);
        $this->assertNotContains('Bitácora de accesos', $docente);

        $this->como(config('consejo.admin.correo'));
        $admin = $this->titulos($this->getJson('/api/menu')->assertOk()->json());
        foreach (['Usuarios y roles', 'Matriz de acceso', 'Menú dinámico', 'Bitácora de accesos', 'Administración de pagos'] as $t) {
            $this->assertContains($t, $admin);
        }
        $this->assertNotContains('Seguridad', $docente);
    }

    public function test_matriz_de_acceso_editable_desde_la_bd_cambia_el_menu_y_la_api(): void
    {
        $this->como('secretario@consejo.test');
        $this->getJson('/api/rbac/matriz')->assertStatus(403);

        $this->como(config('consejo.admin.correo'));
        $this->getJson('/api/rbac/matriz')->assertOk()->assertJsonStructure(['roles', 'permisos' => [['codigo', 'roles']]]);

        // se le quita a TITULAR_DOCENTE el permiso de ver sesiones
        $this->putJson('/api/rbac/matriz', ['rol' => 'TITULAR_DOCENTE', 'permiso' => 'sesion.ver', 'concedido' => false])->assertOk();
        $this->como('titular.docente.inf@consejo.test');
        $this->getJson('/api/sesiones')->assertStatus(403);
        $this->assertNotContains('Sesiones y excusas', $this->titulos($this->getJson('/api/menu')->json()));

        // y se vuelve a conceder
        $this->como(config('consejo.admin.correo'));
        $this->putJson('/api/rbac/matriz', ['rol' => 'TITULAR_DOCENTE', 'permiso' => 'sesion.ver', 'concedido' => true])->assertOk();
        $this->como('titular.docente.inf@consejo.test');
        $this->getJson('/api/sesiones')->assertOk();
    }

    public function test_el_administrador_no_puede_perder_permisos_criticos(): void
    {
        $this->como(config('consejo.admin.correo'));
        $this->putJson('/api/rbac/matriz', ['rol' => 'ADMINISTRADOR', 'permiso' => 'rbac.gestionar', 'concedido' => false])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'ADMINISTRADOR'));
        $this->assertContains('rbac.gestionar', Permisos::delUsuario($this->admin()));
    }

    public function test_crud_del_menu_dinamico(): void
    {
        $this->como(config('consejo.admin.correo'));
        $id = $this->postJson('/api/rbac/menus', ['titulo' => 'Ayuda', 'ruta' => '/ayuda', 'icono' => 'ayuda', 'orden' => 99, 'activo' => true, 'permiso' => 'buscar'])
            ->assertCreated()->json('id_menu');
        $this->assertContains('Ayuda', $this->titulos($this->getJson('/api/menu')->json()));
        $this->putJson("/api/rbac/menus/{$id}", ['titulo' => 'Ayuda', 'ruta' => '/ayuda', 'orden' => 99, 'activo' => false])->assertOk();
        $this->assertNotContains('Ayuda', $this->titulos($this->getJson('/api/menu')->json()));
        $this->postJson('/api/rbac/menus', ['titulo' => 'X', 'ruta' => 'sin-barra', 'orden' => 1, 'activo' => true])->assertStatus(422)->assertJsonValidationErrors(['ruta']);
        $this->deleteJson("/api/rbac/menus/{$id}")->assertOk();
    }

    public function test_bitacora_registra_logins_exitosos_fallidos_ip_y_recursos(): void
    {
        $this->postJson('/api/auth/login', ['correo' => 'presidente@consejo.test', 'password' => 'incorrecta'], ['REMOTE_ADDR' => '10.1.2.3'])->assertStatus(401);
        $this->postJson('/api/auth/login', ['correo' => 'nadie@consejo.test', 'password' => 'x'])->assertStatus(401);
        $r = $this->postJson('/api/auth/login', ['correo' => 'presidente@consejo.test', 'password' => 'Consejo2026*'])->assertOk();

        $this->assertSame(2, BitacoraAcceso::where('evento', 'LOGIN_FALLIDO')->count());
        $this->assertSame(1, BitacoraAcceso::where('evento', 'LOGIN_OK')->count());
        $this->assertNotNull(BitacoraAcceso::where('evento', 'LOGIN_OK')->first()->ip);
        $this->assertSame('Usuario inexistente', BitacoraAcceso::where('correo', 'nadie@consejo.test')->value('detalle'));

        $this->withHeader('Authorization', 'Bearer '.$r['token'])->getJson('/api/sesiones')->assertOk();
        $this->assertTrue(BitacoraAcceso::where('evento', 'ACCESO')->where('recurso', 'GET api/sesiones')->exists());

        $this->como('titular.docente.inf@consejo.test');
        $this->getJson('/api/usuarios')->assertStatus(403);
        $this->assertTrue(BitacoraAcceso::where('evento', 'DENEGADO')->where('recurso', 'GET api/usuarios')->exists());
    }

    public function test_bitacora_registra_denegados_en_rutas_omitidas_pero_no_el_ruido_de_la_interfaz(): void
    {
        // El 403 sobre el panel de la bitácora SÍ queda registrado (acceso denegado a un recurso protegido).
        $this->como('titular.estudiante.inf@consejo.test');
        $this->getJson('/api/bitacora')->assertStatus(403);
        $this->assertTrue(BitacoraAcceso::where('evento', 'DENEGADO')->where('recurso', 'GET api/bitacora')->where('estado_http', 403)->exists());

        // La consulta automática de la campana, cuando sale bien, no llena el registro.
        $this->getJson('/api/notificaciones')->assertOk();
        $this->assertFalse(BitacoraAcceso::where('recurso', 'GET api/notificaciones')->exists());
    }

    public function test_triggers_de_postgresql_protegen_la_bitacora_y_completan_el_plan(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Los triggers solo existen en PostgreSQL (la prueba se ejecuta con DB_CONNECTION=pgsql).');
        }
        // 1. La bitácora es de solo escritura.
        $this->postJson('/api/auth/login', ['correo' => 'nadie@consejo.test', 'password' => 'mala']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->expectExceptionMessage('solo escritura');
        DB::table('bitacora_acceso')->update(['detalle' => 'alterado']);
    }

    public function test_panel_de_bitacora_solo_para_roles_autorizados(): void
    {
        $this->como('titular.estudiante.inf@consejo.test');
        $this->getJson('/api/bitacora')->assertStatus(403);

        $this->postJson('/api/auth/login', ['correo' => 'presidente@consejo.test', 'password' => 'mala']);
        $this->como(config('consejo.admin.correo'));
        $this->getJson('/api/sesiones')->assertOk();
        $this->getJson('/api/bitacora?evento=LOGIN_FALLIDO')->assertOk()->assertJsonPath('total', 1);
        $r = $this->getJson('/api/bitacora/resumen')->assertOk();
        $r->assertJsonStructure(['ingresos_hoy', 'fallidos_hoy', 'serie_diaria', 'recursos_mas_accedidos', 'ips_mas_activas']);
        $this->assertSame(1, $r['fallidos_hoy']);
    }

    public function test_contador_de_visitas_es_persistente_y_por_pagina(): void
    {
        $this->postJson('/api/visitas', ['pagina' => '/login'])->assertOk()->assertJsonPath('visitas', 1);
        $this->postJson('/api/visitas', ['pagina' => '/login'])->assertJsonPath('visitas', 2);
        $r = $this->postJson('/api/visitas', ['pagina' => '/panel'])->assertJsonPath('visitas', 1);
        $this->assertSame(3, $r['total']);
        $this->postJson('/api/visitas', ['pagina' => '/login/'])->assertJsonPath('visitas', 3);   // normaliza la barra final
        $this->postJson('/api/visitas', ['pagina' => '<script>'])->assertStatus(422);
        $this->assertDatabaseHas('visita_pagina', ['pagina' => '/login', 'contador' => 3]);
    }

    public function test_busqueda_global_respeta_permisos(): void
    {
        $this->como('secretario@consejo.test');
        $r = $this->getJson('/api/buscar?q=Docente')->assertOk()->json();
        $this->assertGreaterThan(0, $r['total']);
        $this->assertContains('Usuarios', array_column($r['grupos'], 'grupo'));

        $this->como('titular.docente.inf@consejo.test');
        $r = $this->getJson('/api/buscar?q=Docente')->assertOk()->json();
        $this->assertNotContains('Usuarios', array_column($r['grupos'], 'grupo'));
        $this->getJson('/api/buscar?q=a')->assertStatus(422);
        $this->getJson('/api/buscar?q=certificaci')->assertOk()->assertJsonPath('grupos.0.grupo', 'Conceptos de pago');
    }

    public function test_validacion_del_servidor_en_espanol(): void
    {
        $r = $this->postJson('/api/auth/login', ['correo' => 'no-es-correo', 'password' => ''])->assertStatus(422)->json();
        $this->assertStringContainsString('correo electrónico válido', $r['errors']['correo'][0]);
        $this->assertStringContainsString('obligatorio', $r['errors']['password'][0]);

        $this->como('secretario@consejo.test');
        $r = $this->postJson('/api/usuarios', ['ci' => '1'])->assertStatus(422)->json();
        $this->assertStringContainsString('obligatorio', $r['errors']['nombres'][0]);

        $this->getJson('/api/ruta-que-no-existe')->assertStatus(404)->assertJsonPath('message', 'El recurso solicitado no existe.');
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '')->getJson('/api/sesiones')->assertStatus(401)->assertJsonPath('message', fn ($m) => str_contains($m, 'No autenticado'));
    }

    public function test_preferencias_de_tema_accesibilidad_se_guardan_por_usuario(): void
    {
        $this->como('titular.estudiante.inf@consejo.test');
        $this->getJson('/api/auth/me')->assertJsonPath('preferencias', null);
        $this->putJson('/api/auth/preferencias', ['tema' => 'jovenes', 'modo' => 'noche', 'fuente' => 125, 'contraste' => true])->assertOk();
        $this->getJson('/api/auth/me')->assertJsonPath('preferencias.tema', 'jovenes')->assertJsonPath('preferencias.fuente', 125)->assertJsonPath('preferencias.contraste', true);
        $this->putJson('/api/auth/preferencias', ['tema' => 'otro', 'modo' => 'noche', 'fuente' => 300, 'contraste' => 'x'])->assertStatus(422)
            ->assertJsonValidationErrors(['tema', 'fuente', 'contraste']);
    }

    public function test_dashboard_incluye_accesos_solo_si_el_rol_puede_verlos(): void
    {
        $this->como('titular.estudiante.inf@consejo.test');
        $r = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertNull($r['accesos']);
        $this->assertArrayHasKey('sesiones_por_mes', $r);
        $this->assertTrue($r['pagos']['propios']);

        $this->como(config('consejo.admin.correo'));
        $r = $this->getJson('/api/dashboard')->assertOk()->json();
        $this->assertNotNull($r['accesos']);
        $this->assertArrayHasKey('paginas_mas_visitadas', $r['accesos']);
        $this->assertArrayHasKey('recaudado', $r['pagos']);
        $this->assertCount(9, $r['sesiones_por_mes']);
    }
}
