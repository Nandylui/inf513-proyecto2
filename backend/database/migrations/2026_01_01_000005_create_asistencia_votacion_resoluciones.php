<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CU5 - Asistencia biométrica, quórum y votaciones (RF-06, RF-07, RF-15)
 * CU6 - Ciclo de vida de resoluciones, veto y actas (RF-14, RF-17, RF-18)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencia', function (Blueprint $t) {
            $t->id('id_asistencia');
            $t->foreignId('id_sesion')->constrained('sesion', 'id_sesion')->cascadeOnDelete();
            $t->foreignId('id_usuario')->constrained('usuario', 'id_usuario');
            $t->foreignId('id_mandato')->nullable()->constrained('mandato', 'id_mandato');
            $t->string('metodo', 10)->default('HUELLA');          // HUELLA | FACIAL | MANUAL
            $t->string('dispositivo', 30)->default('C5');         // reloj biométrico
            $t->boolean('es_suplente')->default(false);
            $t->timestamp('hora_ingreso')->useCurrent();
            $t->unique(['id_sesion', 'id_usuario']);
        });

        Schema::create('votacion', function (Blueprint $t) {
            $t->id('id_votacion');
            $t->foreignId('id_sesion')->constrained('sesion', 'id_sesion')->cascadeOnDelete();
            $t->foreignId('id_documento')->constrained('documento', 'id_documento')->cascadeOnDelete();
            $t->foreignId('id_usuario')->constrained('usuario', 'id_usuario');
            $t->string('voto', 15);                               // A FAVOR | EN CONTRA | BLANCO
            $t->timestamp('fecha')->useCurrent();
            $t->unique(['id_documento', 'id_usuario']);
        });

        Schema::create('resolucion', function (Blueprint $t) {
            $t->id('id_resolucion');
            $t->string('numero', 30)->unique();                   // RES-2026-001
            $t->foreignId('id_documento')->unique()->constrained('documento', 'id_documento');
            $t->foreignId('id_sesion')->constrained('sesion', 'id_sesion');
            $t->foreignId('id_tipo_tramite')->nullable()->constrained('tipo_tramite', 'id_tipo_tramite');
            $t->string('mayoria_requerida', 20);
            $t->unsignedSmallInteger('presentes');
            $t->unsignedSmallInteger('votos_favor');
            $t->unsignedSmallInteger('votos_contra');
            $t->unsignedSmallInteger('votos_blanco');
            $t->string('resultado', 10);                          // APROBADA | RECHAZADA
            // APROBADA | PROMULGADA | VETADA | MANTENIDA | PUBLICADA | RECHAZADA | ARCHIVADA
            $t->string('estado', 15);
            $t->timestamp('fecha_aprobacion')->useCurrent();
            $t->date('fecha_limite_promulgacion')->nullable();    // 5 días hábiles (Art. 64)
            $t->timestamp('fecha_promulgacion')->nullable();
            $t->timestamp('fecha_veto')->nullable();
            $t->text('veto_fundamento')->nullable();
            $t->timestamp('fecha_publicacion')->nullable();
            $t->string('gaceta_referencia', 100)->nullable();
            $t->index('estado');
        });

        Schema::create('acta', function (Blueprint $t) {
            $t->id('id_acta');
            $t->foreignId('id_sesion')->unique()->constrained('sesion', 'id_sesion')->cascadeOnDelete();
            $t->longText('contenido');
            $t->string('hash', 64);
            $t->foreignId('generado_por')->nullable()->constrained('usuario', 'id_usuario');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acta');
        Schema::dropIfExists('resolucion');
        Schema::dropIfExists('votacion');
        Schema::dropIfExists('asistencia');
    }
};
