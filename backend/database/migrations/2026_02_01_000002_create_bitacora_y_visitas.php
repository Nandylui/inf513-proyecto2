<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de accesos (inicios de sesión exitosos y fallidos, IP, recursos consultados)
 * y contador persistente de visitas por página.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_acceso', function (Blueprint $t) {
            $t->id('id_bitacora');
            $t->foreignId('id_usuario')->nullable()->constrained('usuario', 'id_usuario')->nullOnDelete();
            $t->string('correo', 190)->nullable();           // correo digitado (útil en intentos fallidos)
            $t->string('evento', 20);                         // LOGIN_OK | LOGIN_FALLIDO | LOGOUT | ACCESO | DENEGADO
            $t->string('recurso', 200)->nullable();           // ruta del recurso consultado (ej. GET api/sesiones/{id})
            $t->string('metodo', 8)->nullable();
            $t->unsignedSmallInteger('estado_http')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->string('detalle', 255)->nullable();
            $t->timestamp('fecha')->useCurrent();
            $t->index(['evento', 'fecha']);
            $t->index('recurso');
            $t->index('ip');
        });

        Schema::create('visita_pagina', function (Blueprint $t) {
            $t->id('id_visita');
            $t->string('pagina', 120)->unique();
            $t->unsignedBigInteger('contador')->default(0);
            $t->timestamp('ultima_visita')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visita_pagina');
        Schema::dropIfExists('bitacora_acceso');
    }
};
