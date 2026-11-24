<?php

namespace Tests\Feature;

use App\Models\MetodoPago;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Módulo de pagos electrónicos (pasarela simulada). */
class PagosTest extends TestCase
{
    private function como(string $correo): void
    {
        Sanctum::actingAs($this->usuario($correo), ['*']);
    }

    private function tarjeta(string $numero = '4242 4242 4242 4242', array $extra = []): array
    {
        return array_merge(['tipo' => 'TARJETA', 'alias' => 'Mi tarjeta', 'numero' => $numero, 'titular' => 'Carlos Mendoza', 'vencimiento' => now()->addYears(2)->format('m/y'), 'cvv' => '123'], $extra);
    }

    private function concepto(string $codigo): int
    {
        return $this->getJson('/api/conceptos-pago')->assertOk()->collect()->firstWhere('codigo', $codigo)['id_concepto'];
    }

    public function test_registro_de_tarjeta_guarda_solo_marca_y_ultimos_4(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $r = $this->postJson('/api/metodos-pago', $this->tarjeta())->assertCreated()->assertJsonPath('marca', 'VISA')->assertJsonPath('ultimos4', '4242')->assertJsonPath('predeterminado', true);
        $this->assertStringNotContainsString('4242424242424242', json_encode($r->json()));
        $this->assertDatabaseMissing('metodo_pago', ['ultimos4' => '4242424242424242']);
        $this->assertFalse(\Schema::hasColumn('metodo_pago', 'cvv'));
    }

    public function test_validacion_de_tarjeta_en_espanol(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $this->postJson('/api/metodos-pago', $this->tarjeta('4242 4242 4242 4241'))->assertStatus(422)->assertJsonPath('errors.numero.0', fn ($m) => str_contains($m, 'no es válido'));
        $this->postJson('/api/metodos-pago', $this->tarjeta(extra: ['vencimiento' => '13/30']))->assertStatus(422)->assertJsonValidationErrors(['vencimiento']);
        $this->postJson('/api/metodos-pago', $this->tarjeta(extra: ['vencimiento' => '01/20']))->assertStatus(422)->assertJsonPath('errors.vencimiento.0', 'La tarjeta está vencida.');
        $this->postJson('/api/metodos-pago', $this->tarjeta(extra: ['cvv' => '12']))->assertStatus(422)->assertJsonValidationErrors(['cvv']);
        $this->postJson('/api/metodos-pago', $this->tarjeta(extra: ['titular' => '1234']))->assertStatus(422)->assertJsonValidationErrors(['titular']);
        $this->postJson('/api/metodos-pago', ['tipo' => 'QR'])->assertStatus(422)->assertJsonValidationErrors(['alias']);
        $this->postJson('/api/metodos-pago', ['tipo' => 'BILLETERA', 'alias' => 'Tigo', 'celular' => '12345678'])->assertStatus(422)->assertJsonValidationErrors(['celular']);
    }

    public function test_pago_unico_aprobado_y_rechazado_segun_reglas_de_la_pasarela(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $ok = $this->postJson('/api/metodos-pago', $this->tarjeta())->json('id_metodo');
        $sinFondos = $this->postJson('/api/metodos-pago', $this->tarjeta('4000 0000 0000 9995', ['alias' => 'Sin fondos']))->json('id_metodo');
        $rechazada = $this->postJson('/api/metodos-pago', $this->tarjeta('4000 0000 0000 0002', ['alias' => 'Rechazada']))->json('id_metodo');
        $c = $this->concepto('CERT_RESOLUCION');

        $p = $this->postJson('/api/pagos', ['id_concepto' => $c, 'id_metodo' => $ok, 'cvv' => '123'])->assertCreated()->assertJsonPath('estado', 'APROBADO')->json();
        $this->assertStringStartsWith('SIM-', $p['codigo_autorizacion']);
        $this->assertSame('VISA ****4242', $p['detalle_metodo']);

        $this->postJson('/api/pagos', ['id_concepto' => $c, 'id_metodo' => $sinFondos, 'cvv' => '123'])->assertCreated()->assertJsonPath('estado', 'RECHAZADO')->assertJsonPath('motivo_rechazo', fn ($m) => str_contains($m, 'Fondos'));
        $this->postJson('/api/pagos', ['id_concepto' => $c, 'id_metodo' => $rechazada, 'cvv' => '123'])->assertJsonPath('estado', 'RECHAZADO');
        $this->postJson('/api/pagos', ['id_concepto' => $c, 'id_metodo' => $ok])->assertStatus(422)->assertJsonValidationErrors(['cvv']);

        $this->getJson('/api/pagos')->assertOk()->assertJsonCount(3);
        $this->getJson("/api/pagos/{$p['id_pago']}/comprobante")->assertOk()->assertJsonPath('monto', '25.00')->assertJsonPath('referencia', $p['referencia']);
        $this->assertDatabaseHas('notificacion', ['tipo' => 'PAGO']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'PAGO_APROBADO']);
    }

    public function test_no_se_puede_pagar_con_el_metodo_de_otro_usuario_ni_ver_su_comprobante(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $metodo = $this->postJson('/api/metodos-pago', $this->tarjeta())->json('id_metodo');
        $c = $this->concepto('COPIA_ACTA');
        $pago = $this->postJson('/api/pagos', ['id_concepto' => $c, 'id_metodo' => $metodo, 'cvv' => '123'])->json('id_pago');

        $this->como('titular.estudiante.inf@consejo.test');
        $this->postJson('/api/pagos', ['id_concepto' => $c, 'id_metodo' => $metodo, 'cvv' => '123'])->assertStatus(403);
        $this->getJson("/api/pagos/{$pago}/comprobante")->assertStatus(403);
        $this->deleteJson("/api/metodos-pago/{$metodo}")->assertStatus(403);
        $this->getJson('/api/pagos-admin/resumen')->assertStatus(403);
    }

    public function test_plan_de_pagos_en_cuotas(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $m = $this->postJson('/api/metodos-pago', $this->tarjeta())->json('id_metodo');
        $simple = $this->concepto('CERT_RESOLUCION');
        $capacitacion = $this->concepto('CAPACITACION');

        $this->postJson('/api/planes-pago', ['id_concepto' => $simple, 'num_cuotas' => 2])->assertStatus(422);              // no admite plan
        $this->postJson('/api/planes-pago', ['id_concepto' => $capacitacion, 'num_cuotas' => 9])->assertStatus(422)->assertJsonValidationErrors(['num_cuotas']);
        $plan = $this->postJson('/api/planes-pago', ['id_concepto' => $capacitacion, 'num_cuotas' => 3])->assertCreated()->json();
        $this->assertCount(3, $plan['cuotas']);
        $this->assertEquals(150.00, array_sum(array_column($plan['cuotas'], 'monto')));
        $this->postJson('/api/planes-pago', ['id_concepto' => $capacitacion, 'num_cuotas' => 3])->assertStatus(422);          // ya hay uno activo

        [$c1, $c2, $c3] = array_column($plan['cuotas'], 'id_cuota');
        $this->postJson("/api/cuotas/{$c2}/pagar", ['id_metodo' => $m, 'cvv' => '123'])->assertStatus(422);                   // orden obligatorio
        $this->postJson("/api/cuotas/{$c1}/pagar", ['id_metodo' => $m, 'cvv' => '123'])->assertCreated()->assertJsonPath('tipo', 'CUOTA');
        $this->postJson("/api/cuotas/{$c1}/pagar", ['id_metodo' => $m, 'cvv' => '123'])->assertStatus(422);                   // ya pagada
        $this->postJson("/api/cuotas/{$c2}/pagar", ['id_metodo' => $m, 'cvv' => '123'])->assertCreated();
        $this->postJson("/api/cuotas/{$c3}/pagar", ['id_metodo' => $m, 'cvv' => '123'])->assertCreated();

        $mis = $this->getJson('/api/planes-pago')->assertOk()->json();
        $this->assertSame('COMPLETADO', $mis[0]['estado']);
        $this->assertEquals(150.00, $mis[0]['pagado']);
    }

    public function test_cancelar_plan_y_cuota_rechazada_no_avanza(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $mala = $this->postJson('/api/metodos-pago', $this->tarjeta('4000 0000 0000 0002'))->json('id_metodo');
        $plan = $this->postJson('/api/planes-pago', ['id_concepto' => $this->concepto('DERECHO_TRAMITE'), 'num_cuotas' => 2])->json();
        $this->postJson("/api/cuotas/{$plan['cuotas'][0]['id_cuota']}/pagar", ['id_metodo' => $mala, 'cvv' => '123'])->assertCreated()->assertJsonPath('estado', 'RECHAZADO');
        $this->assertDatabaseHas('cuota', ['id_cuota' => $plan['cuotas'][0]['id_cuota'], 'estado' => 'PENDIENTE']);
        $this->postJson("/api/planes-pago/{$plan['id_plan']}/cancelar")->assertOk()->assertJsonPath('estado', 'CANCELADO');
        $this->assertDatabaseMissing('cuota', ['id_plan' => $plan['id_plan'], 'estado' => 'PENDIENTE']);
    }

    public function test_qr_transferencia_y_billetera_se_aprueban(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $c = $this->concepto('CERT_ASISTENCIA');
        $qr = $this->postJson('/api/metodos-pago', ['tipo' => 'QR', 'alias' => 'QR del banco'])->assertCreated()->json('id_metodo');
        $tr = $this->postJson('/api/metodos-pago', ['tipo' => 'TRANSFERENCIA', 'alias' => 'Cuenta BNB', 'banco' => 'BNB', 'cuenta' => '1000234567'])->assertCreated()->assertJsonPath('ultimos4', '4567')->json('id_metodo');
        $bi = $this->postJson('/api/metodos-pago', ['tipo' => 'BILLETERA', 'alias' => 'Tigo Money', 'celular' => '71234567'])->assertCreated()->json('id_metodo');
        foreach ([$qr, $tr, $bi] as $m) {
            $this->postJson('/api/pagos', ['id_concepto' => $c, 'id_metodo' => $m])->assertCreated()->assertJsonPath('estado', 'APROBADO');
        }
        $this->deleteJson("/api/metodos-pago/{$qr}")->assertOk();
        $this->assertSame(1, MetodoPago::where('predeterminado', true)->count());
    }

    public function test_administracion_de_conceptos_y_resumen(): void
    {
        $this->como('titular.docente.inf@consejo.test');
        $this->postJson('/api/conceptos-pago', [])->assertStatus(403);

        $this->como('secretario@consejo.test');
        $nuevo = $this->postJson('/api/conceptos-pago', ['codigo' => 'LEGALIZ', 'nombre' => 'Legalización de documento', 'monto' => 30, 'permite_plan' => false, 'activo' => true])->assertCreated()->json();
        $this->postJson('/api/conceptos-pago', ['codigo' => 'LEGALIZ', 'nombre' => 'Repetido', 'monto' => 30, 'permite_plan' => false, 'activo' => true])->assertStatus(422)->assertJsonValidationErrors(['codigo']);
        $this->postJson('/api/conceptos-pago', ['codigo' => 'MALO', 'nombre' => 'Monto negativo', 'monto' => -5, 'permite_plan' => false, 'activo' => true])->assertStatus(422)->assertJsonValidationErrors(['monto']);
        $this->putJson("/api/conceptos-pago/{$nuevo['id_concepto']}", ['codigo' => 'LEGALIZ', 'nombre' => 'Legalización', 'monto' => 35, 'permite_plan' => false, 'activo' => true])->assertOk()->assertJsonPath('monto', '35.00');

        $this->como('titular.docente.inf@consejo.test');
        $m = $this->postJson('/api/metodos-pago', ['tipo' => 'QR', 'alias' => 'QR'])->json('id_metodo');
        $this->postJson('/api/pagos', ['id_concepto' => $nuevo['id_concepto'], 'id_metodo' => $m])->assertCreated();

        $this->como('secretario@consejo.test');
        $this->deleteJson("/api/conceptos-pago/{$nuevo['id_concepto']}")->assertOk()->assertJsonPath('message', fn ($t) => str_contains($t, 'desactivó'));
        $this->getJson('/api/pagos-admin/transacciones')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/pagos-admin/resumen')->assertOk()->assertJsonPath('aprobados', 1)->assertJsonPath('recaudado', 35);
    }
}
