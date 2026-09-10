<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Repositories\CapacitacionRepository;
use App\Repositories\MatrizRepository;
use App\Repositories\PlanAnualRepository;
use PDOException;

class PlanAnualService
{
    public const ESTADOS = ['BORRADOR', 'EN_REVISION', 'APROBADO'];

    private PlanAnualRepository $repo;
    private CapacitacionRepository $capacitaciones;
    private MatrizRepository $matriz;
    private PersonalService $personal;
    private AlertaRepository $alertas;

    public function __construct()
    {
        $this->repo = new PlanAnualRepository();
        $this->capacitaciones = new CapacitacionRepository();
        $this->matriz = new MatrizRepository();
        $this->personal = new PersonalService();
        $this->alertas = new AlertaRepository();
    }

    public function reglasActividad(): array
    {
        return [
            'capacitacion_id' => 'required|integer',
            'proceso_id' => 'required|integer',
            'proyecto' => 'nullable|string|max:120',
            'fecha_programada' => 'required|string|max:10',
        ];
    }

    public function listar(
        int $pagina,
        int $porPagina,
        ?int $anio,
        ?string $estado,
        ?string $buscar = null,
        ?int $procesoId = null,
        ?string $proyecto = null
    ): array {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        if ($estado !== null && $estado !== '' && !in_array($estado, self::ESTADOS, true)) {
            throw new HttpException('El estado del plan no es válido', 422);
        }

        $filas = $this->repo->listar($porPagina, $offset, $anio, $estado, $buscar, $procesoId, $proyecto);

        return [
            'items' => array_map([$this, 'normalizarCabecera'], $filas),
            'total' => $this->repo->contar($anio, $estado, $buscar, $procesoId, $proyecto),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    /**
     * @return array{procesos:list<array{proceso_id:int,nombre:string}>,proyectos:list<string>,capacitaciones:list<array<string,mixed>>}
     */
    public function opciones(): array
    {
        return [
            'procesos' => $this->alertas->procesosActivos(),
            'proyectos' => $this->alertas->proyectos(),
            'capacitaciones' => $this->capacitaciones->listarActivasResumen(),
        ];
    }

    public function alcance(int $capacitacionId, int $procesoId, ?string $proyecto): array
    {
        $this->exigirCapacitacionActiva($capacitacionId);
        $this->procesoDePlan($procesoId);
        $proyectoNorm = $this->proyectoSegunProceso($procesoId, $proyecto, true);
        $cargos = $this->cargosAplicables($capacitacionId, $procesoId, $proyectoNorm);

        return [
            'capacitacion_id' => $capacitacionId,
            'proceso_id' => $procesoId,
            'proyecto' => $proyectoNorm,
            'cargos_aplicables' => $cargos,
            'cantidad_programada' => $this->personal->repositorio()->contarActivosPorCargos(
                array_map(static fn (array $c): int => (int)$c['cargo_id'], $cargos)
            ),
        ];
    }

    public function ver(int $id): array
    {
        $plan = $this->exigirPlan($id);
        $detalles = $this->repo->detalles($id);
        $items = [];
        $totalHoras = 0.0;
        foreach ($detalles as $fila) {
            $item = $this->normalizarDetalle($fila);
            $totalHoras += (float)($item['duracion_estimada_horas'] ?? 0);
            $items[] = $item;
        }

        $salida = $this->normalizarCabecera($plan);
        $salida['total_horas'] = round($totalHoras, 2);
        $salida['detalles'] = $items;

        return $salida;
    }

    public function verActividad(int $planId, int $detalleId): array
    {
        $this->exigirPlan($planId);
        $fila = $this->repo->buscarDetallePorId($planId, $detalleId);
        if ($fila === null) {
            throw new HttpException('La actividad no pertenece a este plan.', 404);
        }

        return $this->normalizarDetalle($fila);
    }

    public function crear(array $datos, int $usuarioId): array
    {
        $anio = (int)($datos['anio'] ?? 0);
        if ($anio < 2000 || $anio > 2100) {
            throw new HttpException('El año no es válido', 422);
        }

        if ($this->repo->buscarPorAnio($anio) !== null) {
            throw new HttpException('Ya existe un plan anual para ese año.', 409);
        }

        try {
            $id = $this->repo->crear([
                'anio' => $anio,
                'estado' => 'BORRADOR',
                'creado_por_usuario_id_ext' => $usuarioId,
            ]);
        } catch (PDOException $e) {
            throw new HttpException('No fue posible guardar el Plan Anual.', 500);
        }

        return $this->ver($id);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function crearActividad(int $planId, array $datos): array
    {
        $plan = $this->exigirBorrador($planId);
        $payload = $this->validarActividad($plan, $datos);
        $duplicado = $this->repo->buscarDetalleActividad(
            (int)$plan['plan_anual_id'],
            $payload['capacitacion_id'],
            $payload['proceso_id'],
            $payload['proyecto'],
            $payload['fecha_programada']
        );
        if ($duplicado !== null) {
            throw new HttpException('Ya existe una actividad con la misma capacitación, proceso, proyecto y fecha.', 409);
        }

        try {
            $this->repo->crearDetalle($payload);
        } catch (PDOException $e) {
            throw new HttpException('No fue posible guardar el Plan Anual.', 500);
        }

        return $this->ver($planId);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function actualizarActividad(int $planId, int $detalleId, array $datos): array
    {
        $plan = $this->exigirBorrador($planId);
        $existente = $this->repo->buscarDetallePorId($planId, $detalleId);
        if ($existente === null) {
            throw new HttpException('La actividad no pertenece a este plan.', 404);
        }

        $payload = $this->validarActividad($plan, $datos);
        unset($payload['plan_anual_id']);
        $duplicado = $this->repo->buscarDetalleActividad(
            $planId,
            $payload['capacitacion_id'],
            $payload['proceso_id'],
            $payload['proyecto'],
            $payload['fecha_programada'],
            $detalleId
        );
        if ($duplicado !== null) {
            throw new HttpException('Ya existe una actividad con la misma capacitación, proceso, proyecto y fecha.', 409);
        }

        try {
            $this->repo->actualizarDetalle($detalleId, $payload);
        } catch (PDOException $e) {
            throw new HttpException('No fue posible guardar el Plan Anual.', 500);
        }

        return $this->ver($planId);
    }

    public function eliminarActividad(int $planId, int $detalleId): array
    {
        $this->exigirBorrador($planId);
        $existente = $this->repo->buscarDetallePorId($planId, $detalleId);
        if ($existente === null) {
            throw new HttpException('La actividad no pertenece a este plan.', 404);
        }
        if ($this->repo->detalleTieneSesiones($detalleId)) {
            throw new HttpException(
                'No es posible retirar la actividad porque tiene sesiones o historial de ejecución.',
                409
            );
        }

        $this->repo->transaccion(function () use ($detalleId): int {
            $this->repo->eliminarEnlacesDetalle($detalleId);
            $this->repo->eliminarDetalle($detalleId);

            return $detalleId;
        });

        return $this->ver($planId);
    }

    public function devolver(int $planId): array
    {
        $plan = $this->exigirPlan($planId);
        if ($plan['estado'] !== 'EN_REVISION') {
            throw new HttpException('Solo un plan pendiente de aprobación puede devolverse.', 409);
        }

        $this->repo->actualizar($planId, ['estado' => 'BORRADOR']);

        return $this->ver($planId);
    }

    /**
     * @return array{seleccionados:int, creadas:int, omitidas:int, items:list<array<string,mixed>>, omitidas_detalle:list<array<string,mixed>>}
     */
    public function incluirAsignaciones(int $planId, array $datos): array
    {
        $plan = $this->exigirBorrador($planId);
        $mes = (int)($datos['mes_programado'] ?? 0);
        if ($mes < 1 || $mes > 12) {
            throw new HttpException('El mes debe estar entre 1 y 12.', 422);
        }

        $ids = $this->normalizarIds($datos['asignacion_ids'] ?? []);
        $creadas = 0;
        $omitidas = [];

        $this->repo->transaccion(function () use ($plan, $ids, $mes, &$creadas, &$omitidas): int {
            foreach ($ids as $asignacionId) {
                $asig = $this->repo->buscarAsignacion($asignacionId);
                if ($asig === null) {
                    $omitidas[] = [
                        'asignacion_id' => $asignacionId,
                        'motivo' => 'La asignación no existe.',
                    ];
                    continue;
                }

                if ($this->repo->enlacePorAsignacion((int)$plan['plan_anual_id'], $asignacionId) !== null) {
                    $omitidas[] = [
                        'asignacion_id' => $asignacionId,
                        'motivo' => 'La asignación ya está incluida en este plan.',
                    ];
                    continue;
                }

                $capacitacionId = (int)$asig['capacitacion_id'];
                $detalle = $this->repo->buscarDetalle((int)$plan['plan_anual_id'], $capacitacionId, $mes);
                if ($detalle === null) {
                    $anio = (int)$plan['anio'];
                    $fecha = sprintf('%04d-%02d-01', $anio, $mes);
                    $detalleId = $this->repo->crearDetalle([
                        'plan_anual_id' => (int)$plan['plan_anual_id'],
                        'capacitacion_id' => $capacitacionId,
                        'mes_programado' => $mes,
                        'fecha_programada' => $fecha,
                        'cantidad_programada' => 0,
                        'area_id' => $asig['area_id'] !== null ? (int)$asig['area_id'] : null,
                        'proceso_id' => $asig['proceso_id'] !== null ? (int)$asig['proceso_id'] : null,
                        'ambito' => $asig['ambito'] !== null && $asig['ambito'] !== '' ? $asig['ambito'] : null,
                        'proyecto' => $asig['proyecto'] !== null && $asig['proyecto'] !== '' ? $asig['proyecto'] : null,
                    ]);
                } else {
                    $detalleId = (int)$detalle['plan_detalle_id'];
                }

                $this->repo->enlazar($detalleId, $asignacionId);
                $this->repo->actualizarCantidad($detalleId, $this->repo->contarEnlacesDetalle($detalleId));
                $creadas++;
            }

            return $creadas;
        });

        return [
            'seleccionados' => count($ids),
            'creadas' => $creadas,
            'omitidas' => count($omitidas),
            'items' => $this->ver($planId)['detalles'],
            'omitidas_detalle' => $omitidas,
        ];
    }

    public function mensajeInclusion(array $resultado): string
    {
        $creadas = (int)$resultado['creadas'];
        $omitidas = (int)$resultado['omitidas'];

        if ($creadas > 0 && $omitidas > 0) {
            return "{$creadas} asignaciones incluidas. {$omitidas} ya estaban en el plan y fueron omitidas.";
        }
        if ($creadas > 0) {
            return $creadas === 1
                ? '1 asignación incluida en el plan.'
                : "{$creadas} asignaciones incluidas en el plan.";
        }
        if ($omitidas > 0) {
            return 'No se incluyeron asignaciones nuevas. Ya estaban en el plan.';
        }

        return 'No se incluyeron asignaciones.';
    }

    public function quitarAsignacion(int $planId, int $asignacionId): array
    {
        $this->exigirBorrador($planId);
        $enlace = $this->repo->enlacePorAsignacion($planId, $asignacionId);
        if ($enlace === null) {
            throw new HttpException('La asignación no está en este plan.', 404);
        }

        $detalleId = (int)$enlace['plan_detalle_id'];
        $this->repo->transaccion(function () use ($detalleId, $asignacionId): int {
            $this->repo->desenlazar($detalleId, $asignacionId);
            $restantes = $this->repo->contarEnlacesDetalle($detalleId);
            if ($restantes === 0 && !$this->repo->detalleTieneSesiones($detalleId)) {
                $this->repo->eliminarDetalle($detalleId);
            } else {
                $this->repo->actualizarCantidad($detalleId, $restantes);
            }

            return $restantes;
        });

        return $this->ver($planId);
    }

    public function moverAsignacion(int $planId, int $asignacionId, int $mes): array
    {
        $this->exigirBorrador($planId);
        if ($mes < 1 || $mes > 12) {
            throw new HttpException('El mes debe estar entre 1 y 12.', 422);
        }

        $enlace = $this->repo->enlacePorAsignacion($planId, $asignacionId);
        if ($enlace === null) {
            throw new HttpException('La asignación no está en este plan.', 404);
        }

        if ((int)$enlace['mes_programado'] === $mes) {
            return $this->ver($planId);
        }

        $asig = $this->repo->buscarAsignacion($asignacionId);
        if ($asig === null) {
            throw new HttpException('La asignación no existe.', 422);
        }

        $this->repo->transaccion(function () use ($planId, $enlace, $asig, $asignacionId, $mes): int {
            $origenId = (int)$enlace['plan_detalle_id'];
            $this->repo->desenlazar($origenId, $asignacionId);
            $restantes = $this->repo->contarEnlacesDetalle($origenId);
            if ($restantes === 0 && !$this->repo->detalleTieneSesiones($origenId)) {
                $this->repo->eliminarDetalle($origenId);
            } else {
                $this->repo->actualizarCantidad($origenId, $restantes);
            }

            $capacitacionId = (int)$asig['capacitacion_id'];
            $destino = $this->repo->buscarDetalle($planId, $capacitacionId, $mes);
            if ($destino === null) {
                $anioPlan = $this->exigirPlan($planId);
                $fecha = sprintf('%04d-%02d-01', (int)$anioPlan['anio'], $mes);
                $destinoId = $this->repo->crearDetalle([
                    'plan_anual_id' => $planId,
                    'capacitacion_id' => $capacitacionId,
                    'mes_programado' => $mes,
                    'fecha_programada' => $fecha,
                    'cantidad_programada' => 0,
                    'area_id' => $asig['area_id'] !== null ? (int)$asig['area_id'] : null,
                    'proceso_id' => $asig['proceso_id'] !== null ? (int)$asig['proceso_id'] : null,
                    'ambito' => $asig['ambito'] !== null && $asig['ambito'] !== '' ? $asig['ambito'] : null,
                    'proyecto' => $asig['proyecto'] !== null && $asig['proyecto'] !== '' ? $asig['proyecto'] : null,
                ]);
            } else {
                $destinoId = (int)$destino['plan_detalle_id'];
            }

            $this->repo->enlazar($destinoId, $asignacionId);
            $this->repo->actualizarCantidad($destinoId, $this->repo->contarEnlacesDetalle($destinoId));

            return $destinoId;
        });

        return $this->ver($planId);
    }

    public function disponibles(int $planId, ?string $buscar): array
    {
        $this->exigirPlan($planId);
        $filas = $this->repo->asignacionesDisponibles($planId, $buscar, 100);

        return [
            'items' => array_map([$this, 'normalizarAsignacionPlan'], $filas),
            'total' => count($filas),
        ];
    }

    public function enviarRevision(int $planId): array
    {
        $plan = $this->exigirPlan($planId);
        if ($plan['estado'] !== 'BORRADOR') {
            throw new HttpException('Solo un plan en borrador puede enviarse a revisión.', 409);
        }
        if ($this->repo->contarDetalles($planId) < 1) {
            throw new HttpException('El plan no puede enviarse a revisión porque tiene información incompleta.', 422);
        }

        $this->repo->actualizar($planId, ['estado' => 'EN_REVISION']);

        return $this->ver($planId);
    }

    public function aprobar(int $planId, int $usuarioId): array
    {
        $plan = $this->exigirPlan($planId);
        if ($plan['estado'] === 'APROBADO') {
            throw new HttpException('El plan ya fue aprobado y no puede modificarse bajo el flujo actual.', 409);
        }
        if ($plan['estado'] !== 'EN_REVISION') {
            throw new HttpException('El plan debe estar en revisión para poder aprobarse.', 409);
        }
        if ($this->repo->contarDetalles($planId) < 1) {
            throw new HttpException('El plan no puede aprobarse porque no tiene capacitaciones programadas.', 422);
        }

        $this->repo->actualizar($planId, [
            'estado' => 'APROBADO',
            'aprobado_por_usuario_id_ext' => $usuarioId,
            'fecha_aprobacion' => date('Y-m-d H:i:s'),
        ]);

        return $this->ver($planId);
    }

    /** @param array<string,mixed> $fila */
    private function normalizarCabecera(array $fila): array
    {
        return [
            'plan_anual_id' => (int)$fila['plan_anual_id'],
            'anio' => (int)$fila['anio'],
            'estado' => (string)$fila['estado'],
            'total_programadas' => (int)($fila['total_programadas'] ?? 0),
            'aprobado_por_usuario_id_ext' => $fila['aprobado_por_usuario_id_ext'] !== null
                ? (int)$fila['aprobado_por_usuario_id_ext']
                : null,
            'fecha_aprobacion' => $fila['fecha_aprobacion'],
            'creado_por_usuario_id_ext' => $fila['creado_por_usuario_id_ext'] !== null
                ? (int)$fila['creado_por_usuario_id_ext']
                : null,
            'created_at' => $fila['created_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $fila */
    private function normalizarAsignacionPlan(array $fila): array
    {
        return [
            'asignacion_id' => (int)$fila['asignacion_id'],
            'persona_id_ext' => isset($fila['persona_id_ext']) ? (int)$fila['persona_id_ext'] : null,
            'persona_nombre' => $fila['persona_nombre'] !== null && $fila['persona_nombre'] !== ''
                ? (string)$fila['persona_nombre']
                : null,
            'numero_documento' => $fila['numero_documento'] !== null && $fila['numero_documento'] !== ''
                ? (string)$fila['numero_documento']
                : null,
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'capacitacion_codigo' => (string)($fila['capacitacion_codigo'] ?? ''),
            'capacitacion_nombre' => (string)($fila['capacitacion_nombre'] ?? ''),
            'origen' => (string)($fila['origen'] ?? ''),
            'proyecto' => $fila['proyecto'] ?? null,
        ];
    }

    /** @return list<int> */
    private function normalizarIds(mixed $bruto): array
    {
        if (!is_array($bruto) || $bruto === []) {
            throw new HttpException('Debe seleccionar al menos una asignación.', 422);
        }

        $ids = [];
        foreach ($bruto as $valor) {
            $id = (int)$valor;
            if ($id <= 0) {
                throw new HttpException('El identificador de asignación no es válido.', 422);
            }
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    /** @return array<string,mixed> */
    private function exigirPlan(int $id): array
    {
        $plan = $this->repo->buscarPorId($id);
        if ($plan === null) {
            throw new HttpException('El plan anual no existe.', 404);
        }

        return $plan;
    }

    /** @return array<string,mixed> */
    private function exigirBorrador(int $id): array
    {
        $plan = $this->exigirPlan($id);
        if ($plan['estado'] === 'APROBADO') {
            throw new HttpException('El plan ya fue aprobado y no puede modificarse bajo el flujo actual.', 409);
        }
        if ($plan['estado'] !== 'BORRADOR') {
            throw new HttpException('Solo se puede modificar un plan en borrador.', 409);
        }

        return $plan;
    }

    /**
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $datos
     * @return array<string,mixed>
     */
    private function validarActividad(array $plan, array $datos): array
    {
        $capacitacionId = (int)($datos['capacitacion_id'] ?? 0);
        $this->exigirCapacitacionActiva($capacitacionId);

        $procesoId = (int)($datos['proceso_id'] ?? 0);
        $this->procesoDePlan($procesoId);
        $proyecto = $this->proyectoSegunProceso($procesoId, $datos['proyecto'] ?? null, true);
        $fecha = $this->fechaEnAnio((string)($datos['fecha_programada'] ?? ''), (int)$plan['anio']);
        $cargos = $this->cargosAplicables($capacitacionId, $procesoId, $proyecto);
        if ($cargos === []) {
            throw new HttpException(
                'La capacitación seleccionada no está definida como aplicable al cargo seleccionado.',
                422
            );
        }

        $mes = (int)substr($fecha, 5, 2);
        $cantidad = $this->personal->repositorio()->contarActivosPorCargos(
            array_map(static fn (array $c): int => (int)$c['cargo_id'], $cargos)
        );

        return [
            'plan_anual_id' => (int)$plan['plan_anual_id'],
            'capacitacion_id' => $capacitacionId,
            'mes_programado' => $mes,
            'fecha_programada' => $fecha,
            'cantidad_programada' => $cantidad,
            'area_id' => null,
            'proceso_id' => $procesoId,
            'ambito' => $this->alertas->procesoEsGestionProyectos($procesoId) ? 'PROYECTO' : 'ADMINISTRACION',
            'proyecto' => $proyecto,
        ];
    }

    private function exigirCapacitacionActiva(int $capacitacionId): void
    {
        if ($capacitacionId < 1) {
            throw new HttpException('La capacitación seleccionada no existe o no se encuentra disponible.', 422);
        }
        $cap = $this->capacitaciones->buscarPorId($capacitacionId);
        if ($cap === null || strtoupper((string)($cap['estado'] ?? '')) !== 'ACTIVA') {
            throw new HttpException('La capacitación seleccionada no existe o no se encuentra disponible.', 422);
        }
    }

    /**
     * @return array{proceso_id:int,nombre:string}
     */
    private function procesoDePlan(int $procesoId): array
    {
        foreach ($this->alertas->procesosActivos() as $proceso) {
            if ((int)$proceso['proceso_id'] === $procesoId) {
                return $proceso;
            }
        }

        throw new HttpException('El proceso seleccionado no es válido.', 422);
    }

    private function proyectoSegunProceso(int $procesoId, mixed $proyecto, bool $exigirSiGestion): ?string
    {
        if (!$this->alertas->procesoEsGestionProyectos($procesoId)) {
            return null;
        }

        $normalizado = nullable_trimmed_string($proyecto);
        if ($normalizado === null || $normalizado === '') {
            if ($exigirSiGestion) {
                throw new HttpException('El proyecto es obligatorio para Gestión de Proyectos.', 422);
            }

            return null;
        }

        $canonico = $this->alertas->resolverProyecto($normalizado, true);
        if ($canonico === null) {
            throw new HttpException('El proyecto no es válido.', 422);
        }

        return $canonico;
    }

    private function fechaEnAnio(string $fecha, int $anio): string
    {
        $fecha = trim($fecha);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        $errores = \DateTimeImmutable::getLastErrors();
        $conError = is_array($errores)
            && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0);
        if ($dt === false || $conError) {
            throw new HttpException('La fecha de programación no es válida.', 422);
        }
        if ((int)$dt->format('Y') !== $anio) {
            throw new HttpException('La fecha debe pertenecer al año del plan.', 422);
        }

        return $dt->format('Y-m-d');
    }

    /**
     * @return list<array{cargo_id:int,nombre_cargo:string}>
     */
    private function cargosAplicables(int $capacitacionId, int $procesoId, ?string $proyecto): array
    {
        $filas = $this->matriz->cargosActivosDeCapacitacion($capacitacionId, $procesoId, $proyecto);
        $ids = [];
        foreach ($filas as $fila) {
            $id = (int)($fila['cargo_id_ext'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $nombres = $this->personal->nombresCargosPorIds(array_values($ids));
        $salida = [];
        foreach ($ids as $id) {
            $salida[] = [
                'cargo_id' => $id,
                'nombre_cargo' => $nombres[$id] ?? ('Cargo ' . $id),
            ];
        }
        usort(
            $salida,
            static fn (array $a, array $b): int => strcasecmp((string)$a['nombre_cargo'], (string)$b['nombre_cargo'])
        );

        return $salida;
    }

    /** @param array<string,mixed> $fila */
    private function normalizarDetalle(array $fila): array
    {
        $mes = (int)$fila['mes_programado'];
        $procesoId = $fila['proceso_id'] !== null ? (int)$fila['proceso_id'] : null;
        $proyecto = $fila['proyecto'] !== null && $fila['proyecto'] !== '' ? (string)$fila['proyecto'] : null;
        $cargos = [];
        if ($procesoId !== null && $procesoId > 0) {
            $cargos = $this->cargosAplicables((int)$fila['capacitacion_id'], $procesoId, $proyecto);
        }

        $vigencia = null;
        if (!empty($fila['vigencia_nombre'])) {
            $vigencia = humanizar_nombre_unidad((string)$fila['vigencia_nombre']);
        } elseif (!empty($fila['vigencia_cantidad']) && !empty($fila['vigencia_unidad'])) {
            $vigencia = humanizar_nombre_unidad((int)$fila['vigencia_cantidad'] . ' ' . $fila['vigencia_unidad']);
        }

        return [
            'plan_detalle_id' => (int)$fila['plan_detalle_id'],
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'capacitacion_codigo' => (string)$fila['capacitacion_codigo'],
            'capacitacion_nombre' => (string)$fila['capacitacion_nombre'],
            'capacitacion_objetivo' => $fila['capacitacion_objetivo'] ?? null,
            'tipo_nombre' => $fila['tipo_nombre'] ?? null,
            'duracion_estimada_horas' => $fila['duracion_estimada_horas'] !== null
                ? (float)$fila['duracion_estimada_horas']
                : null,
            'es_tarea_critica' => (int)($fila['es_tarea_critica'] ?? 0) === 1,
            'evaluacion' => (int)($fila['evaluacion'] ?? 0) === 1,
            'vigencia_nombre' => $vigencia,
            'modalidad_nombre' => $fila['modalidad_nombre'] ?? null,
            'fecha_programada' => $fila['fecha_programada'] !== null
                ? substr((string)$fila['fecha_programada'], 0, 10)
                : null,
            'mes_programado' => $mes,
            'mes_nombre' => $this->nombreMes($mes),
            'trimestre' => (int)ceil($mes / 3),
            'cantidad_programada' => (int)$fila['cantidad_programada'],
            'proceso_id' => $procesoId,
            'proceso_nombre' => $fila['proceso_nombre'] !== null && $fila['proceso_nombre'] !== ''
                ? (string)$fila['proceso_nombre']
                : null,
            'ambito' => $fila['ambito'],
            'proyecto' => $proyecto,
            'cargos_aplicables' => $cargos,
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
