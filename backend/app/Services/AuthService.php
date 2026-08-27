<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\AuthenticationException;
use App\Repositories\AuthRepository;
use Firebase\JWT\JWT;

class AuthService
{
    private AuthRepository $repo;

    public function __construct()
    {
        $this->repo = new AuthRepository();
    }

    public function login(string $identificador, string $password): array
    {
        $usuario = $this->repo->buscarPorIdentificador($identificador);

        if ($usuario === null) {
            throw new AuthenticationException('Credenciales inválidas');
        }

        if ($this->estaBloqueado($usuario)) {
            throw new AuthenticationException('La cuenta está temporalmente bloqueada. Intente más tarde.');
        }

        if (($usuario['estado'] ?? '') !== 'Activo') {
            throw new AuthenticationException('El usuario está inactivo');
        }

        if (!password_verify($password, (string)$usuario['password_hash'])) {
            $this->registrarFallo($usuario);
            throw new AuthenticationException('Credenciales inválidas');
        }

        $this->repo->registrarAccesoExitoso((int)$usuario['usuario_id']);

        $publico = $this->aUsuarioPublico($usuario);

        return [
            'token' => $this->emitirToken($publico),
            'token_type' => 'Bearer',
            'expires_in' => (int)config('auth.jwt_expiration', 3600),
            'usuario' => $publico,
        ];
    }

    public function perfil(int $usuarioId): array
    {
        $usuario = $this->repo->buscarPorId($usuarioId);

        if ($usuario === null || ($usuario['estado'] ?? '') !== 'Activo') {
            throw new AuthenticationException('Sesión inválida');
        }

        if ($this->estaBloqueado($usuario)) {
            throw new AuthenticationException('La cuenta está temporalmente bloqueada. Intente más tarde.');
        }

        return $this->aUsuarioPublico($usuario);
    }

    private function emitirToken(array $usuario): string
    {
        $ahora = time();
        $expiracion = (int)config('auth.jwt_expiration', 3600);
        $secreto = (string)config('auth.jwt_secret', '');
        $algoritmo = (string)config('auth.jwt_algorithm', 'HS256');

        if ($secreto === '') {
            throw new AuthenticationException('Configuración de autenticación incompleta');
        }

        $payload = [
            'iss' => (string)config('app.url', ''),
            'iat' => $ahora,
            'exp' => $ahora + $expiracion,
            'data' => [
                'id' => $usuario['usuario_id'],
                'usuario_id' => $usuario['usuario_id'],
                'nombre_usuario' => $usuario['nombre_usuario'],
                'nombre_completo' => $usuario['nombre_completo'],
                'correo' => $usuario['correo'],
                'rol' => $usuario['rol'],
                'roles' => $usuario['roles'],
            ],
        ];

        return JWT::encode($payload, $secreto, $algoritmo);
    }

    private function aUsuarioPublico(array $usuario): array
    {
        $usuarioId = (int)$usuario['usuario_id'];

        return [
            'usuario_id' => $usuarioId,
            'nombre_usuario' => $usuario['nombre_usuario'],
            'nombre_completo' => $usuario['nombre_completo'],
            'correo' => $usuario['correo'],
            'rol' => $usuario['rol'],
            'estado' => $usuario['estado'],
            'ultimo_acceso' => $usuario['ultimo_acceso'] ?? null,
            'roles' => $this->repo->rolesHseq($usuarioId),
        ];
    }

    private function estaBloqueado(array $usuario): bool
    {
        $hasta = $usuario['bloqueado_hasta'] ?? null;

        if ($hasta === null || $hasta === '') {
            return false;
        }

        return strtotime((string)$hasta) > time();
    }

    private function registrarFallo(array $usuario): void
    {
        $intentos = (int)$usuario['intentos_fallidos'] + 1;
        $max = (int)config('auth.max_intentos', 5);
        $minutos = (int)config('auth.bloqueo_minutos', 15);
        $bloqueadoHasta = $intentos >= $max
            ? date('Y-m-d H:i:s', time() + ($minutos * 60))
            : null;

        $this->repo->registrarIntentoFallido(
            (int)$usuario['usuario_id'],
            $intentos,
            $bloqueadoHasta
        );
    }
}
