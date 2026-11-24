<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Asistencia;
use App\Models\Habilitacion;
use App\Models\Mandato;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Support\BuscaEntidades;
use App\Support\Fechas;
use Illuminate\Support\Collection;

/**
 * CU2 - Gestión de miembros del Consejo y habilitación de quórum.
 * RF-02 (mandatos de 2 años), RF-03 (panel de titulares/suplentes), RF-07 (quórum).
 */
class MiembroService
{
    use BuscaEntidades;

    private const ESTAMENTO_POR_ROL = [
        'TITULAR_DOCENTE' => 'DOCENTE', 'SUPLENTE_DOCENTE' => 'DOCENTE',
        'TITULAR_ESTUDIANTE' => 'ESTUDIANTE', 'SUPLENTE_ESTUDIANTE' => 'ESTUDIANTE',
    ];

    /** Mandatos vigentes: estado VIGENTE y fecha actual dentro del período. */
    public function vigentes(): Collection
    {
        $hoy = now()->toDateString();

        return Mandato::with(['titular.rol', 'suplente.rol'])
            ->where('estado', 'VIGENTE')
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereDate('fecha_fin', '>=', $hoy)
            ->orderBy('carrera')->orderBy('estamento')
            ->get();
    }

    public function listar(?string $filtro = null): Collection
    {
        $filtro = mb_strtoupper(trim((string) $filtro));
        $q = Mandato::with(['titular.rol', 'suplente.rol'])->orderBy('carrera')->orderBy('estamento')->orderByDesc('fecha_inicio');

        if ($filtro === 'VIGENTE' || $filtro === 'VIGENTES') {
            return $this->vigentes();
        }
        if ($filtro === 'CONCLUIDO' || $filtro === 'CONCLUIDOS' || $filtro === 'HISTORIAL') {
            $q->where('estado', 'CONCLUIDO');
        } elseif ($filtro !== '' && $filtro !== '*') {
            $q->whereRaw('UPPER(carrera) LIKE ?', ['%'.$filtro.'%']);
        }

        return $q->get();
    }

    public function registrar(string $ciTitular, ?string $ciSuplente, string $carrera, string $estamento, string $inicio, string $fin, ?Usuario $actor = null): Mandato
    {
        $titular = $this->usuarioPorCi($ciTitular);
        $carrera = mb_strtoupper($this->requerido($carrera, 'carrera'));
        $estamento = mb_strtoupper($this->requerido($estamento, 'estamento'));
        $fechaInicio = Fechas::parsear($inicio, 'fecha_inicio')->startOfDay();
        $fechaFin = Fechas::parsear($fin, 'fecha_fin')->startOfDay();

        if (! in_array($estamento, ['DOCENTE', 'ESTUDIANTE'], true)) {
            throw ReglaNegocioException::invalida('El estamento debe ser DOCENTE o ESTUDIANTE.');
        }
        if (! $titular->tieneRol('TITULAR_DOCENTE', 'TITULAR_ESTUDIANTE')) {
            throw ReglaNegocioException::invalida("El usuario {$ciTitular} no tiene rol de consejero titular.");
        }
        if ((self::ESTAMENTO_POR_ROL[$titular->nombreRol()] ?? null) !== $estamento) {
            throw ReglaNegocioException::invalida("El rol {$titular->nombreRol()} no corresponde al estamento {$estamento}.");
        }
        if ($fechaFin->lte($fechaInicio)) {
            throw ReglaNegocioException::invalida('La fecha de fin debe ser posterior a la fecha de inicio.');
        }
        // Art. 50: permanecen dos (2) años en sus funciones.
        if ($fechaFin->gt($fechaInicio->copy()->addYears((int) config('consejo.reglas.anios_mandato')))) {
            throw ReglaNegocioException::invalida('El mandato no puede exceder 2 años (Art. 50 del Estatuto).');
        }
        // Art. 50: sin derecho a reelección al mismo cargo.
        if (Mandato::where('id_titular', $titular->id_usuario)->where('carrera', $carrera)->where('estamento', $estamento)->exists()) {
            throw ReglaNegocioException::invalida("El consejero {$ciTitular} ya ejerció este cargo en {$carrera}: no hay reelección al mismo cargo (Art. 50).");
        }
        // Art. 52: un (1) delegado titular y un (1) suplente por carrera y estamento.
        if (Mandato::where('estado', 'VIGENTE')->where('carrera', $carrera)->where('estamento', $estamento)->exists()) {
            throw ReglaNegocioException::invalida("{$carrera} ya tiene un titular {$estamento} vigente (Art. 52). Concluya el mandato anterior primero.");
        }

        $idSuplente = null;
        $ciSuplente = trim((string) $ciSuplente);
        if ($ciSuplente !== '' && mb_strtoupper($ciSuplente) !== 'NINGUNO') {
            $suplente = $this->usuarioPorCi($ciSuplente);
            if ($suplente->nombreRol() !== 'SUPLENTE_'.$estamento) {
                throw ReglaNegocioException::invalida("El usuario {$ciSuplente} debe tener rol SUPLENTE_{$estamento}.");
            }
            if (Mandato::where('estado', 'VIGENTE')->where('id_suplente', $suplente->id_usuario)->exists()) {
                throw ReglaNegocioException::invalida("El suplente {$ciSuplente} ya está asignado a otro mandato vigente.");
            }
            $idSuplente = $suplente->id_usuario;
        }

        $mandato = Mandato::create([
            'id_titular' => $titular->id_usuario, 'id_suplente' => $idSuplente, 'carrera' => $carrera,
            'estamento' => $estamento, 'fecha_inicio' => $fechaInicio->toDateString(),
            'fecha_fin' => $fechaFin->toDateString(), 'estado' => 'VIGENTE',
        ]);
        AuditoriaService::registrar($actor, 'MANDATO_REGISTRADO', 'mandato', $mandato->id_mandato, ['carrera' => $carrera, 'titular' => $ciTitular]);

        return $mandato->load(['titular.rol', 'suplente.rol']);
    }

    public function modificar(int|string $id, string $campo, string $valor, ?Usuario $actor = null): Mandato
    {
        $mandato = $this->mandato($id);
        $campo = mb_strtolower(trim($campo));

        switch ($campo) {
            case 'suplente':
                $suplente = $this->usuarioPorCi($valor);
                if ($suplente->nombreRol() !== 'SUPLENTE_'.$mandato->estamento) {
                    throw ReglaNegocioException::invalida("El suplente debe tener rol SUPLENTE_{$mandato->estamento}.");
                }
                $mandato->id_suplente = $suplente->id_usuario;
                break;
            case 'fecha_fin':
                $fin = Fechas::parsear($valor, 'fecha_fin')->startOfDay();
                if ($fin->gt($mandato->fecha_inicio->copy()->addYears(2))) {
                    throw ReglaNegocioException::invalida('El mandato no puede exceder 2 años (Art. 50).');
                }
                $mandato->fecha_fin = $fin->toDateString();
                break;
            case 'carrera':
                $mandato->carrera = mb_strtoupper($this->requerido($valor, 'carrera'));
                break;
            default:
                throw ReglaNegocioException::invalida('Campo no modificable. Use: suplente, fecha_fin, carrera.');
        }
        $mandato->save();
        AuditoriaService::registrar($actor, 'MANDATO_MODIFICADO', 'mandato', $mandato->id_mandato, ['campo' => $campo]);

        return $mandato->load(['titular.rol', 'suplente.rol']);
    }

    /** Pasa el mandato al historial de períodos anteriores (RF-02). */
    public function concluir(int|string $id, ?Usuario $actor = null): Mandato
    {
        $mandato = $this->mandato($id);
        if ($mandato->estado === 'CONCLUIDO') {
            throw ReglaNegocioException::invalida('El mandato ya está concluido.');
        }
        $mandato->update(['estado' => 'CONCLUIDO']);
        AuditoriaService::registrar($actor, 'MANDATO_CONCLUIDO', 'mandato', $mandato->id_mandato);

        return $mandato->load(['titular.rol', 'suplente.rol']);
    }

    /**
     * RF-03: panel con titulares, suplentes vigentes y quién está habilitado en cada sesión.
     * Si no se indica sesión, el habilitado por defecto es el titular.
     */
    public function panel(?Sesion $sesion = null): array
    {
        $excusados = [];
        $habilitaciones = collect();
        $asistieron = [];
        if ($sesion) {
            $habilitaciones = Habilitacion::with('habilitado')->where('id_sesion', $sesion->id_sesion)->get()->keyBy('id_titular');
            $excusados = $habilitaciones->keys()->all();
            $asistieron = Asistencia::where('id_sesion', $sesion->id_sesion)->pluck('id_usuario')->all();
        }

        $filas = [];
        foreach ($this->vigentes() as $m) {
            $hab = $habilitaciones->get($m->id_titular);
            $habilitado = $hab ? $hab->habilitado : $m->titular;
            $filas[] = [
                'id_mandato' => $m->id_mandato,
                'carrera' => $m->carrera,
                'estamento' => $m->estamento,
                'titular' => $this->resumenUsuario($m->titular),
                'suplente' => $m->suplente ? $this->resumenUsuario($m->suplente) : null,
                'habilitado' => $this->resumenUsuario($habilitado),
                'condicion' => $hab ? 'SUPLENTE' : 'TITULAR',
                'motivo_reemplazo' => $hab?->motivo,
                'presente' => $habilitado ? in_array($habilitado->id_usuario, $asistieron, true) : false,
                'fecha_fin' => $m->fecha_fin->toDateString(),
            ];
        }

        $presidente = Usuario::with('rol')->where('estado', 'ACTIVO')->whereHas('rol', fn ($r) => $r->where('nombre', 'PRESIDENTE'))->first();
        if ($presidente) {
            array_unshift($filas, [
                'id_mandato' => null, 'carrera' => 'DECANATURA', 'estamento' => 'PRESIDENTE',
                'titular' => $this->resumenUsuario($presidente), 'suplente' => null,
                'habilitado' => $this->resumenUsuario($presidente), 'condicion' => 'TITULAR', 'motivo_reemplazo' => null,
                'presente' => in_array($presidente->id_usuario, $asistieron, true), 'fecha_fin' => null,
            ]);
        }

        return $filas;
    }

    /**
     * RF-07: quórum de mayoría absoluta de los miembros titulares (o suplentes acreditados). Art. 53.
     * Total de miembros = mandatos vigentes + Presidente (Art. 54: tiene voz y voto).
     */
    public function quorum(Sesion $sesion, bool $guardar = false): array
    {
        $panel = $this->panel($sesion);
        $total = count($panel);
        $requerido = intdiv($total, 2) + 1;
        $presentes = count(array_filter($panel, fn ($f) => $f['presente']));
        $alcanzado = $total > 0 && $presentes >= $requerido;

        if ($guardar) {
            $sesion->update(['quorum_requerido' => $requerido, 'quorum_presentes' => $presentes, 'quorum_alcanzado' => $alcanzado]);
        }

        return [
            'id_sesion' => $sesion->id_sesion,
            'total_miembros' => $total,
            'requerido' => $requerido,
            'presentes' => $presentes,
            'faltan' => max(0, $requerido - $presentes),
            'alcanzado' => $alcanzado,
            'regla' => 'Mayoría absoluta de miembros titulares o suplentes acreditados (Art. 53)',
        ];
    }

    public function resumenUsuario(?Usuario $u): ?array
    {
        return $u ? ['id_usuario' => $u->id_usuario, 'ci' => $u->ci, 'nombre' => $u->nombre_completo, 'correo' => $u->correo, 'rol' => $u->nombreRol()] : null;
    }
}
