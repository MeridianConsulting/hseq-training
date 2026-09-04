<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Logger;
use App\Repositories\AlertaRepository;
use Throwable;

class AlertaService
{
    public const MENSAJE_CARGA = 'No fue posible cargar las alertas. Intente nuevamente.';

    private AlertaRepository $repo;

    public function __construct()
    {
        $this->repo = new AlertaRepository();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{
     *   items:list<array<string,mixed>>,
     *   total:int,
     *   page:int,
     *   per_page:int,
     *   resumen:array{vencidas:int,proximas_30:int}
     * }
     */
    public function listar(int $pagina, int $porPagina, array $filtros): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;
        $limpios = $this->normalizarFiltros($filtros);

        try {
            $filas = $this->repo->listar($porPagina, $offset, $limpios);
            $items = [];
            foreach ($filas as $fila) {
                $items[] = $this->normalizar($fila);
            }

            return [
                'items' => $items,
                'total' => $this->repo->contar($limpios),
                'page' => $pagina,
                'per_page' => $porPagina,
                'resumen' => $this->repo->resumen($limpios),
            ];
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Logger::error('Error al listar alertas: ' . $e->getMessage());
            throw new HttpException(self::MENSAJE_CARGA, 500);
        }
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{items:list<array<string,mixed>>,total:int}
     */
    public function listarTodos(array $filtros): array
    {
        $limpios = $this->normalizarFiltros($filtros);
        $total = $this->repo->contar($limpios);
        $filas = $total === 0 ? [] : $this->repo->listar(max(1, $total), 0, $limpios);
        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->normalizar($fila);
        }

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{
     *   procesos:list<array<string,mixed>>,
     *   proyectos:list<string>,
     *   cargos:list<array<string,mixed>>,
     *   capacitaciones:list<array<string,mixed>>
     * }
     */
    public function opciones(): array
    {
        try {
            $cargos = [];
            foreach ($this->repo->cargos() as $fila) {
                $cargos[] = [
                    'cargo_id' => (int)$fila['cargo_id'],
                    'nombre_cargo' => (string)$fila['nombre_cargo'],
                ];
            }

            return [
                'procesos' => $this->repo->procesosActivos(),
                'proyectos' => $this->repo->proyectos(),
                'cargos' => $cargos,
                'capacitaciones' => $this->repo->capacitacionesEnAlertas(),
            ];
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Logger::error('Error al cargar opciones de alertas: ' . $e->getMessage());
            throw new HttpException(self::MENSAJE_CARGA, 500);
        }
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    private function normalizarFiltros(array $filtros): array
    {
        $procesoId = isset($filtros['proceso_id']) ? (int)$filtros['proceso_id'] : 0;
        $cargoId = isset($filtros['cargo_id_ext']) ? (int)$filtros['cargo_id_ext'] : 0;
        $capId = isset($filtros['capacitacion_id']) ? (int)$filtros['capacitacion_id'] : 0;
        $proyecto = isset($filtros['proyecto']) ? trim((string)$filtros['proyecto']) : '';
        $estado = strtolower(trim((string)($filtros['estado_alerta'] ?? 'todas')));
        if (!in_array($estado, ['todas', 'proximas', 'vencidas'], true)) {
            $estado = 'todas';
        }
        $q = isset($filtros['q']) ? trim((string)$filtros['q']) : '';
        $desde = isset($filtros['vencimiento_desde']) ? trim((string)$filtros['vencimiento_desde']) : '';
        $hasta = isset($filtros['vencimiento_hasta']) ? trim((string)$filtros['vencimiento_hasta']) : '';

        $procesoFinal = $procesoId > 0 ? $procesoId : null;
        $proyectoFinal = null;
        if ($proyecto !== '' && $this->repo->procesoEsGestionProyectos($procesoFinal)) {
            $proyectoFinal = $proyecto;
        }

        return [
            'proceso_id' => $procesoFinal,
            'proyecto' => $proyectoFinal,
            'cargo_id_ext' => $cargoId > 0 ? $cargoId : null,
            'estado_alerta' => $estado,
            'q' => $q !== '' ? $q : null,
            'capacitacion_id' => $capId > 0 ? $capId : null,
            'vencimiento_desde' => $this->fechaONula($desde),
            'vencimiento_hasta' => $this->fechaONula($hasta),
        ];
    }

    private function fechaONula(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            throw new HttpException('La fecha de vencimiento no es válida', 422);
        }

        return $valor;
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizar(array $fila): array
    {
        $codigo = $fila['capacitacion_codigo'] ?? null;
        $nombre = $fila['capacitacion_nombre'] ?? null;
        $estado = (string)($fila['estado_calculado'] ?? $fila['tipo_alerta'] ?? 'PROXIMA_A_VENCER');
        $soportes = (int)($fila['soportes_count'] ?? 0);
        $requiereSoporte = (int)($fila['capacitacion_certificado'] ?? 0) === 1;

        return [
            'cumplimiento_id' => isset($fila['cumplimiento_id']) && $fila['cumplimiento_id'] !== null
                ? (int)$fila['cumplimiento_id']
                : null,
            'asignacion_id' => (int)$fila['asignacion_id'],
            'persona_id_ext' => isset($fila['persona_id_ext']) ? (int)$fila['persona_id_ext'] : null,
            'trabajador' => $fila['persona_nombre'] ?? null,
            'documento' => $fila['numero_documento'] ?? null,
            'cargo' => $fila['nombre_cargo'] ?? null,
            'cargo_id_ext' => isset($fila['cargo_id_ext']) && $fila['cargo_id_ext'] !== null
                ? (int)$fila['cargo_id_ext']
                : null,
            'proceso' => $fila['proceso_nombre'] ?? null,
            'proceso_id' => isset($fila['proceso_id']) && $fila['proceso_id'] !== null
                ? (int)$fila['proceso_id']
                : null,
            'proyecto' => $fila['proyecto'] ?? null,
            'capacitacion_id' => isset($fila['capacitacion_id']) ? (int)$fila['capacitacion_id'] : null,
            'capacitacion_codigo' => $codigo,
            'capacitacion_nombre' => $nombre,
            'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
            'fecha_vencimiento' => $fila['fecha_vencimiento'] ?? $fila['fecha_limite_cumplimiento'] ?? null,
            'dias_restantes' => (int)($fila['dias_restantes'] ?? 0),
            'estado' => $estado,
            'tipo_alerta' => $fila['tipo_alerta'] ?? null,
            'nota_evaluacion' => isset($fila['nota_evaluacion']) && $fila['nota_evaluacion'] !== null
                ? (float)$fila['nota_evaluacion']
                : null,
            'resultado' => $fila['cumplimiento_resultado'] ?? null,
            'requiere_soporte' => $requiereSoporte,
            'soportes_count' => $soportes,
            'tiene_soporte' => $soportes > 0,
        ];
    }
}
