<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CronogramaRepository;

class CronogramaService
{
    private CronogramaRepository $repo;
    private DashboardService $periodos;

    public function __construct()
    {
        $this->repo = new CronogramaRepository();
        $this->periodos = new DashboardService();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function tablero(array $filtros): array
    {
        $periodo = $this->periodos->periodo($filtros);
        $procesoId = isset($filtros['proceso_id']) && $filtros['proceso_id'] !== null && $filtros['proceso_id'] !== ''
            ? (int)$filtros['proceso_id']
            : null;

        $filas = $this->repo->programadas($periodo, $procesoId);
        $procesos = $this->repo->procesos();
        $porMes = [];
        foreach ($filas as $fila) {
            $mes = (int)$fila['mes_programado'];
            $porMes[$mes][] = $this->item($fila, $periodo['anio']);
        }

        $meses = [];
        foreach ($periodo['meses'] as $mes) {
            $items = $porMes[$mes] ?? [];
            $meses[] = [
                'mes' => $mes,
                'nombre' => $this->nombreMes($mes),
                'total' => count($items),
                'items' => $items,
            ];
        }

        $total = 0;
        foreach ($meses as $bloque) {
            $total += $bloque['total'];
        }

        $procesoNombre = null;
        if ($procesoId !== null) {
            foreach ($procesos as $proceso) {
                if ($proceso['proceso_id'] === $procesoId) {
                    $procesoNombre = $proceso['nombre'];
                    break;
                }
            }
        }

        return [
            'periodo' => [
                'tipo' => $periodo['tipo'],
                'anio' => $periodo['anio'],
                'mes' => $periodo['mes'],
                'trimestre' => $periodo['trimestre'],
                'etiqueta' => $periodo['etiqueta'],
            ],
            'proceso_id' => $procesoId,
            'proceso_nombre' => $procesoNombre,
            'total' => $total,
            'estado_plan' => 'APROBADO',
            'procesos' => $procesos,
            'meses' => $meses,
        ];
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function item(array $fila, int $anio): array
    {
        $mes = (int)$fila['mes_programado'];
        $horas = $fila['duracion_estimada_horas'];
        $metodologia = $fila['metodologia'] ?? null;

        return [
            'plan_detalle_id' => (int)$fila['plan_detalle_id'],
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'codigo' => (string)$fila['codigo'],
            'tema' => (string)$fila['nombre'],
            'objetivo' => (string)$fila['objetivo'],
            'horas' => $horas !== null && $horas !== '' ? round((float)$horas, 2) : null,
            'metodologia' => is_string($metodologia) && $metodologia !== '' ? $metodologia : null,
            'mes' => $mes,
            'mes_nombre' => $this->nombreMes($mes),
            'cantidad_programada' => (int)$fila['cantidad_programada'],
            'anio' => $anio,
            'proceso_id' => $fila['proceso_id'] !== null ? (int)$fila['proceso_id'] : null,
            'proceso_nombre' => $fila['proceso_nombre'] !== null && $fila['proceso_nombre'] !== ''
                ? (string)$fila['proceso_nombre']
                : null,
        ];
    }

    private function nombreMes(int $mes): string
    {
        $nombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $nombres[$mes] ?? (string)$mes;
    }
}
