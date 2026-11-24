<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de pagos electrónicos (pasarela SIMULADA): aranceles y tasas de servicios del Consejo Facultativo.
 * Nunca se guarda el número completo de la tarjeta ni el CVV: solo marca y últimos 4 dígitos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_pago', function (Blueprint $t) {
            $t->id('id_concepto');
            $t->string('codigo', 30)->unique();
            $t->string('nombre', 120);
            $t->text('descripcion')->nullable();
            $t->decimal('monto', 10, 2);
            $t->boolean('permite_plan')->default(false);
            $t->unsignedSmallInteger('max_cuotas')->default(1);
            $t->boolean('activo')->default(true);
        });

        Schema::create('metodo_pago', function (Blueprint $t) {
            $t->id('id_metodo');
            $t->foreignId('id_usuario')->constrained('usuario', 'id_usuario')->cascadeOnDelete();
            $t->string('tipo', 20);                           // TARJETA | QR | TRANSFERENCIA | BILLETERA
            $t->string('alias', 60);
            $t->string('titular', 120)->nullable();
            $t->string('marca', 30)->nullable();              // VISA, MASTERCARD, AMEX, banco o billetera
            $t->string('ultimos4', 4)->nullable();
            $t->string('vencimiento', 5)->nullable();         // MM/AA
            $t->boolean('predeterminado')->default(false);
            $t->timestamps();
            $t->index('id_usuario');
        });

        Schema::create('plan_pago', function (Blueprint $t) {
            $t->id('id_plan');
            $t->foreignId('id_usuario')->constrained('usuario', 'id_usuario')->cascadeOnDelete();
            $t->foreignId('id_concepto')->constrained('concepto_pago', 'id_concepto');
            $t->decimal('monto_total', 10, 2);
            $t->unsignedSmallInteger('num_cuotas');
            $t->string('estado', 15)->default('ACTIVO');     // ACTIVO | COMPLETADO | CANCELADO
            $t->timestamps();
        });

        Schema::create('cuota', function (Blueprint $t) {
            $t->id('id_cuota');
            $t->foreignId('id_plan')->constrained('plan_pago', 'id_plan')->cascadeOnDelete();
            $t->unsignedSmallInteger('numero');
            $t->decimal('monto', 10, 2);
            $t->date('fecha_vencimiento');
            $t->string('estado', 15)->default('PENDIENTE'); // PENDIENTE | PAGADA | CANCELADA
            $t->unsignedBigInteger('id_pago')->nullable();
            $t->timestamp('fecha_pago')->nullable();
            $t->unique(['id_plan', 'numero']);
        });

        Schema::create('pago', function (Blueprint $t) {
            $t->id('id_pago');
            $t->foreignId('id_usuario')->constrained('usuario', 'id_usuario')->cascadeOnDelete();
            $t->foreignId('id_concepto')->nullable()->constrained('concepto_pago', 'id_concepto')->nullOnDelete();
            $t->foreignId('id_metodo')->nullable()->constrained('metodo_pago', 'id_metodo')->nullOnDelete();
            $t->unsignedBigInteger('id_plan')->nullable();
            $t->unsignedBigInteger('id_cuota')->nullable();
            $t->string('tipo', 10)->default('UNICO');         // UNICO | CUOTA
            $t->decimal('monto', 10, 2);
            $t->string('moneda', 3)->default('BOB');
            $t->string('estado', 12);                         // APROBADO | RECHAZADO
            $t->string('referencia', 40)->unique();
            $t->string('codigo_autorizacion', 30)->nullable();
            $t->string('motivo_rechazo', 160)->nullable();
            $t->string('detalle_metodo', 120)->nullable();    // ej. "VISA ****4242" al momento del cobro
            $t->string('observacion', 200)->nullable();       // ej. N° de resolución / acta relacionada
            $t->timestamps();
            $t->index(['id_usuario', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
        Schema::dropIfExists('cuota');
        Schema::dropIfExists('plan_pago');
        Schema::dropIfExists('metodo_pago');
        Schema::dropIfExists('concepto_pago');
    }
};
