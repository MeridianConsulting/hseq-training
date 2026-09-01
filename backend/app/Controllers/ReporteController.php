<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\ReporteService;

class ReporteController extends Controller
{
    private ReporteService $service;

    public function __construct()
    {
        $this->service = new ReporteService();
    }

    public function evidenciasFaltantes(Request $request): void
    {
        $resultado = $this->service->evidenciasFaltantes(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            ['buscar' => nullable_trimmed_string($request->query('buscar'))]
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }
}
