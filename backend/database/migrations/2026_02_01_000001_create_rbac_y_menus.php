<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriz de control de acceso (RBAC) y menú dinámico guardados en la base de datos.
 *  - permiso:      acciones del sistema (ej. sesion.convocar)
 *  - rol_permiso:  permisos concedidos a cada rol (la matriz de acceso)
 *  - menu:         opciones de navegación; cada una puede exigir un permiso
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permiso', function (Blueprint $t) {
            $t->id('id_permiso');
            $t->string('codigo', 60)->unique();
            $t->string('descripcion', 200);
            $t->string('modulo', 40);
        });

        Schema::create('rol_permiso', function (Blueprint $t) {
            $t->foreignId('id_rol')->constrained('rol', 'id_rol')->cascadeOnDelete();
            $t->foreignId('id_permiso')->constrained('permiso', 'id_permiso')->cascadeOnDelete();
            $t->primary(['id_rol', 'id_permiso']);
        });

        Schema::create('menu', function (Blueprint $t) {
            $t->id('id_menu');
            $t->unsignedBigInteger('id_padre')->nullable();
            $t->string('titulo', 80);
            $t->string('ruta', 120)->nullable();          // null = grupo (solo agrupa opciones)
            $t->string('icono', 40)->nullable();
            $t->unsignedSmallInteger('orden')->default(0);
            $t->foreignId('id_permiso')->nullable()->constrained('permiso', 'id_permiso')->nullOnDelete(); // null = cualquier usuario autenticado
            $t->boolean('activo')->default(true);
            $t->foreign('id_padre')->references('id_menu')->on('menu')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu');
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permiso');
    }
};
