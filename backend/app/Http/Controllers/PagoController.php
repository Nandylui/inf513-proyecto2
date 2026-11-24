<?php

namespace App\Http\Controllers;

use App\Models\ConceptoPago;
use App\Services\PagoService;
use App\Support\Permisos;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function __construct(private PagoService $s) {}

    // ---- conceptos
    public function conceptos(Request $r)
    {
        $todos = $r->boolean('todos') && Permisos::puede($r->user(), 'pago.gestionar');

        return $this->s->conceptos($todos);
    }

    public function guardarConcepto(Request $r, ?int $id = null)
    {
        $d = $r->validate([
            'codigo' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_]+$/', 'unique:concepto_pago,codigo'.($id ? ",{$id},id_concepto" : '')],
            'nombre' => 'required|string|min:3|max:120',
            'descripcion' => 'nullable|string|max:500',
            'monto' => 'required|numeric|gt:0|lte:5000',
            'permite_plan' => 'required|boolean',
            'max_cuotas' => 'required_if:permite_plan,true|integer|between:1,12',
            'activo' => 'required|boolean',
        ]);
        $c = $id ? ConceptoPago::findOrFail($id) : null;
        $d['max_cuotas'] = $d['max_cuotas'] ?? 1;

        return response()->json($this->s->guardarConcepto($d, $c, $r->user()), $id ? 200 : 201);
    }

    public function eliminarConcepto(Request $r, int $id)
    {
        return $this->s->eliminarConcepto($id, $r->user());
    }

    // ---- métodos de pago
    public function metodos(Request $r)
    {
        return $this->s->metodos($r->user());
    }

    public function crearMetodo(Request $r)
    {
        $d = $r->validate([
            'tipo' => 'required|in:TARJETA,QR,TRANSFERENCIA,BILLETERA',
            'alias' => 'required|string|min:2|max:60',
            'numero' => 'nullable|string|max:30', 'titular' => 'nullable|string|max:120', 'vencimiento' => 'nullable|string|max:5',
            'cvv' => 'nullable|string|max:4', 'banco' => 'nullable|string|max:60', 'cuenta' => 'nullable|string|max:30', 'celular' => 'nullable|string|max:12',
            'predeterminado' => 'nullable|boolean',
        ]);

        return response()->json($this->s->crearMetodo($r->user(), $d), 201);
    }

    public function eliminarMetodo(Request $r, int $id)
    {
        $this->s->eliminarMetodo($r->user(), $id);

        return ['message' => 'Método de pago eliminado.'];
    }

    public function predeterminar(Request $r, int $id)
    {
        return $this->s->predeterminar($r->user(), $id);
    }

    // ---- pagos
    public function pagar(Request $r)
    {
        $d = $r->validate([
            'id_concepto' => 'required|integer', 'id_metodo' => 'required|integer', 'cvv' => 'nullable|string|max:4', 'observacion' => 'nullable|string|max:200',
        ]);
        $pago = $this->s->pagar($r->user(), $d);

        return response()->json($pago, 201);
    }

    public function misPagos(Request $r)
    {
        return $this->s->misPagos($r->user());
    }

    public function todos(Request $r)
    {
        $r->validate(['estado' => 'nullable|in:APROBADO,RECHAZADO', 'desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d']);

        return $this->s->todos($r->only(['estado', 'concepto', 'q', 'desde', 'hasta']));
    }

    public function resumen()
    {
        return $this->s->resumen();
    }

    public function comprobante(Request $r, int $id)
    {
        return $this->s->comprobante($r->user(), $id, Permisos::puede($r->user(), 'pago.gestionar'));
    }

    // ---- planes de pago
    public function planes(Request $r)
    {
        return $this->s->misPlanes($r->user(), $r->boolean('todos') && Permisos::puede($r->user(), 'pago.gestionar'));
    }

    public function crearPlan(Request $r)
    {
        $d = $r->validate(['id_concepto' => 'required|integer', 'num_cuotas' => 'required|integer|between:2,12']);

        return response()->json($this->s->crearPlan($r->user(), $d), 201);
    }

    public function cancelarPlan(Request $r, int $id)
    {
        return $this->s->cancelarPlan($r->user(), $id);
    }

    public function pagarCuota(Request $r, int $id)
    {
        $d = $r->validate(['id_metodo' => 'required|integer', 'cvv' => 'nullable|string|max:4']);

        return response()->json($this->s->pagarCuota($r->user(), $id, $d), 201);
    }
}
