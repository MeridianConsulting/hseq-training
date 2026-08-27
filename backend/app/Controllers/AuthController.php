<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    public function login(Request $request): void
    {
        $datos = $this->validate($request, [
            'usuario' => 'required|string|min:3|max:100',
            'password' => 'required|string|min:1|max:255',
        ]);

        $resultado = $this->service->login(
            trim((string)$datos['usuario']),
            (string)$datos['password']
        );

        $this->success($resultado, 'Inicio de sesión exitoso');
    }

    public function me(Request $request): void
    {
        $sesion = $request->user() ?? [];
        $usuarioId = (int)($sesion['id'] ?? $sesion['usuario_id'] ?? 0);

        $this->success($this->service->perfil($usuarioId), 'Sesión activa');
    }

    public function logout(Request $request): void
    {
        $this->success(null, 'Sesión cerrada');
    }
}
