<?php

namespace App\Http\Controllers;

use App\Services\DocumentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    public function __construct(private DocumentoService $s) {}

    public function index(Request $r)
    {
        return $this->s->listar($r->only(['sesion', 'estado', 'tipo', 'q']));
    }

    public function show(int $id)
    {
        return $this->s->obtener($id);
    }

    /** multipart/form-data con archivo opcional (PDF/Word/imagen, máx. 10 MB). */
    public function store(Request $r)
    {
        $d = $r->validate(['titulo' => 'required|string|max:255', 'id_sesion' => 'required|integer', 'tipo' => 'nullable|string', 'punto' => 'nullable|integer', 'prioridad' => 'nullable|integer|between:1,5', 'descripcion' => 'nullable|string', 'archivo' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png']);

        return response()->json($this->s->registrar($d, $r->user(), $r->file('archivo')), 201);
    }

    public function version(Request $r, int $id)
    {
        $d = $r->validate(['comentario' => 'nullable|string', 'archivo' => 'nullable|file|max:10240|mimes:pdf,doc,docx,jpg,jpeg,png']);

        return $this->s->subirVersion($id, $r->user(), $d['comentario'] ?? null, $r->file('archivo'));
    }

    public function validar(Request $r, int $id)
    {
        $d = $r->validate(['decision' => 'required|string', 'observaciones' => 'nullable|string']);

        return $this->s->validar($id, $r->user(), $d['decision'], $d['observaciones'] ?? null);
    }

    public function trazabilidad(int $id)
    {
        return $this->s->trazabilidad($id);
    }

    public function descargar(Request $r, int $id)
    {
        $f = $this->s->rutaArchivo($id, $r->query('version'));

        return Storage::download($f['path'], $f['nombre']);
    }

    public function tipos()
    {
        return $this->s->tipos();
    }

    public function configurarTipo(Request $r, int $id)
    {
        $d = $r->validate(['roles' => 'required|array|min:1', 'roles.*' => 'string', 'mayoria' => 'nullable|string']);

        return $this->s->configurarTipo($id, $d['roles'], $d['mayoria'] ?? null, $r->user());
    }
}
