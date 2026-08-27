<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\CatalogService;

class CatalogController extends Controller
{
    private CatalogService $service;

    public function __construct()
    {
        $this->service = new CatalogService();
    }

    public function tipos(Request $request): void
    {
        $this->success($this->service->tiposDisponibles(), 'Catalogos disponibles');
    }

    public function index(Request $request, string $tipo): void
    {
        $def = $this->service->definicion($tipo);

        $soloActivos = in_array($request->query('activos'), ['1', 'true'], true);
        $buscar = $request->query('buscar');

        $items = $this->service->listar($def, $soloActivos, $buscar !== null ? (string)$buscar : null);

        $this->success([
            'tipo' => $def['tipo'],
            'etiqueta' => $def['etiqueta'],
            'total' => count($items),
            'items' => $items,
        ]);
    }

    public function show(Request $request, string $tipo, string $id): void
    {
        $def = $this->service->definicion($tipo);

        $this->success($this->service->ver($def, (int)$id));
    }

    public function store(Request $request, string $tipo): void
    {
        $def = $this->service->definicion($tipo);
        $datos = $this->validate($request, $this->service->reglas($def));

        $this->created($this->service->crear($def, $datos));
    }

    public function update(Request $request, string $tipo, string $id): void
    {
        $def = $this->service->definicion($tipo);
        $datos = $this->validate($request, $this->service->reglas($def, true));

        $this->success($this->service->actualizar($def, (int)$id, $datos), 'Registro actualizado');
    }

    public function destroy(Request $request, string $tipo, string $id): void
    {
        $def = $this->service->definicion($tipo);

        $mensaje = $this->service->eliminar($def, (int)$id);

        $this->success(null, $mensaje);
    }
}
