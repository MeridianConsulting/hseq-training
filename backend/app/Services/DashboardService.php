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
        $alcance = $this->alcance($filtros);

        $cobertura = [
            'general' => $this->kpiCobertura(
                $this->repo->programado($periodo, 'general', $alcance),
                $this->repo->ejecutado($periodo, 'general', $alcance)
            ),
            'induccion' => $this->kpiCobertura(
                $this->repo->programado($periodo, 'induccion', $alcance),
                $this->repo->ejecutado($periodo, 'induccion', $alcance)
            ),
            'tareas_criticas' => $this->kpiCobertura(
                $this->repo->programado($periodo, 'critica', $alcance),
                $this->repo->ejecutado($periodo, 'critica', $alcance)
            ),
        ];

        $eficacia = [
            'general' => $this->repo->eficacia($periodo, 'general', $alcance),
            'induccion' => $this->repo->eficacia($periodo, 'induccion', $alcance),
            'tareas_criticas' => $this->repo->eficacia($periodo, 'critica', $alcance),
        ];

        $horas = [
            'general' => $this->repo->horas($periodo, 'general', $alcance),
            'induccion' => $this->repo->horas($periodo, 'induccion', $alcance),
            'critica' => $this->repo->horas($periodo, 'critica', $alcance),
        ];

        return [
            'periodo' => [
                'tipo' => $periodo['tipo'],
                'anio' => $periodo['anio'],
                'mes' => $periodo['mes'],
                'trimestre' => $periodo['trimestre'],
                'semestre' => $periodo['semestre'],
                'desde' => $periodo['desde'],
                'hasta' => $periodo['hasta'],
                'etiqueta' => $periodo['etiqueta'],
            ],
            'alcance' => [
                'proceso' => $alcance['modo'] === 'proceso'
                    ? (string)$alcance['proceso_id']
                    : $alcance['modo'],
                'proyecto' => $alcance['proyecto'],
            ],
            'cobertura' => $cobertura,
            'eficacia' => $eficacia,
            'soportes' => $this->repo->soportes($periodo, $alcance),
            'horas' => $horas,
            // Compatibilidad con clientes que aún leen las claves anteriores.
            'cumplimiento_general' => $cobertura['general'],
            'cumplimiento_induccion' => $cobertura['induccion'],
            'cumplimiento_tareas_criticas' => $cobertura['tareas_criticas'],
            'poblacion' => (new PersonalRepository())->contarPorEstado(),
            'opciones' => [
                'procesos' => $this->repo->procesos(),
                'proyectos' => $this->repo->proyectos(),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{
     *   tipo:string,anio:int,mes:?int,trimestre:?int,semestre:?int,meses:list<int>,
     *   desde:string,hasta:string,etiqueta:string
     * }
     */
    public function periodo(array $filtros): array
    {
        $tipo = strtolower(trim((string)($filtros['tipo'] ?? 'mensual')));
        if (!in_array($tipo, ['mensual', 'trimestral', 'semestral', 'anual'], true)) {
            throw new HttpException('El período debe ser mensual, trimestral, semestral o anual', 422);
        }

        $anio = (int)($filtros['anio'] ?? date('Y'));
        if ($anio < 2000 || $anio > 2100) {
            throw new HttpException('El año no es válido', 422);
        }

        $mes = null;
        $trimestre = null;
        $semestre = null;
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
        } elseif ($tipo === 'semestral') {
            $semestre = (int)($filtros['semestre'] ?? ((int)date('n') <= 6 ? 1 : 2));
            if ($semestre < 1 || $semestre > 2) {
                throw new HttpException('El semestre debe ser 1 o 2', 422);
            }
            $inicioMes = $semestre === 1 ? 1 : 7;
            $meses = $semestre === 1
                ? [1, 2, 3, 4, 5, 6]
                : [7, 8, 9, 10, 11, 12];
            $desde = sprintf('%04d-%02d-01', $anio, $inicioMes);
            $hastaMes = $inicioMes + 5;
            $hasta = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $hastaMes)));
            $etiqueta = $this->nombreSemestre($semestre) . ' – ' . $anio;
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
            'semestre' => $semestre,
            'meses' => $meses,
            'desde' => $desde,
            'hasta' => $hasta,
            'etiqueta' => $etiqueta,
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{modo:string,proceso_id:?int,proyecto:?string}
     */
    public function alcance(array $filtros): array
    {
        $procesoRaw = trim((string)($filtros['proceso'] ?? 'todos'));
        if ($procesoRaw === '' || strcasecmp($procesoRaw, 'todos') === 0) {
            return ['modo' => 'todos', 'proceso_id' => null, 'proyecto' => null];
        }

        if (!ctype_digit($procesoRaw) && !is_numeric($procesoRaw)) {
            throw new HttpException('El proceso seleccionado no es válido', 422);
        }

        $procesoId = (int)$procesoRaw;
        if ($procesoId < 1) {
            throw new HttpException('El proceso seleccionado no es válido', 422);
        }

        $proyecto = null;
        if ($this->procesoPermiteFiltroProyecto($procesoId)) {
            $proyectoRaw = trim((string)($filtros['proyecto'] ?? ''));
            $proyecto = $proyectoRaw !== '' ? $proyectoRaw : null;
        }

        return ['modo' => 'proceso', 'proceso_id' => $procesoId, 'proyecto' => $proyecto];
    }

    private function procesoPermiteFiltroProyecto(int $procesoId): bool
    {
        foreach ($this->repo->procesos() as $proceso) {
            if ((int)$proceso['proceso_id'] !== $procesoId) {
                continue;
            }
            $nombre = mb_strtolower((string)$proceso['nombre'], 'UTF-8');
            $nombre = strtr($nombre, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            ]);

            return str_contains($nombre, 'gestion de proyectos');
        }

        return false;
    }

    /** @return array{programado:int,ejecutado:int,porcentaje:?float,sin_programado:bool} */
    private function kpiCobertura(int $programado, int $ejecutado): array
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

    private function nombreSemestre(int $semestre): string
    {
        return $semestre === 1 ? 'Primer semestre' : 'Segundo semestre';
    }
}
