<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Logger;
use App\Repositories\AlertaRepository;
use Throwable;

class AlertaService
{
    public const ESTADO = 'PROXIMA_A_VENCER';
    public const MENSAJE_CARGA = 'No fue posible cargar las alertas. Intente nuevamente.';

    private AlertaRepository $repo;

    public function __construct()
    {
        $this->repo = new AlertaRepository();
    }

    /**
     * @param array{proceso_id?:?int, proyecto?:?string, cargo_id_ext?:?int} $filtros
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
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
            ];
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Logger::error('Error al listar alertas: ' . $e->getMessage());
            throw new HttpException(self::MENSAJE_CARGA, 500);
        }
    }

    /**
     * @param array{proceso_id?:?int, proyecto?:?string, cargo_id_ext?:?int} $filtros
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
     * @return array{procesos:list<array<string,mixed>>,proyectos:list<string>,cargos:list<array<string,mixed>>}
     */
    public function opciones(): array
    {
        try {
            $procesos = [];
            foreach ($this->repo->procesosActivos() as $fila) {
                $procesos[] = [
                    'proceso_id' => (int)$fila['proceso_id'],
                    'nombre' => (string)$fila['nombre'],
                ];
            }

            $cargos = [];
            foreach ($this->repo->cargos() as $fila) {
                $cargos[] = [
                    'cargo_id' => (int)$fila['cargo_id'],
                    'nombre_cargo' => (string)$fila['nombre_cargo'],
                ];
            }

            return [
                'procesos' => $procesos,
                'proyectos' => $this->repo->proyectos(),
                'cargos' => $cargos,
            ];
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            Logger::error('Error al cargar opciones de alertas: ' . $e->getMessage());
            throw new HttpException(self::MENSAJE_CARGA, 500);
        }
    }

    /**
     * @param array{proceso_id?:?int, proyecto?:?string, cargo_id_ext?:?int} $filtros
     * @return array{proceso_id:?int, proyecto:?string, cargo_id_ext:?int}
     */
    private function normalizarFiltros(array $filtros): array
    {
        $procesoId = isset($filtros['proceso_id']) ? (int)$filtros['proceso_id'] : 0;
        $cargoId = isset($filtros['cargo_id_ext']) ? (int)$filtros['cargo_id_ext'] : 0;
        $proyecto = isset($filtros['proyecto']) ? trim((string)$filtros['proyecto']) : '';

        return [
            'proceso_id' => $procesoId > 0 ? $procesoId : null,
            'proyecto' => $proyecto !== '' ? $proyecto : null,
            'cargo_id_ext' => $cargoId > 0 ? $cargoId : null,
        ];
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizar(array $fila): array
    {
        $codigo = $fila['capacitacion_codigo'] ?? null;
        $nombre = $fila['capacitacion_nombre'] ?? null;

        return [
            'cumplimiento_id' => (int)$fila['cumplimiento_id'],
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
            'fecha_vencimiento' => $fila['fecha_vencimiento'] ?? null,
            'dias_restantes' => (int)($fila['dias_restantes'] ?? 0),
            'estado' => self::ESTADO,
        ];
    }
}
