<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CapacitacionRepository;
use App\Repositories\MatrizRepository;

class MatrizService
{
    private const MENSAJE_DUPLICADO = 'La capacitación ya está asociada a este cargo, proceso y proyecto.';

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

    public function reglasMasiva(): array
    {
        return [
            'capacitacion_id' => 'required|integer',
            'cargo_ids_ext' => 'required|array',
            'area_id' => 'nullable|integer',
            'proceso_id' => 'nullable|integer',
            'ambito' => 'nullable|in:ADMINISTRACION,PROYECTO',
            'proyecto' => 'nullable|string|max:120',
            'periodicidad_id' => 'nullable|integer',
            'obligatoria' => 'nullable|integer|min:0|max:1',
        ];
    }

    /**
     * @param array{capacitacion_id?:?int, cargo_id_ext?:?int, proceso_id?:?int, proyecto?:?string, activa?:?int} $filtros
     */
    public function listar(int $pagina, int $porPagina, array $filtros): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $filas = $this->repo->listar($porPagina, $offset, $filtros);
        $nombresCargo = $this->personal->nombresCargosPorIds(array_column($filas, 'cargo_id_ext'));

        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->normalizar($fila, $nombresCargo);
        }

        return [
            'items' => $items,
            'total' => $this->repo->contar($filtros),
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

    public function aplicables(?int $cargoId, ?int $procesoId, ?string $proyecto): array
    {
        $filas = $this->repo->aplicables($cargoId, $procesoId, $proyecto);
        $nombresCargo = $this->personal->nombresCargosPorIds(array_column($filas, 'cargo_id_ext'));

        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->normalizar($fila, $nombresCargo);
        }

        return [
            'total' => count($items),
            'items' => $items,
        ];
    }

    public function crear(array $datos, int $usuarioId): array
    {
        $datos = $this->preparar($datos);
        $this->validarReferencias($datos, false);

        if ($this->repo->duplicado($datos)) {
            throw new HttpException(self::MENSAJE_DUPLICADO, 409);
        }

        $datos['creado_por_usuario_id_ext'] = $usuarioId;
        $id = $this->repo->crear($datos);

        return $this->ver($id);
    }

    /**
     * @return array{creadas:int, omitidas:int, items:list<array<string,mixed>>, omitidas_detalle:list<array<string,mixed>>}
     */
    public function asociarMasivo(array $entrada, int $usuarioId): array
    {
        $base = $this->preparar([
            'capacitacion_id' => $entrada['capacitacion_id'] ?? null,
            'area_id' => $entrada['area_id'] ?? null,
            'proceso_id' => $entrada['proceso_id'] ?? null,
            'ambito' => $entrada['ambito'] ?? null,
            'proyecto' => $entrada['proyecto'] ?? null,
            'periodicidad_id' => $entrada['periodicidad_id'] ?? null,
            'obligatoria' => $entrada['obligatoria'] ?? 1,
            'activa' => 1,
        ]);

        $this->validarReferencias($base, true);

        $cargos = $this->normalizarCargosMasivos($entrada['cargo_ids_ext'] ?? []);

        $creadasIds = [];
        $omitidas = [];

        $this->repo->transaccion(function () use ($base, $cargos, $usuarioId, &$creadasIds, &$omitidas): int {
            foreach ($cargos as $cargoId) {
                $fila = $base;
                $fila['cargo_id_ext'] = $cargoId;
                $fila['creado_por_usuario_id_ext'] = $usuarioId;

                if ($this->repo->duplicado($fila)) {
                    $omitidas[] = [
                        'cargo_id_ext' => $cargoId,
                        'motivo' => self::MENSAJE_DUPLICADO,
                    ];
                    continue;
                }

                $creadasIds[] = $this->repo->crear($fila);
            }

            return count($creadasIds);
        });

        $items = [];
        foreach ($creadasIds as $id) {
            $items[] = $this->ver($id);
        }

        return [
            'creadas' => count($items),
            'omitidas' => count($omitidas),
            'items' => $items,
            'omitidas_detalle' => $omitidas,
        ];
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

        $this->validarReferencias($combinado, false);

        if ($this->repo->duplicado($combinado, $id)) {
            throw new HttpException(self::MENSAJE_DUPLICADO, 409);
        }

        if ($datos !== []) {
            $this->repo->actualizar($id, $datos);
        }

        return $this->ver($id);
    }

    public function eliminar(int $id): string
    {
        $actual = $this->ver($id);

        if ((int)$actual['activa'] === 0 || $actual['activa'] === false) {
            return 'El registro ya está inactivo.';
        }

        $this->repo->inactivar($id);

        return 'El registro fue inactivado correctamente.';
    }

    public function mensajeMasivo(array $resultado): string
    {
        $creadas = (int)$resultado['creadas'];
        $omitidas = (int)$resultado['omitidas'];

        if ($creadas > 0 && $omitidas > 0) {
            return "{$creadas} asociaciones creadas, {$omitidas} omitida(s) porque ya existía(n).";
        }

        if ($creadas > 0) {
            return $creadas === 1 ? '1 asociación creada.' : "{$creadas} asociaciones creadas.";
        }

        if ($omitidas > 0) {
            return self::MENSAJE_DUPLICADO;
        }

        return 'No se crearon asociaciones.';
    }

    /**
     * @return list<int>
     */
    private function normalizarCargosMasivos(mixed $bruto): array
    {
        if (!is_array($bruto) || $bruto === []) {
            throw new HttpException('Debe seleccionar al menos un cargo.', 422);
        }

        $ids = [];
        foreach ($bruto as $valor) {
            $id = (int)$valor;
            if ($id <= 0) {
                throw new HttpException('El cargo no existe en el maestro de personal corporativo', 422);
            }
            $ids[$id] = $id;
        }

        $ids = array_values($ids);

        foreach ($ids as $id) {
            if (!$this->personal->cargoExiste($id)) {
                throw new HttpException('El cargo no existe en el maestro de personal corporativo', 422);
            }
        }

        return $ids;
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

        unset($datos['cargo_ids_ext']);

        return $datos;
    }

    private function validarReferencias(array $datos, bool $altaNueva): void
    {
        if (!empty($datos['capacitacion_id'])) {
            $cap = $this->capacitaciones->buscarPorId((int)$datos['capacitacion_id']);
            if ($cap === null) {
                throw new HttpException('La capacitación no existe', 422);
            }
            if ($altaNueva && strtoupper((string)($cap['estado'] ?? '')) !== 'ACTIVA') {
                throw new HttpException('Solo se pueden asociar capacitaciones activas.', 422);
            }
        }

        if (!empty($datos['cargo_id_ext']) && !$this->personal->cargoExiste((int)$datos['cargo_id_ext'])) {
            throw new HttpException('El cargo no existe en el maestro de personal corporativo', 422);
        }

        if (!empty($datos['area_id']) && !$this->capacitaciones->catalogoExiste('areas', 'area_id', (int)$datos['area_id'])) {
            throw new HttpException('El área seleccionada no existe', 422);
        }

        if (!empty($datos['proceso_id'])) {
            if (!$this->capacitaciones->catalogoExiste('procesos', 'proceso_id', (int)$datos['proceso_id'])) {
                throw new HttpException('El proceso seleccionado no existe', 422);
            }
            if ($altaNueva && !$this->capacitaciones->catalogoActivo('procesos', 'proceso_id', (int)$datos['proceso_id'])) {
                throw new HttpException('El proceso seleccionado está inactivo.', 422);
            }
        }

        if (!empty($datos['periodicidad_id'])) {
            if (!$this->capacitaciones->catalogoExiste('periodicidades', 'periodicidad_id', (int)$datos['periodicidad_id'])) {
                throw new HttpException('La periodicidad seleccionada no existe', 422);
            }
            if ($altaNueva && !$this->capacitaciones->catalogoActivo('periodicidades', 'periodicidad_id', (int)$datos['periodicidad_id'])) {
                throw new HttpException('La periodicidad seleccionada está inactiva.', 422);
            }
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
