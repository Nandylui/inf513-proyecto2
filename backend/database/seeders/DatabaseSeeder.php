<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolSeeder::class, PermisoSeeder::class, MenuSeeder::class, TipoTramiteSeeder::class, ConceptoPagoSeeder::class, AdminSeeder::class]);

        if (env('SEED_DEMO', true)) {
            $this->call(DemoSeeder::class);
            if (env('SEED_HISTORICO', true)) {
                $this->call(DemoHistoricoSeeder::class);
            }
        }
    }
}
