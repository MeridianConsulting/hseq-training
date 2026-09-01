<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;
use App\Services\CapacitacionService;

class CapacitacionController extends Controller
{
    private CapacitacionService $service;

    public function __construct()
    {
        $this->service = new CapacitacionService();
    }

    public function index(Request $request): void
    {
        $categoriaRaw = $request->query('categoria_id');

        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            nullable_trimmed_string($request->query('buscar')),
            nullable_trimmed_string($request->query('estado')),
            ($categoriaRaw !== null && $categoriaRaw !== '') ? (int)$categoriaRaw : null
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }

    public function store(Request $request): void
    {
        $datos = $this->validate($request, $this->service->reglas(), $this->service->mensajes());
        $creado = $this->service->crear($datos, $request->userId(), AuditoriaService::actorDe($request));

        $this->created($creado, 'Capacitación creada');
    }

    public function update(Request $request, string $id): void
    {
        $datos = $this->validate($request, $this->service->reglas(true), $this->service->mensajes());
        $actualizado = $this->service->actualizar((int)$id, $datos, AuditoriaService::actorDe($request));

        $this->success($actualizado, 'Capacitación actualizada');
    }

    public function destroy(Request $request, string $id): void
    {
        $mensaje = $this->service->eliminar((int)$id, AuditoriaService::actorDe($request));

        $this->success(null, $mensaje);
    }
}
