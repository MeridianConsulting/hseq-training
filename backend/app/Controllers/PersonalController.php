<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\PersonalService;

class PersonalController extends Controller
{
    private PersonalService $service;

    public function __construct()
    {
        $this->service = new PersonalService();
    }

    public function index(Request $request): void
    {
        $pagina = (int)$request->query('page', 1);
        $porPagina = (int)$request->query('per_page', 20);
        $buscar = nullable_trimmed_string($request->query('buscar'));
        $estado = nullable_trimmed_string($request->query('estado'));
        $cargoRaw = $request->query('cargo_id');
        $cargoId = ($cargoRaw !== null && $cargoRaw !== '') ? (int)$cargoRaw : null;

        $resultado = $this->service->listar($pagina, $porPagina, $buscar, $estado, $cargoId);

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function cargos(Request $request): void
    {
        $this->success($this->service->cargos(), 'Cargos corporativos');
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }
}
