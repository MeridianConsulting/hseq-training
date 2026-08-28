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

    public function listar(
        int $pagina,
        int $porPagina,
        ?int $personaId,
        ?int $capacitacionId,
        ?string $estado,
        ?string $alerta,
        ?string $buscar
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

        $filas = $this->repo->listar($porPagina, $offset, $personaId, $capacitacionId, $estado, $alerta, $buscar);

        return [
            'items' => array_map([$this, 'normalizar'], $filas),
            'total' => $this->repo->contar($personaId, $capacitacionId, $estado, $alerta, $buscar),
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
