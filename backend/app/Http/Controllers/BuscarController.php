<?php

namespace App\Http\Controllers;

use App\Models\ConceptoPago;
use App\Models\Documento;
use App\Models\Mandato;
use App\Models\PuntoOrdenDia;
use App\Models\Resolucion;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Services\MenuService;
use App\Support\Permisos;
use Illuminate\Http\Request;

/**
 * Búsqueda global (barra del encabezado). Solo devuelve resultados de los módulos que el rol del usuario puede consultar.
 */
class BuscarController extends Controller
{
    public function __construct(private MenuService $menus) {}

    public function index(Request $r)
    {
        $d = $r->validate(['q' => 'required|string|min:2|max:60']);
        $u = $r->user()->load('rol');
        $q = trim($d['q']);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($q)).'%';
        $grupos = [];
        $agregar = function (string $titulo, array $items) use (&$grupos) {
            if ($items) {
                $grupos[] = ['grupo' => $titulo, 'items' => $items];
            }
        };

        // Páginas del propio menú (según permisos)
        $paginas = [];
        foreach ($this->menus->paraUsuario($u) as $m) {
            foreach ($m['ruta'] ? [$m] : $m['hijos'] as $x) {
                if ($x['ruta'] && str_contains(mb_strtolower($x['titulo']), mb_strtolower($q))) {
                    $paginas[] = ['titulo' => $x['titulo'], 'detalle' => 'Ir a la página', 'ruta' => $x['ruta']];
                }
            }
        }
        $agregar('Páginas', array_slice($paginas, 0, 5));

        if (Permisos::puede($u, 'usuarios.ver')) {
            $agregar('Usuarios', Usuario::with('rol')->where(fn ($w) => $w->where('ci', $q)
                ->orWhereRaw('LOWER(nombres) LIKE ?', [$like])->orWhereRaw('LOWER(apellidos) LIKE ?', [$like])->orWhereRaw('LOWER(correo) LIKE ?', [$like]))
                ->limit(5)->get()->map(fn ($x) => ['titulo' => $x->nombre_completo, 'detalle' => $x->nombreRol().' · CI '.$x->ci, 'ruta' => '/usuarios?q='.rawurlencode($x->ci)])->all());
        }
        if (Permisos::puede($u, 'miembros.ver')) {
            $agregar('Mandatos', Mandato::with('titular:id_usuario,nombres,apellidos')->where(fn ($w) => $w->whereRaw('LOWER(carrera) LIKE ?', [$like])
                ->orWhereHas('titular', fn ($t) => $t->whereRaw('LOWER(nombres) LIKE ?', [$like])->orWhereRaw('LOWER(apellidos) LIKE ?', [$like])))
                ->limit(5)->get()->map(fn ($x) => ['titulo' => 'Mandato · '.$x->carrera, 'detalle' => trim(($x->titular?->nombres ?? '').' '.($x->titular?->apellidos ?? '')).' · '.$x->estamento, 'ruta' => '/miembros'])->all());
        }
        if (Permisos::puede($u, 'sesion.ver')) {
            $ids = PuntoOrdenDia::whereRaw('LOWER(titulo) LIKE ?', [$like])->pluck('id_sesion');
            $agregar('Sesiones', Sesion::where(fn ($w) => $w->whereRaw('LOWER(lugar) LIKE ?', [$like])->orWhereIn('id_sesion', $ids)
                ->orWhere('id_sesion', ctype_digit($q) ? (int) $q : 0))->orderByDesc('fecha_hora')->limit(5)->get()
                ->map(fn ($x) => ['titulo' => "Sesión #{$x->id_sesion} · {$x->tipo}", 'detalle' => $x->fecha_hora->format('Y-m-d H:i').' · '.$x->estado, 'ruta' => '/sesiones?id='.$x->id_sesion])->all());
        }
        if (Permisos::puede($u, 'documento.ver')) {
            $agregar('Documentos', Documento::where(fn ($w) => $w->whereRaw('LOWER(titulo) LIKE ?', [$like])->orWhereRaw('LOWER(descripcion) LIKE ?', [$like]))
                ->orderByDesc('id_documento')->limit(5)->get()
                ->map(fn ($x) => ['titulo' => $x->titulo, 'detalle' => "Documento #{$x->id_documento} · {$x->estado}", 'ruta' => '/documentos?id='.$x->id_documento])->all());
        }
        if (Permisos::puede($u, 'resolucion.ver')) {
            $agregar('Resoluciones', Resolucion::with('documento:id_documento,titulo')->where(fn ($w) => $w->whereRaw('LOWER(numero) LIKE ?', [$like])
                ->orWhereHas('documento', fn ($d) => $d->whereRaw('LOWER(titulo) LIKE ?', [$like])))->limit(5)->get()
                ->map(fn ($x) => ['titulo' => $x->numero, 'detalle' => ($x->documento?->titulo ?? '').' · '.$x->estado, 'ruta' => '/resoluciones'])->all());
        }
        if (Permisos::puede($u, 'pago.propio')) {
            $agregar('Conceptos de pago', ConceptoPago::where('activo', true)->where(fn ($w) => $w->whereRaw('LOWER(nombre) LIKE ?', [$like])->orWhereRaw('LOWER(codigo) LIKE ?', [$like]))
                ->limit(5)->get()->map(fn ($x) => ['titulo' => $x->nombre, 'detalle' => 'Bs '.number_format((float) $x->monto, 2, ',', '.'), 'ruta' => '/pagos'])->all());
        }

        return ['q' => $q, 'total' => array_sum(array_map(fn ($g) => count($g['items']), $grupos)), 'grupos' => $grupos];
    }
}
