<?php

namespace App\Console\Commands;

use App\Correo\Catalogo;
use Illuminate\Console\Command;

class CorreoComandos extends Command
{
    protected $signature = 'correo:comandos {--markdown : Imprime la guía en Markdown}';

    protected $description = 'Lista el catálogo de comandos del Sistema Vía Mail.';

    public function handle(): int
    {
        $porCu = [];
        foreach (Catalogo::todos() as $c) {
            $porCu[$c['cu']][] = $c;
        }
        if ($this->option('markdown')) {
            foreach ($porCu as $cu => $lista) {
                $this->line('### '.Catalogo::CASOS_DE_USO[$cu]."\n");
                $this->line('| Comando | Sintaxis | Descripción | Permiso |');
                $this->line('|---|---|---|---|');
                foreach ($lista as $c) {
                    $this->line('| `'.$c['nombre'].'` | `'.str_replace('|', '\|', $c['sintaxis']).'` | '.str_replace('|', '\|', $c['descripcion']).' | '.($c['permiso'] ?? 'cualquier usuario').' |');
                }
                $this->line('');
            }

            return self::SUCCESS;
        }
        foreach ($porCu as $cu => $lista) {
            $this->info(Catalogo::CASOS_DE_USO[$cu]);
            $this->table(['Comando', 'Sintaxis', 'Permiso'], array_map(fn ($c) => [$c['nombre'], $c['sintaxis'], $c['permiso'] ?? '-'], $lista));
        }
        $this->info(count(Catalogo::todos()).' comandos.');

        return self::SUCCESS;
    }
}
