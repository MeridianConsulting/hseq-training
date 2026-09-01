<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditoriaService;
use App\Services\MigracionService;

class MigracionController extends Controller
{
    private MigracionService $service;

    public function __construct()
    {
        $this->service = new MigracionService();
    }

    public function validar(Request $request): void
    {
        $archivo = $request->file('archivo');
        if ($archivo === null) {
            $this->error('Debe seleccionar un archivo.', 422);
            return;
        }

        $anio = (int)$request->input('anio_programa', date('Y'));
        $resultado = $this->service->validar($archivo, $anio, AuditoriaService::actorDe($request));

        $this->success($resultado, 'Archivo validado. Revise el resumen antes de confirmar.');
    }

    public function show(Request $request, string $id): void
    {
        $this->success($this->service->ver((int)$id));
    }

    public function inconsistencias(Request $request, string $id): void
    {
        $resultado = $this->service->inconsistencias(
            (int)$id,
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20)
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function reporte(Request $request, string $id): void
    {
        $archivo = $this->service->reporteExcel((int)$id);
        Response::download($archivo['contenido'], $archivo['nombre'], $archivo['mime']);
    }

    public function archivo(Request $request, string $id): void
    {
        $archivo = $this->service->archivoOrigen((int)$id);
        Response::download($archivo['contenido'], $archivo['nombre'], $archivo['mime']);
    }

    public function confirmar(Request $request, string $id): void
    {
        $resultado = $this->service->confirmar((int)$id, AuditoriaService::actorDe($request));
        $this->success($resultado, 'Migración confirmada.');
    }

    public function cancelar(Request $request, string $id): void
    {
        $resultado = $this->service->cancelar((int)$id);
        $this->success($resultado, 'Migración cancelada. No se importaron datos.');
    }
}
