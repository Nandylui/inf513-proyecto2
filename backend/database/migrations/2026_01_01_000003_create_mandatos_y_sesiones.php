<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CU2 - Gestión de miembros del Consejo y habilitación de quórum (RF-02, RF-03, RF-07)
 * CU3 - Convocatorias, excusas y habilitación de suplentes (RF-04, RF-05, RF-08, RF-19)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mandato', function (Blueprint $t) {
            $t->id('id_mandato');
            $t->foreignId('id_titular')->constrained('usuario', 'id_usuario');
            $t->foreignId('id_suplente')->nullable()->constrained('usuario', 'id_usuario');
            $t->string('carrera', 100);
            $t->string('estamento', 20)->default('DOCENTE');   // DOCENTE | ESTUDIANTE (Art. 52)
            $t->date('fecha_inicio');
            $t->date('fecha_fin');                              // máximo 2 años (Art. 50)
            $t->string('estado', 20)->default('VIGENTE');       // VIGENTE | CONCLUIDO (historial)
            $t->timestamps();
            $t->index(['estado', 'carrera']);
        });

        Schema::create('sesion', function (Blueprint $t) {
            $t->id('id_sesion');
            $t->timestamp('fecha_hora');
            $t->string('lugar', 255);
            $t->string('tipo', 20);                              // ORDINARIA | EXTRAORDINARIA
            $t->string('estado', 20)->default('PROGRAMADA');     // PROGRAMADA | EN_CURSO | FINALIZADA | CANCELADA
            $t->text('orden_del_dia')->nullable();
            $t->string('convocada_por', 20)->default('PRESIDENTE'); // PRESIDENTE | MIEMBROS (3 o más, Art. 55)
            $t->foreignId('id_convocante')->nullable()->constrained('usuario', 'id_usuario');
            $t->unsignedSmallInteger('numero_solicitantes')->default(0);
            $t->boolean('publica')->default(true);              // Art. 56: sesiones públicas
            $t->unsignedSmallInteger('quorum_requerido')->nullable();
            $t->unsignedSmallInteger('quorum_presentes')->nullable();
            $t->boolean('quorum_alcanzado')->nullable();
            $t->timestamps();
        });

        Schema::create('punto_orden_dia', function (Blueprint $t) {
            $t->id('id_punto');
            $t->foreignId('id_sesion')->constrained('sesion', 'id_sesion')->cascadeOnDelete();
            $t->unsignedSmallInteger('numero');
            $t->string('titulo', 255);
            $t->text('descripcion')->nullable();
            $t->unique(['id_sesion', 'numero']);
        });

        Schema::create('excusa', function (Blueprint $t) {
            $t->id('id_excusa');
            $t->foreignId('id_titular')->constrained('usuario', 'id_usuario');
            $t->foreignId('id_sesion')->constrained('sesion', 'id_sesion')->cascadeOnDelete();
            $t->text('motivo');
            $t->string('archivo_nombre')->nullable();
            $t->string('archivo_path')->nullable();
            $t->string('archivo_hash', 64)->nullable();          // sello SHA-256 del documento adjunto
            $t->boolean('firmado_digitalmente')->default(false);
            $t->timestamp('fecha_registro')->useCurrent();
            $t->unique(['id_titular', 'id_sesion']);
        });

        Schema::create('habilitacion', function (Blueprint $t) {
            $t->id('id_habilitacion');
            $t->foreignId('id_sesion')->constrained('sesion', 'id_sesion')->cascadeOnDelete();
            $t->foreignId('id_mandato')->constrained('mandato', 'id_mandato');
            $t->foreignId('id_titular')->constrained('usuario', 'id_usuario');
            $t->foreignId('id_habilitado')->constrained('usuario', 'id_usuario'); // el suplente
            $t->string('motivo', 30)->default('EXCUSA');
            $t->text('constancia');                                // se transcribe al acta (RF-05)
            $t->timestamp('fecha')->useCurrent();
            $t->unique(['id_sesion', 'id_titular']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitacion');
        Schema::dropIfExists('excusa');
        Schema::dropIfExists('punto_orden_dia');
        Schema::dropIfExists('sesion');
        Schema::dropIfExists('mandato');
    }
};
