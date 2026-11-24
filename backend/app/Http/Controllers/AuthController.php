<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaNegocioException;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\BitacoraService;
use App\Support\Contexto;
use App\Support\Permisos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public const PREFERENCIAS_BASE = ['tema' => 'adultos', 'modo' => 'auto', 'fuente' => 100, 'contraste' => false];

    public function login(Request $request)
    {
        $d = $request->validate(['correo' => 'required|email|max:190', 'password' => 'required|string|max:200']);
        $clave = 'login:'.mb_strtolower($d['correo']).'|'.$request->ip();
        $ruta = 'POST api/auth/login';

        if (RateLimiter::tooManyAttempts($clave, 8)) {
            BitacoraService::registrar(BitacoraService::LOGIN_FALLIDO, $request, null, $d['correo'], $ruta, 429, 'Bloqueado temporalmente por demasiados intentos');
            throw new ReglaNegocioException('Demasiados intentos. Espere '.RateLimiter::availableIn($clave).' segundos.', 429);
        }

        $u = Usuario::with('rol')->whereRaw('LOWER(correo) = ?', [mb_strtolower($d['correo'])])->first();
        if (! $u || ! Hash::check($d['password'], $u->password)) {
            RateLimiter::hit($clave, 300);
            BitacoraService::registrar(BitacoraService::LOGIN_FALLIDO, $request, $u, $d['correo'], $ruta, 401, $u ? 'Contraseña incorrecta' : 'Usuario inexistente');
            throw new ReglaNegocioException('Correo o contraseña incorrectos.', 401);
        }
        if ($u->estado !== 'ACTIVO') {
            BitacoraService::registrar(BitacoraService::LOGIN_FALLIDO, $request, $u, $d['correo'], $ruta, 403, 'Usuario desactivado');
            throw ReglaNegocioException::prohibido('Su usuario está desactivado.');
        }

        RateLimiter::clear($clave);
        Contexto::web($request->ip());
        AuditoriaService::registrar($u, 'LOGIN', 'usuario', $u->id_usuario);
        BitacoraService::registrar(BitacoraService::LOGIN_OK, $request, $u, null, $ruta, 200);

        return ['token' => $u->createToken('spa-react')->plainTextToken, 'usuario' => $this->perfil($u)];
    }

    public function logout(Request $request)
    {
        BitacoraService::registrar(BitacoraService::LOGOUT, $request, $request->user(), null, 'POST api/auth/logout', 200);
        $request->user()->currentAccessToken()->delete();

        return ['message' => 'Sesión cerrada.'];
    }

    public function me(Request $request)
    {
        return $this->perfil($request->user()->load('rol'));
    }

    public function cambiarPassword(Request $request)
    {
        $d = $request->validate(['actual' => 'required|string', 'nueva' => 'required|string|min:8|max:100|different:actual']);
        $u = $request->user();
        if (! Hash::check($d['actual'], $u->password)) {
            throw ReglaNegocioException::invalida('La contraseña actual no coincide.');
        }
        $u->update(['password' => $d['nueva']]);
        AuditoriaService::registrar($u, 'PASSWORD_CAMBIADA', 'usuario', $u->id_usuario);

        return ['message' => 'Contraseña actualizada.'];
    }

    /** Guarda el tema, el modo día/noche, el tamaño de fuente y el contraste elegidos por el usuario. */
    public function preferencias(Request $request)
    {
        $d = $request->validate([
            'tema' => 'required|in:ninos,jovenes,adultos',
            'modo' => 'required|in:auto,dia,noche',
            'fuente' => 'required|integer|between:80,160',
            'contraste' => 'required|boolean',
        ]);
        $u = $request->user();
        $u->update(['preferencias' => $d]);

        return ['preferencias' => $d];
    }

    private function perfil(Usuario $u): array
    {
        return [
            'id_usuario' => $u->id_usuario, 'ci' => $u->ci, 'nombre' => $u->nombre_completo, 'correo' => $u->correo,
            'rol' => $u->nombreRol(), 'permisos' => Permisos::delUsuario($u),
            // null = el usuario aún no guardó preferencias: la aplicación conserva las que eligió en el navegador y las sincroniza.
            'preferencias' => $u->preferencias ? array_merge(self::PREFERENCIAS_BASE, $u->preferencias) : null,
        ];
    }
}
