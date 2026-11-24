<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\Usuario;
use App\Support\Contexto;

/** RF-22: historial de acciones relevantes (carga de documentos, validaciones, votos, cambios de estado). */
class AuditoriaService
{
    public static function registrar(?Usuario $usuario, string $accion, ?string $entidad = null, ?int $idEntidad = null, array $detalle = []): void
    {
        Auditoria::create([
            'id_usuario' => $usuario?->id_usuario,
            'accion' => $accion,
            'entidad' => $entidad,
            'id_entidad' => $idEntidad,
            'detalle' => $detalle ?: null,
            'canal' => Contexto::$canal,
            'ip' => Contexto::$ip,
        ]);
    }
}
