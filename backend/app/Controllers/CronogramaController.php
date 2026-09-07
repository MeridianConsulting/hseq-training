<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Services\AuditoriaService;
use App\Services\CronogramaService;

class CronogramaController extends Controller
{
    private CronogramaService $servicio;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->servicio = new CronogramaService();
        $this->auditoria = new AuditoriaService();
    }

    public function show(Request $request): void
    {
        $query = $request->allQuery();
        $filtros = $this->validateArray([
            'tipo' => $query['tipo'] ?? 'mensual',
            'anio' => $this->enteroONulo($query['anio'] ?? date('Y')),
            'mes' => $this->enteroONulo($query['mes'] ?? null),
            'trimestre' => $this->enteroONulo($query['trimestre'] ?? null),
            'semestre' => $this->enteroONulo($query['semestre'] ?? null),
            'proceso_id' => $this->enteroONulo($query['proceso_id'] ?? null),
            'proyecto' => nullable_trimmed_string($query['proyecto'] ?? null),
            'buscar' => nullable_trimmed_string($query['buscar'] ?? null),
        ], [
            'tipo' => 'required|in:mensual,trimestral,semestral,anual',
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'nullable|integer|min:1|max:12',
            'trimestre' => 'nullable|integer|min:1|max:4',
            'semestre' => 'nullable|integer|min:1|max:2',
            'proceso_id' => 'nullable|integer|min:1',
            'proyecto' => 'nullable|string|max:120',
            'buscar' => 'nullable|string|max:120',
        ]);

        $this->success($this->servicio->tablero($filtros), 'Cronograma del programa');
    }

    public function ver(Request $request, string $detalleId): void
    {
        $this->success($this->servicio->ver((int)$detalleId), 'Detalle de la programación');
    }

    public function trabajadores(Request $request, string $detalleId): void
    {
        $this->success($this->servicio->trabajadores((int)$detalleId), 'Trabajadores programados');
    }

    public function reprogramar(Request $request, string $detalleId): void
    {
        $datos = $this->validate($request, [
            'fecha_programada' => 'required|string|max:10',
        ]);
        $id = (int)$detalleId;
        $item = $this->servicio->reprogramar($id, $datos);

        $this->auditoria->dePeticion(
            $request,
            'reprogramar',
            'plan_anual_detalle',
            $id,
            $datos
        );

        $this->success($item, 'Programación actualizada correctamente.');
    }

    public function cancelar(Request $request, string $detalleId): void
    {
        $id = (int)$detalleId;
        $item = $this->servicio->cancelar($id);

        $this->auditoria->dePeticion(
            $request,
            'cancelar',
            'plan_anual_detalle',
            $id,
            ['estado_programacion' => 'CANCELADA']
        );

        $this->success($item, 'La programación fue cancelada.');
    }

    public function iniciar(Request $request, string $detalleId): void
    {
        $id = (int)$detalleId;
        $item = $this->servicio->iniciar($id, $request->userId());

        $this->auditoria->dePeticion(
            $request,
            'iniciar',
            'plan_anual_detalle',
            $id,
            $item
        );

        $this->success($item, 'Capacitación iniciada correctamente.');
    }

    private function enteroONulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_numeric($valor) ? (int)$valor : null;
    }
}
