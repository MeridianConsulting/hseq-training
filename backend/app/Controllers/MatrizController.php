<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;
use App\Services\MatrizService;

class MatrizController extends Controller
{
    private MatrizService $service;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new MatrizService();
        $this->auditoria = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $capRaw = $request->query('capacitacion_id');
        $cargoRaw = $request->query('cargo_id_ext');

        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            ($capRaw !== null && $capRaw !== '') ? (int)$capRaw : null,
            ($cargoRaw !== null && $cargoRaw !== '') ? (int)$cargoRaw : null
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }

    public function store(Request $request): void
    {
        $datos = $this->validate($request, $this->service->reglas());
        $creado = $this->service->crear($datos, $request->userId());

        $this->auditoria->dePeticion(
            $request,
            'crear',
            'matriz_aplicabilidad',
            (int)$creado['matriz_aplicabilidad_id'],
            $creado
        );

        $this->created($creado, 'Fila de matriz creada');
    }

    public function update(Request $request, string $id): void
    {
        $datos = $this->validate($request, $this->service->reglas(true));
        $actualizado = $this->service->actualizar((int)$id, $datos);

        $this->auditoria->dePeticion(
            $request,
            'actualizar',
            'matriz_aplicabilidad',
            (int)$id,
            $actualizado
        );

        $this->success($actualizado, 'Fila de matriz actualizada');
    }

    public function destroy(Request $request, string $id): void
    {
        $mensaje = $this->service->eliminar((int)$id);

        $this->auditoria->dePeticion(
            $request,
            'eliminar',
            'matriz_aplicabilidad',
            (int)$id,
            ['mensaje' => $mensaje]
        );

        $this->success(null, $mensaje);
    }
}
