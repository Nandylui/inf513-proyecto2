<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CU4 - Repositorio documental, anticipación y flujo de aprobación (RF-09 a RF-13, RF-16)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_tramite', function (Blueprint $t) {
            $t->id('id_tipo_tramite');
            $t->string('nombre', 80)->unique();
            $t->string('articulo', 60)->nullable();               // base estatutaria
            $t->string('mayoria', 20)->default('ABSOLUTA');       // ABSOLUTA | DOS_TERCIOS (RF-16)
            $t->text('descripcion')->nullable();
            $t->boolean('activo')->default(true);
        });

        Schema::create('flujo_paso', function (Blueprint $t) {
            $t->id('id_flujo_paso');
            $t->foreignId('id_tipo_tramite')->constrained('tipo_tramite', 'id_tipo_tramite')->cascadeOnDelete();
            $t->unsignedSmallInteger('orden');
            $t->foreignId('id_rol')->constrained('rol', 'id_rol');
            $t->unique(['id_tipo_tramite', 'orden']);
        });

        Schema::create('documento', function (Blueprint $t) {
            $t->id('id_documento');
            $t->string('titulo', 255);
            $t->text('descripcion')->nullable();
            $t->foreignId('id_remitente')->constrained('usuario', 'id_usuario');
            $t->foreignId('id_sesion')->nullable()->constrained('sesion', 'id_sesion');
            $t->foreignId('id_punto')->nullable()->constrained('punto_orden_dia', 'id_punto');
            $t->foreignId('id_tipo_tramite')->nullable()->constrained('tipo_tramite', 'id_tipo_tramite');
            // RECIBIDO | EN_REVISION | OBSERVADO | APROBADO | RECHAZADO | VOTADO
            $t->string('estado', 20)->default('RECIBIDO');
            $t->unsignedSmallInteger('prioridad')->default(3);    // 1 = alta ... 5 = baja (RF-11)
            $t->unsignedSmallInteger('version_actual')->default(1);
            $t->unsignedSmallInteger('paso_actual')->default(1);  // paso del workflow (RF-12)
            $t->timestamp('fecha_recepcion')->useCurrent();
            $t->index(['id_sesion', 'estado']);
        });

        Schema::create('documento_version', function (Blueprint $t) {
            $t->id('id_version');
            $t->foreignId('id_documento')->constrained('documento', 'id_documento')->cascadeOnDelete();
            $t->unsignedSmallInteger('numero');
            $t->string('archivo_nombre')->nullable();
            $t->string('archivo_path')->nullable();
            $t->string('archivo_hash', 64)->nullable();
            $t->foreignId('id_subido_por')->constrained('usuario', 'id_usuario');
            $t->text('comentario')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['id_documento', 'numero']);
        });

        Schema::create('validacion', function (Blueprint $t) {
            $t->id('id_validacion');
            $t->foreignId('id_documento')->constrained('documento', 'id_documento')->cascadeOnDelete();
            $t->foreignId('id_usuario')->constrained('usuario', 'id_usuario');
            $t->foreignId('id_rol')->constrained('rol', 'id_rol');
            $t->unsignedSmallInteger('paso');
            $t->string('decision', 20);                           // APROBADO | OBSERVADO | RECHAZADO
            $t->text('observaciones')->nullable();
            $t->timestamp('fecha')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validacion');
        Schema::dropIfExists('documento_version');
        Schema::dropIfExists('documento');
        Schema::dropIfExists('flujo_paso');
        Schema::dropIfExists('tipo_tramite');
    }
};
