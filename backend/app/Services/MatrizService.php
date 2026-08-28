<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CapacitacionRepository;
use App\Repositories\MatrizRepository;
use PDOException;

class MatrizService
{
    private const SQLSTATE_INTEGRIDAD = '23000';

    private MatrizRepository $repo;
    private CapacitacionRepository $capacitaciones;
    private PersonalService $personal;

    public function __construct()
    {
        $this->repo = new MatrizRepository();
        $this->capacitaciones = new CapacitacionRepository();
        $this->personal = new PersonalService();
    }

    public function reglas(bool $esActualizacion = false): array
    {
        return [
            'capacitacion_id' => ($esActualizacion ? 'nullable' : 'required') . '|integer',
            'cargo_id_ext' => 'nullable|integer',
            'area_id' => 'nullable|integer',
            'proceso_id' => 'nullable|integer',
            'ambito' => 'nullable|in:ADMINISTRACION,PROYECTO',
            'proyecto' => 'nullable|string|max:120',
            'periodicidad_id' => 'nullable|integer',
            'obligatoria' => 'nullable|integer|min:0|max:1',
            'activa' => 'nullable|integer|min:0|max:1',
        ];
    }

    public function listar(int $pagina, int $porPagina, ?int $capacitacionId, ?int $cargoId): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $filas = $this->repo->listar($porPagina, $offset, $capacitacionId, $cargoId);
        $nombresCargo = $this->personal->nombresCargosPorIds(array_column($filas, 'cargo_id_ext'));

        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->normalizar($fila, $nombresCargo);
        }

        return [
            'items' => $items,
            'total' => $this->repo->contar($capacitacionId, $cargoId),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    public function ver(int $id): array
    {
        $fila = $this->repo->buscarPorId($id);

        if ($fila === null) {
            throw new HttpException('Registro de matriz no encontrado', 404);
        }

        $nombres = $this->personal->nombresCargosPorIds([$fila['cargo_id_ext'] ?? null]);

        return $this->normalizar($fila, $nombres);
    }

    public function crear(array $datos, int $usuarioId): array
    {
        $datos = $this->preparar($datos);
        $this->validarReferencias($datos);

        if ($this->repo->duplicado($datos)) {
            throw new HttpException(
                'Ya existe una fila de matriz con la misma capacitación, cargo, área, proceso, ámbito y proyecto',
                409
            );
        }

        $datos['creado_por_usuario_id_ext'] = $usuarioId;
        $id = $this->repo->crear($datos);

        return $this->ver($id);
    }

    public function actualizar(int $id, array $datos): array
    {
        $actual = $this->ver($id);
        $datos = $this->preparar($datos, true);

        $combinado = array_merge([
            'capacitacion_id' => $actual['capacitacion_id'],
            'cargo_id_ext' => $actual['cargo_id_ext'],
            'area_id' => $actual['area_id'],
            'proceso_id' => $actual['proceso_id'],
            'ambito' => $actual['ambito'],
            'proyecto' => $actual['proyecto'],
        ], $datos);

        $this->validarReferencias($combinado);

        if ($this->repo->duplicado($combinado, $id)) {
            throw new HttpException(
                'Ya existe una fila de matriz con la misma capacitación, cargo, área, proceso, ámbito y proyecto',
                409
            );
        }

        if ($datos !== []) {
            $this->repo->actualizar($id, $datos);
        }

        return $this->ver($id);
    }

    public function eliminar(int $id): string
    {
        $this->ver($id);

        try {
            $this->repo->eliminar($id);

            return 'Fila de matriz eliminada';
        } catch (PDOException $e) {
            if ($e->getCode() === self::SQLSTATE_INTEGRIDAD) {
                throw new HttpException(
                    'No se puede eliminar porque hay asignaciones históricas que la referencian',
                    409
                );
            }

            throw $e;
        }
    }

    private function preparar(array $datos, bool $parcial = false): array
    {
        $enteros = ['capacitacion_id', 'cargo_id_ext', 'area_id', 'proceso_id', 'periodicidad_id', 'obligatoria', 'activa'];

        foreach ($enteros as $campo) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }
            if ($datos[$campo] === null || $datos[$campo] === '') {
                $datos[$campo] = null;
            } else {
                $datos[$campo] = (int)$datos[$campo];
            }
        }

        if (array_key_exists('proyecto', $datos)) {
            $datos['proyecto'] = nullable_trimmed_string($datos['proyecto']);
        }

        if (array_key_exists('ambito', $datos) && $datos['ambito'] === '') {
            $datos['ambito'] = null;
        }

        if (!$parcial && !array_key_exists('obligatoria', $datos)) {
            $datos['obligatoria'] = 1;
        }

        if (!$parcial && !array_key_exists('activa', $datos)) {
            $datos['activa'] = 1;
        }

        return $datos;
    }

    private function validarReferencias(array $datos): void
    {
        if (!empty($datos['capacitacion_id']) && $this->capacitaciones->buscarPorId((int)$datos['capacitacion_id']) === null) {
            throw new HttpException('La capacitación no existe', 422);
        }

        if (!empty($datos['cargo_id_ext']) && !$this->personal->cargoExiste((int)$datos['cargo_id_ext'])) {
            throw new HttpException('El cargo no existe en el maestro de personal corporativo', 422);
        }

        if (!empty($datos['area_id']) && !$this->capacitaciones->catalogoExiste('areas', 'area_id', (int)$datos['area_id'])) {
            throw new HttpException('El área seleccionada no existe', 422);
        }

        if (!empty($datos['proceso_id']) && !$this->capacitaciones->catalogoExiste('procesos', 'proceso_id', (int)$datos['proceso_id'])) {
            throw new HttpException('El proceso seleccionado no existe', 422);
        }

        if (!empty($datos['periodicidad_id']) && !$this->capacitaciones->catalogoExiste('periodicidades', 'periodicidad_id', (int)$datos['periodicidad_id'])) {
            throw new HttpException('La periodicidad seleccionada no existe', 422);
        }
    }

    private function normalizar(array $fila, array $nombresCargo): array
    {
        $cargoId = $fila['cargo_id_ext'] !== null ? (int)$fila['cargo_id_ext'] : null;

        return [
            'matriz_aplicabilidad_id' => (int)$fila['matriz_aplicabilidad_id'],
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'capacitacion_codigo' => $fila['capacitacion_codigo'] ?? null,
            'capacitacion_nombre' => $fila['capacitacion_nombre'] ?? null,
            'cargo_id_ext' => $cargoId,
            'cargo_nombre' => $cargoId !== null ? ($nombresCargo[$cargoId] ?? null) : null,
            'area_id' => $fila['area_id'] !== null ? (int)$fila['area_id'] : null,
            'area_nombre' => $fila['area_nombre'] ?? null,
            'proceso_id' => $fila['proceso_id'] !== null ? (int)$fila['proceso_id'] : null,
            'proceso_nombre' => $fila['proceso_nombre'] ?? null,
            'ambito' => $fila['ambito'],
            'proyecto' => $fila['proyecto'],
            'periodicidad_id' => $fila['periodicidad_id'] !== null ? (int)$fila['periodicidad_id'] : null,
            'periodicidad_nombre' => $fila['periodicidad_nombre'] ?? null,
            'obligatoria' => (int)$fila['obligatoria'] === 1,
            'activa' => (int)$fila['activa'] === 1,
            'created_at' => $fila['created_at'] ?? null,
            'updated_at' => $fila['updated_at'] ?? null,
        ];
    }
}
