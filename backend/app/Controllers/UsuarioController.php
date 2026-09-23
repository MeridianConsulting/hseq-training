<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;
use App\Services\UsuarioService;

class UsuarioController extends Controller
{
    private UsuarioService $service;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new UsuarioService();
        $this->auditoria = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            nullable_trimmed_string($request->query('buscar')),
            nullable_trimmed_string($request->query('estado'))
        );
        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id), 'Usuario');
    }

    public function store(Request $request): void
    {
        $datos = $this->validate($request, [
            'nombre_usuario' => 'required|string|max:50',
            'correo' => 'required|email|max:100',
            'password' => 'required|string|min:8|max:120',
            'estado' => 'nullable|string|in:Activo,Inactivo',
        ], [
            'nombre_usuario.required' => 'El nombre de usuario es obligatorio.',
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'El correo no es válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $creado = $this->service->crear($datos);
        $this->auditoria->deActor(
            AuditoriaService::actorDe($request),
            'crear',
            'usuarios',
            (int)$creado['usuario_id'],
            ['usuario' => $creado]
        );
        $this->created($creado, 'Usuario creado. Puede iniciar sesión con esas credenciales.');
    }

    public function update(Request $request, string $id): void
    {
        $datos = $this->validate($request, [
            'nombre_usuario' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:100',
            'password' => 'nullable|string|min:8|max:120',
            'estado' => 'nullable|string|in:Activo,Inactivo',
        ], [
            'correo.email' => 'El correo no es válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $actualizado = $this->service->actualizar((int)$id, $datos, $request->userId());
        $this->auditoria->deActor(
            AuditoriaService::actorDe($request),
            'actualizar',
            'usuarios',
            (int)$id,
            ['usuario' => $actualizado]
        );
        $this->success($actualizado, 'Usuario actualizado');
    }

    public function destroy(Request $request, string $id): void
    {
        $actualizado = $this->service->inactivar((int)$id, $request->userId());
        $this->auditoria->deActor(
            AuditoriaService::actorDe($request),
            'inactivar',
            'usuarios',
            (int)$id,
            ['usuario' => $actualizado]
        );
        $this->success($actualizado, 'Usuario inactivado');
    }
}
