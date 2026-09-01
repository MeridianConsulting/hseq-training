<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AlertaService;

class AlertaController extends Controller
{
    private AlertaService $service;

    public function __construct()
    {
        $this->service = new AlertaService();
    }

    public function index(Request $request): void
    {
        $procesoRaw = $request->query('proceso_id');
        $cargoRaw = $request->query('cargo_id_ext');

        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 15),
            [
                'proceso_id' => ($procesoRaw !== null && $procesoRaw !== '') ? (int)$procesoRaw : null,
                'proyecto' => nullable_trimmed_string($request->query('proyecto')),
                'cargo_id_ext' => ($cargoRaw !== null && $cargoRaw !== '') ? (int)$cargoRaw : null,
            ]
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function opciones(Request $request): void
    {
        $this->success($this->service->opciones());
    }
}
