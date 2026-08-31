<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\AsignacionRepository;
use App\Repositories\CapacitacionRepository;

class AsignacionService
{
    private AsignacionRepository $repo;
    private CapacitacionRepository $capacitaciones;
    private PersonalService $personal;

    public function __construct()
    {
        $this->repo = new AsignacionRepository();
        $this->capacitaciones = new CapacitacionRepository();
        $this->personal = new PersonalService();
    }

    public function reglas(bool $esActualizacion = false): array
    {
        if ($esActualizacion) {
            return [
                'fecha_limite_cumplimiento' => 'required|date',
            ];
        }

        return [
            'persona_id_ext' => 'required|integer|min:1',
            'capacitacion_id' => 'required|integer|min:1',
            'fecha_limite_cumplimiento' => 'required|date',
            'fecha_asignacion' => 'nullable|date',
        ];
    }

    public function reglasMasiva(): array
    {
        return [
            'persona_ids_ext' => 'required|array',
            'capacitacion_id' => 'required|integer|min:1',
            'fecha_limite_cumplimiento' => 'nullable|date',
        ];
    }

    public function listar(
        int $pagina,
        int $porPagina,
        ?int $personaId,
        ?int $capacitacionId,
        ?string $estado,
        ?string $alerta,
        ?string $buscar,
        ?string $origen = null
    ): array {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        if ($estado !== null && $estado !== '' && !in_array($estado, VencimientoService::ESTADOS, true)) {
            throw new HttpException('El estado calculado no es válido', 422);
        }

        if ($alerta !== null && $alerta !== '' && !in_array($alerta, ['proximas', 'vencidas'], true)) {
            throw new HttpException('El filtro de alerta debe ser proximas o vencidas', 422);
        }

        if ($origen !== null && $origen !== '' && !in_array($origen, ['AUTOMATICA', 'MANUAL'], true)) {
            throw new HttpException('El origen debe ser AUTOMATICA o MANUAL', 422);
        }

        $filas = $this->repo->listar($porPagina, $offset, $personaId, $capacitacionId, $estado, $alerta, $buscar, $origen);

        return [
            'items' => array_map([$this, 'normalizar'], $filas),
            'total' => $this->repo->contar($personaId, $capacitacionId, $estado, $alerta, $buscar, $origen),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    /** @return array{total:int,items:list<array<string,mixed>>} */
    public function proximas(): array
    {
        $items = array_map([$this, 'normalizar'], $this->repo->proximasPendientes());

        return [
            'total' => count($items),
            'items' => $items,
        ];
    }

    public function ver(int $id): array
    {
        $fila = $this->repo->buscarPorId($id);

        if ($fila === null) {
            throw new HttpException('Asignación no encontrada', 404);
        }

        return $this->normalizar($fila);
    }

    public function crear(array $datos, int $usuarioId): array
    {
        $personaId = (int)$datos['persona_id_ext'];
        $capacitacionId = (int)$datos['capacitacion_id'];
        $persona = $this->personal->ver($personaId);

        if ($this->capacitaciones->buscarPorId($capacitacionId) === null) {
            throw new HttpException('La capacitación no existe', 422);
        }

        if ($this->repo->pendienteDuplicada($personaId, $capacitacionId)) {
            throw new HttpException(
                'Esta persona ya tiene una asignación pendiente de esa capacitación',
                409
            );
        }

        $fechaAsignacion = $this->fechaONulo($datos['fecha_asignacion'] ?? null) ?? date('Y-m-d');
        $fechaLimite = $this->fechaONulo($datos['fecha_limite_cumplimiento'] ?? null);

        if ($fechaLimite === null) {
            throw new HttpException('La fecha límite de cumplimiento es obligatoria', 422);
        }

        $id = $this->repo->crear([
            'persona_id_ext' => $personaId,
            'contrato_id_ext' => $persona['contrato_id'],
            'capacitacion_id' => $capacitacionId,
            'matriz_aplicabilidad_id' => null,
            'fecha_asignacion' => $fechaAsignacion,
            'fecha_limite_cumplimiento' => $fechaLimite,
            'origen' => 'MANUAL',
            'cargo_id_ext' => $persona['cargo_id'],
            'area_id' => null,
            'proceso_id' => null,
            'ambito' => null,
            'proyecto' => $persona['proyecto'],
            'creada_por_usuario_id_ext' => $usuarioId,
        ]);

        return $this->ver($id);
    }

    /**
     * Asignación MANUAL a varios trabajadores. No toca la matriz.
     *
     * @param array<string,mixed> $datos
     * @return array{seleccionados:int, creadas:int, omitidas:int, errores:int, items:list<array<string,mixed>>, omitidas_detalle:list<array<string,mixed>>}
     */
    public function crearMasivo(array $datos, int $usuarioId): array
    {
        $ids = $this->normalizarPersonaIds($datos['persona_ids_ext'] ?? []);
        $capacitacionId = (int)$datos['capacitacion_id'];
        $cap = $this->capacitaciones->buscarPorId($capacitacionId);

        if ($cap === null) {
            throw new HttpException('La capacitación no existe', 422);
        }

        if (($cap['estado'] ?? '') !== 'ACTIVA') {
            throw new HttpException('Solo se puede asignar una capacitación activa.', 422);
        }

        $fechaLimite = $this->fechaONulo($datos['fecha_limite_cumplimiento'] ?? null);
        if ($fechaLimite === null) {
            $fechaLimite = (new MotorAsignacionService())->fechaLimiteDesdePeriodicidad(
                isset($cap['periodicidad_cantidad']) ? (int)$cap['periodicidad_cantidad'] : 0,
                isset($cap['periodicidad_unidad']) ? (string)$cap['periodicidad_unidad'] : ''
            );
        }

        $personas = [];
        $errores = 0;
        $omitidasDetalle = [];

        foreach ($ids as $personaId) {
            try {
                $personas[] = $this->personal->ver($personaId);
            } catch (HttpException $e) {
                if ($e->getStatusCode() !== 404) {
                    throw $e;
                }
                $errores++;
                $omitidasDetalle[] = [
                    'persona_id_ext' => $personaId,
                    'motivo' => 'El trabajador no existe.',
                ];
            }
        }

        if ($personas === []) {
            throw new HttpException('Ninguno de los trabajadores seleccionados existe.', 422);
        }

        $hoy = date('Y-m-d');
        $creadasIds = [];
        $omitidas = 0;

        $this->repo->transaccion(function () use (
            $personas,
            $capacitacionId,
            $fechaLimite,
            $hoy,
            $usuarioId,
            &$creadasIds,
            &$omitidas,
            &$omitidasDetalle
        ): int {
            foreach ($personas as $persona) {
                $personaId = (int)$persona['persona_id'];
                if ($this->repo->pendienteDuplicada($personaId, $capacitacionId)) {
                    $omitidas++;
                    $omitidasDetalle[] = [
                        'persona_id_ext' => $personaId,
                        'motivo' => 'Ya tiene esta capacitación pendiente.',
                    ];
                    continue;
                }

                $creadasIds[] = $this->repo->crear([
                    'persona_id_ext' => $personaId,
                    'contrato_id_ext' => $persona['contrato_id'],
                    'capacitacion_id' => $capacitacionId,
                    'matriz_aplicabilidad_id' => null,
                    'fecha_asignacion' => $hoy,
                    'fecha_limite_cumplimiento' => $fechaLimite,
                    'origen' => 'MANUAL',
                    'cargo_id_ext' => $persona['cargo_id'],
                    'area_id' => null,
                    'proceso_id' => null,
                    'ambito' => null,
                    'proyecto' => $persona['proyecto'],
                    'creada_por_usuario_id_ext' => $usuarioId,
                ]);
            }

            return count($creadasIds);
        });

        $items = [];
        foreach ($creadasIds as $id) {
            $items[] = $this->ver($id);
        }

        return [
            'seleccionados' => count($ids),
            'creadas' => count($items),
            'omitidas' => $omitidas,
            'errores' => $errores,
            'items' => $items,
            'omitidas_detalle' => $omitidasDetalle,
        ];
    }

    public function mensajeMasivo(array $resultado): string
    {
        $creadas = (int)$resultado['creadas'];
        $omitidas = (int)$resultado['omitidas'];

        if ($creadas > 0 && $omitidas > 0) {
            return "{$creadas} trabajadores fueron asignados correctamente. {$omitidas} ya tenían esta capacitación y fueron omitidos.";
        }

        if ($creadas > 0) {
            return $creadas === 1
                ? '1 trabajador fue asignado correctamente.'
                : "{$creadas} trabajadores fueron asignados correctamente.";
        }

        if ($omitidas > 0) {
            return "Ningún trabajador nuevo fue asignado. {$omitidas} ya tenían esta capacitación y fueron omitidos.";
        }

        return 'No se crearon asignaciones.';
    }

    /**
     * @return list<int>
     */
    private function normalizarPersonaIds(mixed $bruto): array
    {
        if (!is_array($bruto) || $bruto === []) {
            throw new HttpException('Debe seleccionar al menos un trabajador.', 422);
        }

        $ids = [];
        foreach ($bruto as $valor) {
            $id = (int)$valor;
            if ($id <= 0) {
                throw new HttpException('El identificador de trabajador no es válido.', 422);
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    public function actualizar(int $id, array $datos): array
    {
        $this->ver($id);
        $fechaLimite = $this->fechaONulo($datos['fecha_limite_cumplimiento'] ?? null);

        if ($fechaLimite === null) {
            throw new HttpException('La fecha límite de cumplimiento es obligatoria', 422);
        }

        $this->repo->actualizar($id, [
            'fecha_limite_cumplimiento' => $fechaLimite,
        ]);

        return $this->ver($id);
    }

    public function eliminar(int $id): string
    {
        $this->ver($id);

        if ($this->repo->tieneCumplimiento($id)) {
            throw new HttpException(
                'No se puede eliminar porque ya tiene un cumplimiento registrado',
                409
            );
        }

        $this->repo->eliminar($id);

        return 'Asignación eliminada';
    }

    /** @param array<string,mixed> $fila */
    private function normalizar(array $fila): array
    {
        $dias = $fila['dias_restantes'] !== null ? (int)$fila['dias_restantes'] : null;

        return [
            'asignacion_id' => (int)$fila['asignacion_id'],
            'persona_id_ext' => (int)$fila['persona_id_ext'],
            'persona_nombre' => $fila['persona_nombre'] !== null && $fila['persona_nombre'] !== ''
                ? (string)$fila['persona_nombre']
                : null,
            'numero_documento' => $fila['numero_documento'] !== null && $fila['numero_documento'] !== ''
                ? (string)$fila['numero_documento']
                : null,
            'contrato_id_ext' => $fila['contrato_id_ext'] !== null ? (int)$fila['contrato_id_ext'] : null,
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'capacitacion_codigo' => (string)$fila['capacitacion_codigo'],
            'capacitacion_nombre' => (string)$fila['capacitacion_nombre'],
            'fecha_asignacion' => $fila['fecha_asignacion'],
            'fecha_limite_cumplimiento' => $fila['fecha_limite_cumplimiento'],
            'origen' => (string)$fila['origen'],
            'periodicidad_nombre' => isset($fila['periodicidad_nombre']) && $fila['periodicidad_nombre'] !== ''
                ? (string)$fila['periodicidad_nombre']
                : null,
            'obligatoria' => array_key_exists('obligatoria', $fila) && $fila['obligatoria'] !== null
                ? ((int)$fila['obligatoria'] === 1)
                : null,
            'cargo_id_ext' => $fila['cargo_id_ext'] !== null ? (int)$fila['cargo_id_ext'] : null,
            'ambito' => $fila['ambito'],
            'proyecto' => $fila['proyecto'],
            'estado_calculado' => (string)$fila['estado_calculado'],
            'tiene_cumplimiento' => $fila['cumplimiento_id'] !== null,
            'dias_restantes' => $dias,
            'etiqueta_dias' => VencimientoService::etiquetaDias($dias),
        ];
    }

    private function fechaONulo(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = (string)$valor;
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $texto);

        if ($dt instanceof \DateTimeImmutable && $dt->format('Y-m-d') === $texto) {
            return $texto;
        }

        $ts = strtotime($texto);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
