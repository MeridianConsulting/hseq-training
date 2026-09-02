<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;
use App\Services\CatalogService;

class CatalogController extends Controller
{
    private CatalogService $service;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->service = new CatalogService();
        $this->auditoria = new AuditoriaService();
    }

    public function tipos(Request $request): void
    {
        $this->success($this->service->tiposDisponibles(), 'Catalogos disponibles');
    }

    public function index(Request $request, string $tipo): void
    {
        $def = $this->service->definicion($tipo);
        $buscar = $request->query('buscar');
        $filtro = $this->filtroEstado($request);
        $pageRaw = $request->query('page');
        $perRaw = $request->query('per_page');

        if (($pageRaw !== null && $pageRaw !== '') || ($perRaw !== null && $perRaw !== '')) {
            $resultado = $this->service->listarPaginado(
                $def,
                $filtro,
                $buscar !== null ? (string)$buscar : null,
                (int)($pageRaw ?: 1),
                (int)($perRaw ?: 20)
            );
            $this->success([
                'tipo' => $def['tipo'],
                'etiqueta' => $def['etiqueta'],
                'items' => $resultado['items'],
                'total' => $resultado['total'],
                'pagination' => [
                    'total' => $resultado['total'],
                    'per_page' => $resultado['per_page'],
                    'current_page' => $resultado['page'],
                    'last_page' => max(1, (int)ceil($resultado['total'] / max(1, $resultado['per_page']))),
                ],
            ]);

            return;
        }

        $items = $this->service->listar(
            $def,
            $filtro,
            $buscar !== null ? (string)$buscar : null
        );

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

        $creado = $this->service->crear($def, $datos);

        $this->auditoria->dePeticion(
            $request,
            'crear',
            $def['tabla'],
            (int)$creado[$def['pk']],
            $creado
        );

        $this->created($creado);
    }

    public function update(Request $request, string $tipo, string $id): void
    {
        $def = $this->service->definicion($tipo);
        $datos = $this->validate($request, $this->service->reglas($def, true));
        $anterior = $this->service->ver($def, (int)$id);
        $actualizado = $this->service->actualizar($def, (int)$id, $datos);

        $accion = 'actualizar';
        if (array_key_exists('activo', $datos)) {
            $antes = (int)($anterior['activo'] ?? 1);
            $despues = (int)($actualizado['activo'] ?? $datos['activo']);
            if ($antes === 1 && $despues === 0) {
                $accion = 'inactivar';
            } elseif ($antes === 0 && $despues === 1) {
                $accion = 'reactivar';
            }
        }

        $this->auditoria->dePeticion(
            $request,
            $accion,
            $def['tabla'],
            (int)$id,
            $actualizado,
            $anterior
        );

        $this->success($actualizado, $accion === 'reactivar' ? 'Registro reactivado' : ($accion === 'inactivar' ? 'El registro fue inactivado correctamente.' : 'Registro actualizado'));
    }

    public function destroy(Request $request, string $tipo, string $id): void
    {
        $def = $this->service->definicion($tipo);
        $mensaje = $this->service->eliminar($def, (int)$id);

        $this->auditoria->dePeticion(
            $request,
            'inactivar',
            $def['tabla'],
            (int)$id,
            ['mensaje' => $mensaje]
        );

        $this->success(null, $mensaje);
    }

    private function filtroEstado(Request $request): string
    {
        $estado = strtolower(trim((string)$request->query('estado', '')));

        if (in_array($estado, ['activos', 'inactivos', 'todos'], true)) {
            return $estado;
        }

        if (in_array($request->query('activos'), ['1', 'true'], true)) {
            return 'activos';
        }

        return 'todos';
    }
}
