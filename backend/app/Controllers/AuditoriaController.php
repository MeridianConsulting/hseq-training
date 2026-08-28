<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;

class AuditoriaController extends Controller
{
    private AuditoriaService $service;

    public function __construct()
    {
        $this->service = new AuditoriaService();
    }

    public function index(Request $request): void
    {
        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            nullable_trimmed_string($request->query('entidad')),
            nullable_trimmed_string($request->query('accion'))
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }
}
