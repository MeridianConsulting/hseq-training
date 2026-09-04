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
        $capRaw = $request->query('capacitacion_id');

        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 15),
            [
                'proceso_id' => ($procesoRaw !== null && $procesoRaw !== '') ? (int)$procesoRaw : null,
                'proyecto' => nullable_trimmed_string($request->query('proyecto')),
                'cargo_id_ext' => ($cargoRaw !== null && $cargoRaw !== '') ? (int)$cargoRaw : null,
                'estado_alerta' => nullable_trimmed_string($request->query('estado_alerta')) ?? 'todas',
                'q' => nullable_trimmed_string($request->query('q')),
                'capacitacion_id' => ($capRaw !== null && $capRaw !== '') ? (int)$capRaw : null,
                'vencimiento_desde' => nullable_trimmed_string($request->query('vencimiento_desde')),
                'vencimiento_hasta' => nullable_trimmed_string($request->query('vencimiento_hasta')),
            ]
        );

        $this->success([
            'items' => $resultado['items'],
            'pagination' => [
                'total' => $resultado['total'],
                'per_page' => $resultado['per_page'],
                'current_page' => $resultado['page'],
                'last_page' => max(1, (int)ceil($resultado['total'] / max(1, $resultado['per_page']))),
            ],
            'resumen' => $resultado['resumen'],
        ]);
    }

    public function opciones(Request $request): void
    {
        $this->success($this->service->opciones());
    }
}
