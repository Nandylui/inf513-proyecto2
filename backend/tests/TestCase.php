<?php

namespace Tests;

use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Support\Permisos;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Carga roles, tipos de trámite, administrador y los datos de demostración (3 carreras). */
    protected bool $seed = true;

    protected string $seeder = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Permisos::limpiarCache();   // la matriz de acceso se guarda en memoria: cada prueba parte de la BD recién sembrada
    }

    protected function usuario(string $correo): Usuario
    {
        return Usuario::with('rol')->where('correo', $correo)->firstOrFail();
    }

    protected function presidente(): Usuario
    {
        return $this->usuario('presidente@consejo.test');
    }

    protected function secretario(): Usuario
    {
        return $this->usuario('secretario@consejo.test');
    }

    protected function admin(): Usuario
    {
        return $this->usuario(config('consejo.admin.correo'));
    }
}
