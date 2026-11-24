<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CU7 - Fiscalizar, auditar y publicar (RF-19, RF-21, RF-22)
 * Soporte del Sistema Vía Mail: bitácora de correos procesados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacion', function (Blueprint $t) {
            $t->id('id_notificacion');
            $t->foreignId('id_usuario')->constrained('usuario', 'id_usuario')->cascadeOnDelete();
            $t->string('tipo', 30);                               // CONVOCATORIA, PLAZO_DOCUMENTOS, RESOLUCION, VETO...
            $t->string('asunto', 200);
            $t->text('mensaje');
            $t->boolean('leida')->default(false);
            $t->boolean('enviada_correo')->default(false);
            $t->timestamp('fecha')->useCurrent();
            $t->index(['id_usuario', 'leida']);
        });

        Schema::create('auditoria', function (Blueprint $t) {
            $t->id('id_auditoria');
            $t->foreignId('id_usuario')->nullable()->constrained('usuario', 'id_usuario')->nullOnDelete();
            $t->string('accion', 60);
            $t->string('entidad', 60)->nullable();
            $t->unsignedBigInteger('id_entidad')->nullable();
            $t->json('detalle')->nullable();
            $t->string('canal', 15)->default('WEB');              // WEB | CORREO
            $t->string('ip', 45)->nullable();
            $t->timestamp('fecha')->useCurrent();
            $t->index(['entidad', 'id_entidad']);
        });

        Schema::create('correo_log', function (Blueprint $t) {
            $t->id('id_correo_log');
            $t->string('remitente', 190);
            $t->string('asunto', 500)->nullable();
            $t->string('comando', 20)->nullable();
            $t->boolean('exito')->default(false);
            $t->text('respuesta')->nullable();
            $t->timestamp('fecha')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correo_log');
        Schema::dropIfExists('auditoria');
        Schema::dropIfExists('notificacion');
    }
};
