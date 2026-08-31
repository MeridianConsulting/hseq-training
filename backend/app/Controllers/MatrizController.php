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
        $resultado = $this->service->listar(
            (int)$request->query('page', 1),
            (int)$request->query('per_page', 20),
            $this->filtrosListado($request)
        );

        $this->paginate($resultado['items'], $resultado['total'], $resultado['page'], $resultado['per_page']);
    }

    public function aplicables(Request $request): void
    {
        $cargoRaw = $request->query('cargo_id');
        $procesoRaw = $request->query('proceso_id');
        $proyecto = nullable_trimmed_string($request->query('proyecto'));

        $resultado = $this->service->aplicables(
            ($cargoRaw !== null && $cargoRaw !== '') ? (int)$cargoRaw : null,
            ($procesoRaw !== null && $procesoRaw !== '') ? (int)$procesoRaw : null,
            $proyecto
        );

        $this->success($resultado, 'Reglas activas de aplicabilidad');
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

    public function asociarMasivo(Request $request): void
    {
        $datos = $this->validate($request, $this->service->reglasMasiva());
        $resultado = $this->service->asociarMasivo($datos, $request->userId());
        $mensaje = $this->service->mensajeMasivo($resultado);

        $this->auditoria->dePeticion(
            $request,
            'asociar_masivo',
            'matriz_aplicabilidad',
            null,
            $resultado
        );

        $this->success($resultado, $mensaje, $resultado['creadas'] > 0 ? 201 : 200);
    }

    public function update(Request $request, string $id): void
    {
        $datos = $this->validate($request, $this->service->reglas(true));
        $anterior = $this->service->ver((int)$id);
        $actualizado = $this->service->actualizar((int)$id, $datos);

        $accion = 'actualizar';
        if (array_key_exists('activa', $datos)) {
            $antes = $anterior['activa'] ? 1 : 0;
            $despues = $actualizado['activa'] ? 1 : 0;
            if ($antes === 1 && $despues === 0) {
                $accion = 'inactivar';
            } elseif ($antes === 0 && $despues === 1) {
                $accion = 'reactivar';
            }
        }

        $this->auditoria->dePeticion(
            $request,
            $accion,
            'matriz_aplicabilidad',
            (int)$id,
            $actualizado,
            $anterior
        );

        $this->success(
            $actualizado,
            $accion === 'reactivar'
                ? 'Registro reactivado'
                : ($accion === 'inactivar' ? 'El registro fue inactivado correctamente.' : 'Fila de matriz actualizada')
        );
    }

    public function destroy(Request $request, string $id): void
    {
        $mensaje = $this->service->eliminar((int)$id);

        $this->auditoria->dePeticion(
            $request,
            'inactivar',
            'matriz_aplicabilidad',
            (int)$id,
            ['mensaje' => $mensaje]
        );

        $this->success(null, $mensaje);
    }

    /**
     * @return array{capacitacion_id:?int, cargo_id_ext:?int, proceso_id:?int, proyecto:?string, activa:?int}
     */
    private function filtrosListado(Request $request): array
    {
        $capRaw = $request->query('capacitacion_id');
        $cargoRaw = $request->query('cargo_id_ext');
        $procesoRaw = $request->query('proceso_id');
        $activaRaw = $request->query('activa');
        $estado = strtolower(trim((string)$request->query('estado', '')));

        $activa = null;
        if ($estado === 'activas') {
            $activa = 1;
        } elseif ($estado === 'inactivas') {
            $activa = 0;
        } elseif ($activaRaw === '1' || $activaRaw === '0') {
            $activa = (int)$activaRaw;
        }

        return [
            'capacitacion_id' => ($capRaw !== null && $capRaw !== '') ? (int)$capRaw : null,
            'cargo_id_ext' => ($cargoRaw !== null && $cargoRaw !== '') ? (int)$cargoRaw : null,
            'proceso_id' => ($procesoRaw !== null && $procesoRaw !== '') ? (int)$procesoRaw : null,
            'proyecto' => nullable_trimmed_string($request->query('proyecto')),
            'activa' => $activa,
        ];
    }
}
