<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\DashboardRepository;
use App\Repositories\PersonalRepository;

class DashboardService
{
    private DashboardRepository $repo;

    public function __construct()
    {
        $this->repo = new DashboardRepository();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function indicadores(array $filtros): array
    {
        $periodo = $this->periodo($filtros);

        $general = $this->kpi(
            $this->repo->programado($periodo, 'general'),
            $this->repo->ejecutado($periodo, 'general')
        );
        $induccion = $this->kpi(
            $this->repo->programado($periodo, 'induccion'),
            $this->repo->ejecutado($periodo, 'induccion')
        );
        $critica = $this->kpi(
            $this->repo->programado($periodo, 'critica'),
            $this->repo->ejecutado($periodo, 'critica')
        );

        return [
            'periodo' => [
                'tipo' => $periodo['tipo'],
                'anio' => $periodo['anio'],
                'mes' => $periodo['mes'],
                'trimestre' => $periodo['trimestre'],
                'desde' => $periodo['desde'],
                'hasta' => $periodo['hasta'],
                'etiqueta' => $periodo['etiqueta'],
            ],
            'cumplimiento_general' => $general,
            'cumplimiento_induccion' => $induccion,
            'cumplimiento_tareas_criticas' => $critica,
            'eficacia_por_tema' => $this->repo->eficaciaPorTema($periodo),
            'horas_por_tema' => $this->repo->horasPorTema($periodo),
            'poblacion' => (new PersonalRepository())->contarPorEstado(),
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{
     *   tipo:string,anio:int,mes:?int,trimestre:?int,meses:list<int>,
     *   desde:string,hasta:string,etiqueta:string
     * }
     */
    public function periodo(array $filtros): array
    {
        $tipo = strtolower(trim((string)($filtros['tipo'] ?? 'mensual')));
        if (!in_array($tipo, ['mensual', 'trimestral', 'anual'], true)) {
            throw new HttpException('El período debe ser mensual, trimestral o anual', 422);
        }

        $anio = (int)($filtros['anio'] ?? date('Y'));
        if ($anio < 2000 || $anio > 2100) {
            throw new HttpException('El año no es válido', 422);
        }

        $mes = null;
        $trimestre = null;
        $meses = [];

        if ($tipo === 'mensual') {
            $mes = (int)($filtros['mes'] ?? date('n'));
            if ($mes < 1 || $mes > 12) {
                throw new HttpException('El mes debe estar entre 1 y 12', 422);
            }
            $meses = [$mes];
            $desde = sprintf('%04d-%02d-01', $anio, $mes);
            $hasta = date('Y-m-t', strtotime($desde));
            $etiqueta = $this->nombreMes($mes) . ' ' . $anio;
        } elseif ($tipo === 'trimestral') {
            $trimestre = (int)($filtros['trimestre'] ?? (int)ceil((int)date('n') / 3));
            if ($trimestre < 1 || $trimestre > 4) {
                throw new HttpException('El trimestre debe estar entre 1 y 4', 422);
            }
            $inicioMes = ($trimestre - 1) * 3 + 1;
            $meses = [$inicioMes, $inicioMes + 1, $inicioMes + 2];
            $desde = sprintf('%04d-%02d-01', $anio, $inicioMes);
            $hastaMes = $inicioMes + 2;
            $hasta = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $hastaMes)));
            $etiqueta = $this->nombreTrimestre($trimestre) . ' – ' . $anio;
        } else {
            $meses = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
            $desde = sprintf('%04d-01-01', $anio);
            $hasta = sprintf('%04d-12-31', $anio);
            $etiqueta = (string)$anio;
        }

        return [
            'tipo' => $tipo,
            'anio' => $anio,
            'mes' => $mes,
            'trimestre' => $trimestre,
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'etiqueta' => $etiqueta,
        ];
    }

    /** @return array{programado:int,ejecutado:int,porcentaje:?float,sin_programado:bool} */
    private function kpi(int $programado, int $ejecutado): array
    {
        return [
            'programado' => $programado,
            'ejecutado' => $ejecutado,
            'porcentaje' => $programado > 0 ? round($ejecutado / $programado * 100, 1) : null,
            'sin_programado' => $programado === 0,
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

    private function nombreTrimestre(int $trimestre): string
    {
        return match ($trimestre) {
            1 => 'Primer trimestre',
            2 => 'Segundo trimestre',
            3 => 'Tercer trimestre',
            default => 'Cuarto trimestre',
        };
    }
}
