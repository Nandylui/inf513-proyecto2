<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CU1 - Gestión de Usuarios (RF-01).
 * Se conservan los nombres de tabla y columnas del diseño original (rol, usuario).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol', function (Blueprint $t) {
            $t->id('id_rol');
            $t->string('nombre', 50)->unique();
            $t->text('descripcion')->nullable();
        });

        Schema::create('usuario', function (Blueprint $t) {
            $t->id('id_usuario');
            $t->string('ci', 20)->unique();
            $t->string('nombres', 100);
            $t->string('apellidos', 100);
            $t->string('correo', 150)->unique();   // el sistema identifica al remitente por su correo
            $t->string('telefono', 30)->nullable();
            $t->string('password');
            $t->foreignId('id_rol')->constrained('rol', 'id_rol');
            $t->string('estado', 20)->default('ACTIVO'); // ACTIVO | INACTIVO
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
        Schema::dropIfExists('rol');
    }
};
