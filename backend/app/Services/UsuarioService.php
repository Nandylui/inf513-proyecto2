<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\BuscaEntidades;
use App\Support\Permisos;
use Illuminate\Support\Collection;

/** CU1 - Gestión de Usuarios y roles (RF-01). */
class UsuarioService
{
    use BuscaEntidades;

    /** @return Collection<int, Usuario> */
    public function listar(?string $filtro = null): Collection
    {
        $q = Usuario::with('rol')->orderBy('apellidos')->orderBy('nombres');
        $filtro = trim((string) $filtro);

        if ($filtro !== '' && $filtro !== '*') {
            if (in_array(mb_strtoupper($filtro), Permisos::ROLES, true)) {
                $q->whereHas('rol', fn ($r) => $r->where('nombre', mb_strtoupper($filtro)));
            } else {
                $like = '%'.mb_strtolower($filtro).'%';
                $q->where(fn ($w) => $w
                    ->where('ci', trim($filtro))
                    ->orWhereRaw('LOWER(nombres) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(apellidos) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(correo) LIKE ?', [$like]));
            }
        }

        return $q->get();
    }

    /**
     * @param array{ci:string,nombres:string,apellidos:string,correo:string,rol:string,telefono?:?string,password?:?string} $d
     */
    public function crear(array $d, ?Usuario $actor = null): Usuario
    {
        $ci = $this->requerido($d['ci'] ?? '', 'CI');
        $nombres = $this->requerido($d['nombres'] ?? '', 'nombres');
        $apellidos = $this->requerido($d['apellidos'] ?? '', 'apellidos');
        $correo = mb_strtolower($this->requerido($d['correo'] ?? '', 'correo'));
        $rol = $this->rolPorNombre($d['rol'] ?? '');

        if (! preg_match('/^[0-9A-Za-z\-]{4,20}$/', $ci)) {
            throw ReglaNegocioException::invalida('El CI debe tener entre 4 y 20 caracteres alfanuméricos.');
        }
        if (! filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw ReglaNegocioException::invalida("El correo \"{$correo}\" no es válido.");
        }
        if (Usuario::where('ci', $ci)->exists()) {
            throw ReglaNegocioException::invalida("Ya existe un usuario con CI {$ci}.");
        }
        if (Usuario::whereRaw('LOWER(correo) = ?', [$correo])->exists()) {
            throw ReglaNegocioException::invalida("Ya existe un usuario con el correo {$correo}.");
        }

        // Clave inicial = CI cuando no se indica una (debe cambiarse en el primer ingreso).
        $usuario = Usuario::create([
            'ci' => $ci, 'nombres' => $nombres, 'apellidos' => $apellidos, 'correo' => $correo,
            'telefono' => $d['telefono'] ?? null,
            'password' => ($d['password'] ?? '') !== '' ? $d['password'] : $ci,
            'id_rol' => $rol->id_rol, 'estado' => 'ACTIVO',
        ]);

        AuditoriaService::registrar($actor, 'USUARIO_CREADO', 'usuario', $usuario->id_usuario, ['ci' => $ci, 'rol' => $rol->nombre]);

        return $usuario->load('rol');
    }

    public function modificar(string $ci, string $campo, string $valor, ?Usuario $actor = null): Usuario
    {
        $usuario = $this->usuarioPorCi($ci);
        $campo = mb_strtolower(trim($campo));
        $valor = trim($valor);

        switch ($campo) {
            case 'nombres':
            case 'apellidos':
            case 'telefono':
                $usuario->{$campo} = $this->requerido($valor, $campo);
                break;
            case 'correo':
                $valor = mb_strtolower($valor);
                if (! filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                    throw ReglaNegocioException::invalida("El correo \"{$valor}\" no es válido.");
                }
                if (Usuario::whereRaw('LOWER(correo) = ?', [$valor])->where('id_usuario', '!=', $usuario->id_usuario)->exists()) {
                    throw ReglaNegocioException::invalida("El correo {$valor} ya pertenece a otro usuario.");
                }
                $usuario->correo = $valor;
                break;
            case 'rol':
                $usuario->id_rol = $this->rolPorNombre($valor)->id_rol;
                break;
            case 'estado':
                $valor = mb_strtoupper($valor);
                if (! in_array($valor, ['ACTIVO', 'INACTIVO'], true)) {
                    throw ReglaNegocioException::invalida('El estado debe ser ACTIVO o INACTIVO.');
                }
                $usuario->estado = $valor;
                break;
            case 'password':
                if (mb_strlen($valor) < 6) {
                    throw ReglaNegocioException::invalida('La contraseña debe tener al menos 6 caracteres.');
                }
                $usuario->password = $valor;
                break;
            default:
                throw ReglaNegocioException::invalida("Campo \"{$campo}\" no modificable. Use: nombres, apellidos, correo, telefono, rol, estado, password.");
        }

        $usuario->save();
        AuditoriaService::registrar($actor, 'USUARIO_MODIFICADO', 'usuario', $usuario->id_usuario, ['campo' => $campo]);

        return $usuario->load('rol');
    }

    /** Baja lógica: el usuario se conserva por trazabilidad. */
    public function desactivar(string $ci, ?Usuario $actor = null): Usuario
    {
        $usuario = $this->usuarioPorCi($ci);
        if ($actor && $actor->id_usuario === $usuario->id_usuario) {
            throw ReglaNegocioException::invalida('No puede desactivar su propio usuario.');
        }
        $usuario->update(['estado' => 'INACTIVO']);
        $usuario->tokens()->delete();
        AuditoriaService::registrar($actor, 'USUARIO_DESACTIVADO', 'usuario', $usuario->id_usuario);

        return $usuario->load('rol');
    }

    public function roles(): Collection
    {
        return Rol::orderBy('id_rol')->get();
    }

    private function rolPorNombre(string $nombre): Rol
    {
        $nombre = mb_strtoupper($this->requerido($nombre, 'rol'));

        return Rol::where('nombre', $nombre)->first()
            ?? throw ReglaNegocioException::invalida('Rol inválido. Valores permitidos: '.implode(', ', Permisos::ROLES).'.');
    }
}
