<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Preferencias de interfaz del usuario: tema, modo día/noche, tamaño de fuente y alto contraste. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $t) {
            $t->json('preferencias')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $t) {
            $t->dropColumn('preferencias');
        });
    }
};
