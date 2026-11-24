<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\ConceptoPago;
use App\Models\Cuota;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\PlanPago;
use App\Models\Usuario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Módulo de pagos electrónicos (pasarela simulada) para aranceles y tasas del Consejo Facultativo:
 * métodos de pago por usuario, pagos únicos y planes de pago en cuotas.
 */
class PagoService
{
    // ------------------------------------------------------------------ catálogo de conceptos

    public function conceptos(bool $incluirInactivos = false): Collection
    {
        return ConceptoPago::when(! $incluirInactivos, fn ($q) => $q->where('activo', true))->orderBy('nombre')->get();
    }

    public function guardarConcepto(array $d, ?ConceptoPago $concepto = null, ?Usuario $actor = null): ConceptoPago
    {
        $concepto ??= new ConceptoPago;
        $concepto->fill([
            'codigo' => mb_strtoupper(trim($d['codigo'])), 'nombre' => trim($d['nombre']), 'descripcion' => $d['descripcion'] ?? null,
            'monto' => round((float) $d['monto'], 2), 'permite_plan' => (bool) $d['permite_plan'],
            'max_cuotas' => $d['permite_plan'] ? (int) $d['max_cuotas'] : 1, 'activo' => (bool) $d['activo'],
        ])->save();
        AuditoriaService::registrar($actor, 'CONCEPTO_PAGO_GUARDADO', 'concepto_pago', $concepto->id_concepto, ['codigo' => $concepto->codigo, 'monto' => $concepto->monto]);

        return $concepto;
    }

    /** Si el concepto ya tiene pagos se desactiva (trazabilidad); si no, se elimina. */
    public function eliminarConcepto(int $id, ?Usuario $actor = null): array
    {
        $c = ConceptoPago::find($id) ?? throw ReglaNegocioException::noEncontrado('No existe el concepto de pago.');
        if (Pago::where('id_concepto', $id)->exists() || PlanPago::where('id_concepto', $id)->exists()) {
            $c->update(['activo' => false]);
            AuditoriaService::registrar($actor, 'CONCEPTO_PAGO_DESACTIVADO', 'concepto_pago', $id);

            return ['message' => 'El concepto ya tiene pagos asociados: se desactivó en lugar de eliminarse.'];
        }
        $c->delete();
        AuditoriaService::registrar($actor, 'CONCEPTO_PAGO_ELIMINADO', 'concepto_pago', $id);

        return ['message' => 'Concepto eliminado.'];
    }

    // ------------------------------------------------------------------ métodos de pago

    public function metodos(Usuario $u): Collection
    {
        return MetodoPago::where('id_usuario', $u->id_usuario)->orderByDesc('predeterminado')->orderBy('id_metodo')->get()
            ->each(fn (MetodoPago $m) => $m->setAttribute('descripcion', $m->descripcion())->setAttribute('vencida', $m->tipo === 'TARJETA' && PasarelaSimulada::vencida($m->vencimiento)));
    }

    /** Registra un método de pago. Del número de tarjeta solo se conservan la marca y los últimos 4 dígitos; el CVV no se guarda. */
    public function crearMetodo(Usuario $u, array $d): MetodoPago
    {
        $tipo = $d['tipo'];
        $datos = ['id_usuario' => $u->id_usuario, 'tipo' => $tipo, 'alias' => trim($d['alias']), 'titular' => null, 'marca' => null, 'ultimos4' => null, 'vencimiento' => null];

        switch ($tipo) {
            case 'TARJETA':
                $numero = preg_replace('/[\s\-]/', '', (string) ($d['numero'] ?? ''));
                $this->exigir(preg_match('/^\d{13,19}$/', $numero) === 1, 'numero', 'El número de tarjeta debe tener entre 13 y 19 dígitos.');
                $this->exigir(self::luhn($numero), 'numero', 'El número de tarjeta no es válido (falla la verificación de dígito).');
                $titular = trim((string) ($d['titular'] ?? ''));
                $this->exigir(mb_strlen($titular) >= 3 && preg_match('/^[\p{L}\s\.\'\-]+$/u', $titular) === 1, 'titular', 'Ingrese el nombre del titular tal como figura en la tarjeta (solo letras).');
                $venc = (string) ($d['vencimiento'] ?? '');
                $this->exigir(preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $venc) === 1, 'vencimiento', 'El vencimiento debe tener el formato MM/AA.');
                $this->exigir(! PasarelaSimulada::vencida($venc), 'vencimiento', 'La tarjeta está vencida.');
                $this->exigir(preg_match('/^\d{3,4}$/', (string) ($d['cvv'] ?? '')) === 1, 'cvv', 'El código de seguridad (CVV) debe tener 3 o 4 dígitos.');
                $datos = array_merge($datos, ['titular' => mb_strtoupper($titular), 'marca' => self::marca($numero), 'ultimos4' => substr($numero, -4), 'vencimiento' => $venc]);
                break;
            case 'QR':
                $datos['marca'] = 'QR Simple';
                break;
            case 'TRANSFERENCIA':
                $banco = trim((string) ($d['banco'] ?? ''));
                $cuenta = preg_replace('/[\s\-]/', '', (string) ($d['cuenta'] ?? ''));
                $this->exigir(mb_strlen($banco) >= 3, 'banco', 'Indique el nombre del banco.');
                $this->exigir(preg_match('/^\d{6,20}$/', $cuenta) === 1, 'cuenta', 'El número de cuenta debe tener entre 6 y 20 dígitos.');
                $datos = array_merge($datos, ['marca' => $banco, 'ultimos4' => substr($cuenta, -4), 'titular' => trim((string) ($d['titular'] ?? '')) ?: null]);
                break;
            case 'BILLETERA':
                $celular = preg_replace('/[\s\-]/', '', (string) ($d['celular'] ?? ''));
                $this->exigir(preg_match('/^[67]\d{7}$/', $celular) === 1, 'celular', 'El celular debe tener 8 dígitos y empezar con 6 o 7.');
                $datos = array_merge($datos, ['marca' => trim((string) ($d['banco'] ?? '')) ?: 'Billetera móvil', 'ultimos4' => substr($celular, -4)]);
                break;
        }

        return DB::transaction(function () use ($u, $datos, $d) {
            $primero = ! MetodoPago::where('id_usuario', $u->id_usuario)->exists();
            $m = MetodoPago::create($datos + ['predeterminado' => false]);
            if ($primero || ! empty($d['predeterminado'])) {
                $this->marcarPredeterminado($u, $m);
            }
            AuditoriaService::registrar($u, 'METODO_PAGO_REGISTRADO', 'metodo_pago', $m->id_metodo, ['tipo' => $m->tipo, 'detalle' => $m->descripcion()]);

            return $m->fresh();
        });
    }

    public function eliminarMetodo(Usuario $u, int $id): void
    {
        $m = $this->metodoPropio($u, $id);
        $eraPredeterminado = $m->predeterminado;
        $m->delete();
        if ($eraPredeterminado && ($otro = MetodoPago::where('id_usuario', $u->id_usuario)->first())) {
            $this->marcarPredeterminado($u, $otro);
        }
        AuditoriaService::registrar($u, 'METODO_PAGO_ELIMINADO', 'metodo_pago', $id);
    }

    public function predeterminar(Usuario $u, int $id): MetodoPago
    {
        $m = $this->metodoPropio($u, $id);
        $this->marcarPredeterminado($u, $m);

        return $m->fresh();
    }

    // ------------------------------------------------------------------ pagos

    /** Pago único de un concepto (arancel o tasa). */
    public function pagar(Usuario $u, array $d): Pago
    {
        $concepto = ConceptoPago::where('activo', true)->find($d['id_concepto'] ?? 0)
            ?? throw ReglaNegocioException::noEncontrado('El concepto de pago no existe o no está disponible.');
        $metodo = $this->metodoPropio($u, (int) ($d['id_metodo'] ?? 0));
        $this->exigirCvv($metodo, $d['cvv'] ?? null);

        return $this->cobrar($u, $metodo, $concepto, (float) $concepto->monto, 'UNICO', null, null, $d['observacion'] ?? null);
    }

    public function crearPlan(Usuario $u, array $d): PlanPago
    {
        $concepto = ConceptoPago::where('activo', true)->find($d['id_concepto'] ?? 0)
            ?? throw ReglaNegocioException::noEncontrado('El concepto de pago no existe o no está disponible.');
        if (! $concepto->permite_plan) {
            throw ReglaNegocioException::invalida("El concepto «{$concepto->nombre}» no admite plan de pagos: debe pagarse en un solo pago.");
        }
        $n = (int) ($d['num_cuotas'] ?? 0);
        if ($n < 2 || $n > $concepto->max_cuotas) {
            throw ValidationException::withMessages(['num_cuotas' => "El número de cuotas debe estar entre 2 y {$concepto->max_cuotas} para este concepto."]);
        }
        if (PlanPago::where('id_usuario', $u->id_usuario)->where('id_concepto', $concepto->id_concepto)->where('estado', 'ACTIVO')->exists()) {
            throw ReglaNegocioException::invalida('Ya tiene un plan de pagos activo para este concepto.');
        }

        return DB::transaction(function () use ($u, $concepto, $n) {
            $total = (float) $concepto->monto;
            $base = round($total / $n, 2);
            $plan = PlanPago::create(['id_usuario' => $u->id_usuario, 'id_concepto' => $concepto->id_concepto, 'monto_total' => $total, 'num_cuotas' => $n, 'estado' => 'ACTIVO']);
            for ($i = 1; $i <= $n; $i++) {
                Cuota::create([
                    'id_plan' => $plan->id_plan, 'numero' => $i,
                    'monto' => $i === $n ? round($total - $base * ($n - 1), 2) : $base,   // la última cuota absorbe el redondeo
                    'fecha_vencimiento' => now()->addDays(30 * ($i - 1))->toDateString(), 'estado' => 'PENDIENTE',
                ]);
            }
            AuditoriaService::registrar($u, 'PLAN_PAGO_CREADO', 'plan_pago', $plan->id_plan, ['concepto' => $concepto->codigo, 'cuotas' => $n]);

            return $plan->load('concepto', 'cuotas');
        });
    }

    public function pagarCuota(Usuario $u, int $idCuota, array $d): Pago
    {
        $cuota = Cuota::with('plan.concepto')->find($idCuota) ?? throw ReglaNegocioException::noEncontrado('No existe la cuota indicada.');
        $plan = $cuota->plan;
        if ($plan->id_usuario !== $u->id_usuario) {
            throw ReglaNegocioException::prohibido('La cuota pertenece a otro usuario.');
        }
        if ($plan->estado !== 'ACTIVO' || $cuota->estado !== 'PENDIENTE') {
            throw ReglaNegocioException::invalida('La cuota no está pendiente de pago.');
        }
        if ($plan->cuotas()->where('numero', '<', $cuota->numero)->where('estado', 'PENDIENTE')->exists()) {
            throw ReglaNegocioException::invalida('Debe pagar primero las cuotas anteriores del plan.');
        }
        $metodo = $this->metodoPropio($u, (int) ($d['id_metodo'] ?? 0));
        $this->exigirCvv($metodo, $d['cvv'] ?? null);

        return $this->cobrar($u, $metodo, $plan->concepto, (float) $cuota->monto, 'CUOTA', $plan, $cuota, "Cuota {$cuota->numero} de {$plan->num_cuotas}");
    }

    public function cancelarPlan(Usuario $u, int $idPlan): PlanPago
    {
        $plan = PlanPago::find($idPlan) ?? throw ReglaNegocioException::noEncontrado('No existe el plan de pagos.');
        if ($plan->id_usuario !== $u->id_usuario) {
            throw ReglaNegocioException::prohibido('El plan pertenece a otro usuario.');
        }
        if ($plan->estado !== 'ACTIVO') {
            throw ReglaNegocioException::invalida('Solo se pueden cancelar planes activos.');
        }
        DB::transaction(function () use ($plan, $u) {
            $plan->cuotas()->where('estado', 'PENDIENTE')->update(['estado' => 'CANCELADA']);
            $plan->update(['estado' => 'CANCELADO']);
            AuditoriaService::registrar($u, 'PLAN_PAGO_CANCELADO', 'plan_pago', $plan->id_plan);
        });

        return $plan->fresh(['concepto', 'cuotas']);
    }

    // ------------------------------------------------------------------ consultas

    public function misPagos(Usuario $u): Collection
    {
        return Pago::with('concepto:id_concepto,nombre,codigo')->where('id_usuario', $u->id_usuario)->orderByDesc('id_pago')->get();
    }

    public function misPlanes(Usuario $u, bool $todos = false): Collection
    {
        $q = PlanPago::with('concepto', 'cuotas', 'usuario:id_usuario,nombres,apellidos,ci')->orderByDesc('id_plan');
        if (! $todos) {
            $q->where('id_usuario', $u->id_usuario);
        }
        $hoy = now()->toDateString();

        return $q->get()->each(function (PlanPago $p) use ($hoy) {
            $p->cuotas->each(fn (Cuota $c) => $c->setAttribute('vencida', $c->estado === 'PENDIENTE' && $c->fecha_vencimiento->toDateString() < $hoy));
            $p->setAttribute('pagado', round($p->cuotas->where('estado', 'PAGADA')->sum('monto'), 2));
        });
    }

    /** Todas las transacciones, con filtros (administración). */
    public function todos(array $f): array
    {
        $q = Pago::with('usuario:id_usuario,ci,nombres,apellidos', 'concepto:id_concepto,nombre,codigo')->orderByDesc('id_pago');
        if (! empty($f['estado'])) {
            $q->where('estado', mb_strtoupper($f['estado']));
        }
        if (! empty($f['concepto'])) {
            $q->where('id_concepto', (int) $f['concepto']);
        }
        if (! empty($f['q'])) {
            $like = '%'.mb_strtolower(trim($f['q'])).'%';
            $q->where(fn ($w) => $w->whereRaw('LOWER(referencia) LIKE ?', [$like])
                ->orWhereHas('usuario', fn ($u) => $u->whereRaw('LOWER(nombres) LIKE ?', [$like])->orWhereRaw('LOWER(apellidos) LIKE ?', [$like])->orWhere('ci', trim($f['q']))));
        }
        if (! empty($f['desde'])) {
            $q->where('created_at', '>=', $f['desde'].' 00:00:00');
        }
        if (! empty($f['hasta'])) {
            $q->where('created_at', '<=', $f['hasta'].' 23:59:59');
        }

        return $q->limit(300)->get()->all();
    }

    public function resumen(): array
    {
        $pagos = Pago::with('concepto:id_concepto,nombre')->get();
        $aprobados = $pagos->where('estado', 'APROBADO');
        $porMes = $aprobados->groupBy(fn ($p) => $p->created_at->format('Y-m'))->map(fn ($g, $mes) => ['mes' => $mes, 'monto' => round($g->sum('monto'), 2), 'pagos' => $g->count()])->sortKeys()->values();
        $porConcepto = $aprobados->groupBy(fn ($p) => $p->concepto?->nombre ?? 'Otro')->map(fn ($g, $c) => ['concepto' => $c, 'monto' => round($g->sum('monto'), 2), 'pagos' => $g->count()])->sortByDesc('monto')->values();

        return [
            'recaudado' => round($aprobados->sum('monto'), 2),
            'aprobados' => $aprobados->count(),
            'rechazados' => $pagos->where('estado', 'RECHAZADO')->count(),
            'planes_activos' => PlanPago::where('estado', 'ACTIVO')->count(),
            'cuotas_pendientes' => Cuota::where('estado', 'PENDIENTE')->count(),
            'por_mes' => $porMes,
            'por_concepto' => $porConcepto,
        ];
    }

    public function comprobante(Usuario $u, int $id, bool $esGestor): array
    {
        $p = Pago::with('usuario:id_usuario,ci,nombres,apellidos,correo', 'concepto')->find($id) ?? throw ReglaNegocioException::noEncontrado('No existe el pago.');
        if (! $esGestor && $p->id_usuario !== $u->id_usuario) {
            throw ReglaNegocioException::prohibido('El pago pertenece a otro usuario.');
        }

        return [
            'referencia' => $p->referencia, 'estado' => $p->estado, 'fecha' => $p->created_at->format('Y-m-d H:i:s'), 'tipo' => $p->tipo,
            'concepto' => $p->concepto?->nombre, 'monto' => number_format((float) $p->monto, 2, '.', ''), 'moneda' => $p->moneda,
            'metodo' => $p->detalle_metodo, 'autorizacion' => $p->codigo_autorizacion, 'motivo_rechazo' => $p->motivo_rechazo, 'observacion' => $p->observacion,
            'pagador' => ['ci' => $p->usuario?->ci, 'nombre' => trim(($p->usuario?->nombres ?? '').' '.($p->usuario?->apellidos ?? '')), 'correo' => $p->usuario?->correo],
            'aviso' => 'Comprobante generado por una pasarela de pagos SIMULADA (proyecto académico). No tiene validez fiscal ni se realizó ningún cargo real.',
        ];
    }

    // ------------------------------------------------------------------ internos

    private function cobrar(Usuario $u, MetodoPago $metodo, ConceptoPago $concepto, float $monto, string $tipo, ?PlanPago $plan, ?Cuota $cuota, ?string $observacion): Pago
    {
        return DB::transaction(function () use ($u, $metodo, $concepto, $monto, $tipo, $plan, $cuota, $observacion) {
            $r = PasarelaSimulada::cobrar($metodo, $monto);
            $pago = Pago::create([
                'id_usuario' => $u->id_usuario, 'id_concepto' => $concepto->id_concepto, 'id_metodo' => $metodo->id_metodo,
                'id_plan' => $plan?->id_plan, 'id_cuota' => $cuota?->id_cuota, 'tipo' => $tipo, 'monto' => $monto, 'moneda' => 'BOB',
                'estado' => $r['aprobado'] ? 'APROBADO' : 'RECHAZADO',
                'referencia' => 'PAG-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                'codigo_autorizacion' => $r['codigo'], 'motivo_rechazo' => $r['motivo'], 'detalle_metodo' => $metodo->descripcion(),
                'observacion' => $observacion !== null ? mb_substr(trim($observacion), 0, 200) : null,
            ]);

            if ($r['aprobado'] && $cuota) {
                $cuota->update(['estado' => 'PAGADA', 'id_pago' => $pago->id_pago, 'fecha_pago' => now()]);
                if (! $plan->cuotas()->where('estado', 'PENDIENTE')->exists()) {
                    $plan->update(['estado' => 'COMPLETADO']);
                }
            }

            AuditoriaService::registrar($u, $r['aprobado'] ? 'PAGO_APROBADO' : 'PAGO_RECHAZADO', 'pago', $pago->id_pago, [
                'referencia' => $pago->referencia, 'monto' => $monto, 'concepto' => $concepto->codigo, 'motivo' => $r['motivo'],
            ]);
            NotificacionService::notificar(
                $u, 'PAGO', ($r['aprobado'] ? 'Pago aprobado ' : 'Pago rechazado ').$pago->referencia,
                ($r['aprobado'] ? "Se registró su pago de Bs {$pago->monto} por «{$concepto->nombre}». Código de autorización {$r['codigo']}." : "Su pago de Bs {$pago->monto} por «{$concepto->nombre}» fue rechazado: {$r['motivo']}")
                ."\nPasarela de pagos simulada: no se realizó ningún cargo real."
            );

            return $pago->load('concepto:id_concepto,nombre,codigo');
        });
    }

    private function metodoPropio(Usuario $u, int $id): MetodoPago
    {
        $m = MetodoPago::find($id) ?? throw ReglaNegocioException::noEncontrado('Seleccione un método de pago válido.');
        if ($m->id_usuario !== $u->id_usuario) {
            throw ReglaNegocioException::prohibido('El método de pago pertenece a otro usuario.');
        }

        return $m;
    }

    private function marcarPredeterminado(Usuario $u, MetodoPago $m): void
    {
        MetodoPago::where('id_usuario', $u->id_usuario)->update(['predeterminado' => false]);
        $m->update(['predeterminado' => true]);
    }

    private function exigirCvv(MetodoPago $m, mixed $cvv): void
    {
        if ($m->tipo === 'TARJETA') {
            $this->exigir(preg_match('/^\d{3,4}$/', (string) $cvv) === 1, 'cvv', 'Ingrese el código de seguridad (CVV) de 3 o 4 dígitos. No se guarda en el sistema.');
        }
    }

    private function exigir(bool $condicion, string $campo, string $mensaje): void
    {
        if (! $condicion) {
            throw ValidationException::withMessages([$campo => $mensaje]);
        }
    }

    /** Algoritmo de Luhn (verificación del número de tarjeta). */
    public static function luhn(string $numero): bool
    {
        $suma = 0;
        $alterno = false;
        for ($i = strlen($numero) - 1; $i >= 0; $i--) {
            $n = (int) $numero[$i];
            if ($alterno) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $suma += $n;
            $alterno = ! $alterno;
        }

        return $suma % 10 === 0;
    }

    public static function marca(string $numero): string
    {
        return match (true) {
            str_starts_with($numero, '4') => 'VISA',
            (bool) preg_match('/^(5[1-5]|2[2-7])/', $numero) => 'MASTERCARD',
            (bool) preg_match('/^3[47]/', $numero) => 'AMEX',
            default => 'TARJETA',
        };
    }
}
