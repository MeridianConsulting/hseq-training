<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\SesionRepository;
use PDOException;

class SesionService
{
    private const SQLSTATE_INTEGRIDAD = '23000';

    private SesionRepository $repo;
    private VencimientoService $vencimiento;
    private SoporteService $soportes;

    public function __construct()
    {
        $this->repo = new SesionRepository();
        $this->vencimiento = new VencimientoService();
        $this->soportes = new SoporteService();
    }

    public function reglasCrear(): array
    {
        return [
            'plan_detalle_id' => 'required|integer|min:1',
            'capacitacion_id' => 'nullable|integer|min:1',
            'fecha' => 'required|date',
            'hora' => 'required|string|max:8',
            'modalidad_id' => 'required|integer|min:1',
            'ubicacion_id' => 'nullable|integer|min:1',
            'enlace_virtual' => 'nullable|string|max:500',
            'proveedor_id' => 'required|integer|min:1',
            'cupo_maximo' => 'required|integer|min:1',
            'asignacion_ids' => 'nullable|array',
            'observaciones' => 'nullable|string',
        ];
    }

    public function reglasEditar(): array
    {
        return [
            'fecha' => 'required|date',
            'hora' => 'required|string|max:8',
            'modalidad_id' => 'required|integer|min:1',
            'ubicacion_id' => 'nullable|integer|min:1',
            'enlace_virtual' => 'nullable|string|max:500',
            'proveedor_id' => 'required|integer|min:1',
            'cupo_maximo' => 'required|integer|min:1',
            'observaciones' => 'nullable|string',
        ];
    }

    public function mensajes(): array
    {
        return [
            'fecha.required' => 'La fecha de la sesión es obligatoria.',
            'fecha.date' => 'La fecha de la sesión no es válida.',
            'hora.required' => 'La hora de la sesión es obligatoria.',
            'modalidad_id.required' => 'La modalidad es obligatoria.',
            'proveedor_id.required' => 'El proveedor o capacitador es obligatorio.',
            'cupo_maximo.required' => 'El cupo máximo es obligatorio.',
            'cupo_maximo.integer' => 'El cupo máximo debe ser un número entero.',
            'cupo_maximo.min' => 'El cupo máximo debe ser mayor que cero.',
            'plan_detalle_id.required' => 'Debe seleccionar una capacitación del plan anual.',
        ];
    }

    public function listarPorDetalle(int $planDetalleId): array
    {
        $this->exigirDetalle($planDetalleId);

        return $this->resumir($this->repo->listarPorDetalle($planDetalleId));
    }

    /**
     * @return array<string,mixed>
     */
    public function contexto(int $planDetalleId, ?int $sesionId = null, ?string $buscar = null): array
    {
        $detalle = $this->exigirDetalle($planDetalleId);

        return [
            'plan_detalle_id' => (int)$detalle['plan_detalle_id'],
            'plan_anual_id' => (int)$detalle['plan_anual_id'],
            'anio' => (int)$detalle['anio'],
            'plan_estado' => (string)$detalle['plan_estado'],
            'mes_programado' => (int)$detalle['mes_programado'],
            'capacitacion_id' => (int)$detalle['capacitacion_id'],
            'capacitacion_codigo' => (string)$detalle['capacitacion_codigo'],
            'capacitacion_nombre' => (string)$detalle['capacitacion_nombre'],
            'modalidad_default_id' => $detalle['modalidad_default_id'] !== null
                ? (int)$detalle['modalidad_default_id']
                : null,
            'proveedor_default_id' => $detalle['proveedor_default_id'] !== null
                ? (int)$detalle['proveedor_default_id']
                : null,
            'modalidades' => $this->repo->catalogoListar('modalidades', 'modalidad_id'),
            'ubicaciones' => $this->repo->catalogoListar('ubicaciones', 'ubicacion_id'),
            'proveedores' => $this->repo->catalogoListar('proveedores_capacitadores', 'proveedor_id'),
            'items' => array_map(
                [$this, 'normalizarConvocable'],
                $this->repo->convocables(
                    (int)$detalle['capacitacion_id'],
                    $planDetalleId,
                    $sesionId,
                    $buscar
                )
            ),
        ];
    }

    public function ver(int $id): array
    {
        $fila = $this->repo->buscarPorId($id);
        if ($fila === null) {
            throw new HttpException('La sesión no existe.', 404);
        }

        $salida = $this->normalizar($fila);
        $salida['participantes'] = array_map(
            [$this, 'normalizarParticipante'],
            $this->repo->participantes($id)
        );
        $salida['resumen'] = $this->resumenAsistencia($salida['participantes']);

        return $salida;
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function crear(array $datos, int $usuarioId): array
    {
        $detalle = $this->exigirDetalle((int)$datos['plan_detalle_id']);
        $this->exigirPlanAprobado($detalle);
        $this->exigirCapacitacionDelDetalle($datos, $detalle);

        $campos = $this->prepararCampos($datos, $detalle);
        $campos['plan_detalle_id'] = (int)$detalle['plan_detalle_id'];
        $campos['capacitacion_id'] = (int)$detalle['capacitacion_id'];
        $campos['creado_por_usuario_id_ext'] = $usuarioId > 0 ? $usuarioId : null;
        $campos['estado'] = 'PROGRAMADA';

        $ids = $this->normalizarIds($datos['asignacion_ids'] ?? []);
        if (count($ids) > (int)$campos['cupo_maximo']) {
            throw new HttpException(
                $this->mensajeCupo((int)$campos['cupo_maximo'], 0, count($ids)),
                422
            );
        }

        try {
            $sesionId = (int)$this->repo->transaccion(function () use ($campos, $ids, $detalle, $usuarioId) {
                $nuevoId = $this->repo->crear($campos);
                if ($ids !== []) {
                    $this->insertarConvocadosAtomico(
                        $nuevoId,
                        (int)$detalle['capacitacion_id'],
                        (int)$campos['cupo_maximo'],
                        $ids,
                        $usuarioId > 0 ? $usuarioId : null,
                        true
                    );
                }

                return $nuevoId;
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw $this->errorPersistencia($e, 'No fue posible crear la sesión.');
        }

        return $this->ver($sesionId);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function actualizar(int $id, array $datos): array
    {
        $actual = $this->ver($id);
        if (($actual['estado'] ?? '') === 'CANCELADA') {
            throw new HttpException('No es posible editar una sesión cancelada.', 409);
        }

        if ($actual['plan_detalle_id'] === null) {
            throw new HttpException('La sesión no está asociada a un detalle del plan anual.', 422);
        }

        $detalle = $this->exigirDetalle((int)$actual['plan_detalle_id']);
        $this->exigirPlanAprobado($detalle);

        $campos = $this->prepararCampos($datos, $detalle);
        $nuevoCupo = (int)$campos['cupo_maximo'];
        $convocados = (int)$actual['convocados'];
        if ($nuevoCupo < $convocados) {
            throw new HttpException(
                "No es posible reducir el cupo a {$nuevoCupo} porque la sesión ya tiene {$convocados} trabajadores convocados.",
                422
            );
        }

        try {
            $this->repo->actualizar($id, $campos);
        } catch (PDOException $e) {
            throw $this->errorPersistencia($e, 'No fue posible actualizar la sesión.');
        }

        return $this->ver($id);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function convocar(int $sesionId, array $datos, int $usuarioId): array
    {
        $ids = $this->normalizarIds($datos['asignacion_ids'] ?? []);
        if ($ids === []) {
            throw new HttpException('Seleccione al menos un trabajador.', 422);
        }

        try {
            $this->repo->transaccion(function () use ($sesionId, $ids, $usuarioId) {
                $sesion = $this->repo->bloquearPorId($sesionId);
                if ($sesion === null) {
                    throw new HttpException('La sesión no existe.', 404);
                }
                if (($sesion['estado'] ?? '') === 'CANCELADA') {
                    throw new HttpException('No es posible convocar trabajadores a una sesión cancelada.', 409);
                }

                $cupo = (int)($sesion['cupo_maximo'] ?? 0);
                $this->insertarConvocadosAtomico(
                    $sesionId,
                    (int)$sesion['capacitacion_id'],
                    $cupo,
                    $ids,
                    $usuarioId > 0 ? $usuarioId : null,
                    false
                );
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw $this->errorPersistencia($e, 'No fue posible convocar a los trabajadores.');
        }

        return $this->ver($sesionId);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function guardarAsistencia(int $sesionId, array $datos, int $usuarioId): array
    {
        $items = $datos['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new HttpException('Debe enviar los resultados de asistencia.', 422);
        }

        try {
            $this->repo->transaccion(function () use ($sesionId, $items, $usuarioId): void {
                $sesion = $this->repo->bloquearPorId($sesionId);
                if ($sesion === null) {
                    throw new HttpException('La sesión no existe.', 404);
                }
                if (($sesion['estado'] ?? '') === 'CANCELADA') {
                    throw new HttpException('No es posible registrar asistencia en una sesión cancelada.', 409);
                }

                $convocados = $this->repo->participantes($sesionId);
                if ($convocados === []) {
                    throw new HttpException('Esta sesión no tiene trabajadores convocados.', 422);
                }

                $porAsig = [];
                foreach ($convocados as $fila) {
                    $porAsig[(int)$fila['asignacion_id']] = $fila;
                }

                $normalizados = $this->normalizarItemsAsistencia($items, $porAsig);
                $cap = $this->repo->datosCapacitacionCumplimiento((int)$sesion['capacitacion_id']);
                $fechaReal = substr((string)($sesion['fecha_hora'] ?? ''), 0, 10);
                $usuario = $usuarioId > 0 ? $usuarioId : null;

                foreach ($normalizados as $item) {
                    $this->repo->actualizarAsistencia($sesionId, $item['asignacion_id'], [
                        'estado_asistencia' => $item['estado_asistencia'],
                        'motivo_ausencia' => $item['motivo_ausencia'],
                        'observacion' => $item['observacion'],
                        'registrado_por_usuario_id_ext' => $usuario,
                    ]);
                    $this->sincronizarCumplimiento(
                        $sesionId,
                        $item['asignacion_id'],
                        $item['estado_asistencia'],
                        $fechaReal,
                        $cap,
                        $usuario
                    );
                }
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw $this->errorPersistencia($e, 'No fue posible registrar la asistencia.');
        }

        return $this->ver($sesionId);
    }

    /**
     * @param array<string,mixed> $datos
     */
    public function reprogramar(int $destinoId, array $datos, int $usuarioId): array
    {
        $origenId = (int)($datos['origen_sesion_id'] ?? 0);
        $ids = $this->normalizarIds($datos['asignacion_ids'] ?? []);
        if ($origenId < 1) {
            throw new HttpException('Debe indicar la sesión de origen.', 422);
        }
        if ($destinoId === $origenId) {
            throw new HttpException('La sesión destino debe ser distinta a la de origen.', 422);
        }
        if ($ids === []) {
            throw new HttpException('Seleccione al menos un trabajador ausente.', 422);
        }

        $seleccionados = count($ids);
        $reprogramados = 0;

        try {
            $this->repo->transaccion(function () use (
                $destinoId,
                $origenId,
                $ids,
                $usuarioId,
                &$reprogramados
            ): void {
                $destino = $this->repo->bloquearPorId($destinoId);
                if ($destino === null) {
                    throw new HttpException('La sesión destino no existe.', 404);
                }
                if (($destino['estado'] ?? '') === 'CANCELADA') {
                    throw new HttpException('No es posible reprogramar hacia una sesión cancelada.', 409);
                }

                $origen = $this->repo->buscarPorId($origenId);
                if ($origen === null) {
                    throw new HttpException('La sesión de origen no existe.', 404);
                }
                if ((int)$destino['capacitacion_id'] !== (int)$origen['capacitacion_id']) {
                    throw new HttpException('La sesión destino debe ser de la misma capacitación.', 422);
                }

                $partOrigen = $this->repo->participantes($origenId);
                $porAsig = [];
                foreach ($partOrigen as $fila) {
                    $porAsig[(int)$fila['asignacion_id']] = $fila;
                }

                foreach ($ids as $asignacionId) {
                    if (!isset($porAsig[$asignacionId])) {
                        throw new HttpException('El trabajador no está convocado a la sesión de origen.', 422);
                    }
                    if ((string)$porAsig[$asignacionId]['estado_asistencia'] !== 'AUSENTE') {
                        throw new HttpException('Solo se pueden reprogramar trabajadores ausentes.', 422);
                    }
                }

                $reprogramados = $this->insertarConvocadosAtomico(
                    $destinoId,
                    (int)$destino['capacitacion_id'],
                    (int)($destino['cupo_maximo'] ?? 0),
                    $ids,
                    $usuarioId > 0 ? $usuarioId : null,
                    true,
                    true
                );
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw $this->errorPersistencia($e, 'No fue posible reprogramar a los trabajadores.');
        }

        $salida = $this->ver($destinoId);
        $salida['reprogramacion'] = [
            'seleccionados' => $seleccionados,
            'reprogramados' => $reprogramados,
            'omitidas' => max(0, $seleccionados - $reprogramados),
            'errores' => 0,
        ];

        return $salida;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function historialPersona(int $personaId): array
    {
        if ($personaId < 1) {
            throw new HttpException('Debe indicar el trabajador.', 422);
        }

        return array_map(
            [$this, 'normalizarIntento'],
            $this->repo->participacionesPorPersona($personaId)
        );
    }

    public function retirar(int $sesionId, int $asignacionId): array
    {
        $this->ver($sesionId);
        $eliminados = $this->repo->eliminarParticipante($sesionId, $asignacionId);
        if ($eliminados < 1) {
            throw new HttpException('El trabajador no está convocado a esta sesión.', 404);
        }

        return $this->ver($sesionId);
    }

    /**
     * @param list<array<string,mixed>> $filas
     * @return list<array<string,mixed>>
     */
    public function resumir(array $filas): array
    {
        return array_map([$this, 'normalizarResumen'], $filas);
    }

    /**
     * @param array<string,mixed> $datos
     * @param array<string,mixed> $detalle
     * @return array<string,mixed>
     */
    private function prepararCampos(array $datos, array $detalle): array
    {
        $fechaHora = $this->combinarFechaHora($datos['fecha'] ?? null, $datos['hora'] ?? null);
        $anioFecha = (int)substr($fechaHora, 0, 4);
        $anioPlan = (int)$detalle['anio'];
        if ($anioFecha !== $anioPlan) {
            throw new HttpException(
                "La fecha de la sesión debe corresponder al año del plan ({$anioPlan}).",
                422
            );
        }

        $modalidadId = (int)($datos['modalidad_id'] ?? 0);
        $modalidad = $this->exigirCatalogo('modalidades', 'modalidad_id', $modalidadId, 'modalidad');
        $tipo = $this->tipoModalidad((string)$modalidad['nombre']);

        $ubicacionId = $this->enteroONulo($datos['ubicacion_id'] ?? null);
        $enlace = nullable_trimmed_string($datos['enlace_virtual'] ?? null);

        if ($tipo === 'VIRTUAL') {
            $this->exigirEnlace($enlace, 'El enlace es obligatorio para una sesión virtual.');
            $ubicacionId = $ubicacionId !== null
                ? $this->exigirCatalogoId('ubicaciones', 'ubicacion_id', $ubicacionId, 'ubicación')
                : null;
        } elseif ($tipo === 'PRESENCIAL') {
            if ($ubicacionId === null) {
                throw new HttpException('La ubicación es obligatoria para una sesión presencial.', 422);
            }
            $this->exigirCatalogoId('ubicaciones', 'ubicacion_id', $ubicacionId, 'ubicación');
            $enlace = null;
        } elseif ($tipo === 'MIXTA') {
            if ($ubicacionId === null) {
                throw new HttpException('La ubicación es obligatoria para una sesión mixta.', 422);
            }
            $this->exigirCatalogoId('ubicaciones', 'ubicacion_id', $ubicacionId, 'ubicación');
            $this->exigirEnlace($enlace, 'El enlace es obligatorio para una sesión mixta.');
        } elseif ($ubicacionId !== null) {
            $this->exigirCatalogoId('ubicaciones', 'ubicacion_id', $ubicacionId, 'ubicación');
        }

        $proveedorId = (int)($datos['proveedor_id'] ?? 0);
        $this->exigirCatalogoId('proveedores_capacitadores', 'proveedor_id', $proveedorId, 'proveedor o capacitador');

        $cupo = $this->exigirCupoEntero($datos['cupo_maximo'] ?? null);

        return [
            'fecha_hora' => $fechaHora,
            'modalidad_id' => $modalidadId,
            'ubicacion_id' => $ubicacionId,
            'enlace_virtual' => $enlace,
            'proveedor_id' => $proveedorId,
            'cupo_maximo' => $cupo,
            'observaciones' => nullable_trimmed_string($datos['observaciones'] ?? null),
        ];
    }

    private function combinarFechaHora(mixed $fecha, mixed $hora): string
    {
        $fechaTxt = is_string($fecha) ? trim($fecha) : '';
        $horaTxt = is_string($hora) ? trim($hora) : (is_numeric($hora) ? (string)$hora : '');

        if ($fechaTxt === '') {
            throw new HttpException('La fecha de la sesión es obligatoria.', 422);
        }
        if ($horaTxt === '') {
            throw new HttpException('La hora de la sesión es obligatoria.', 422);
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fechaTxt, $f)) {
            throw new HttpException('La fecha de la sesión no es válida.', 422);
        }
        if (!checkdate((int)$f[2], (int)$f[3], (int)$f[1])) {
            throw new HttpException('La fecha de la sesión no es válida.', 422);
        }

        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $horaTxt, $h)) {
            throw new HttpException('La hora de la sesión no es válida.', 422);
        }

        $segundos = isset($h[3]) && $h[3] !== '' ? $h[3] : '00';

        return sprintf('%s %02d:%s:%s', $fechaTxt, (int)$h[1], $h[2], $segundos);
    }

    private function tipoModalidad(string $nombre): string
    {
        $clave = mb_strtoupper($nombre);
        if (str_contains($clave, 'MIXTA')) {
            return 'MIXTA';
        }
        if (str_contains($clave, 'VIRTUAL')) {
            return 'VIRTUAL';
        }
        if (str_contains($clave, 'PRESENCIAL')) {
            return 'PRESENCIAL';
        }

        return 'OTRA';
    }

    private function exigirEnlace(?string $enlace, string $mensaje): void
    {
        if ($enlace === null || $enlace === '') {
            throw new HttpException($mensaje, 422);
        }
        if (filter_var($enlace, FILTER_VALIDATE_URL) === false || !preg_match('#^https?://#i', $enlace)) {
            throw new HttpException('El enlace no tiene un formato válido.', 422);
        }
    }

    private function exigirCupoEntero(mixed $cupo): int
    {
        if ($cupo === null || $cupo === '') {
            throw new HttpException('El cupo máximo es obligatorio.', 422);
        }
        if (is_bool($cupo) || is_array($cupo)) {
            throw new HttpException('El cupo máximo debe ser un número entero.', 422);
        }
        if (is_string($cupo) && !preg_match('/^-?\d+$/', trim($cupo))) {
            throw new HttpException('El cupo máximo debe ser un número entero.', 422);
        }
        if (is_float($cupo) && floor($cupo) !== $cupo) {
            throw new HttpException('El cupo máximo debe ser un número entero.', 422);
        }

        $entero = (int)$cupo;
        if ($entero < 1) {
            throw new HttpException('El cupo máximo debe ser mayor que cero.', 422);
        }

        return $entero;
    }

    /**
     * @param array<string,mixed> $detalle
     */
    private function exigirPlanAprobado(array $detalle): void
    {
        if (($detalle['plan_estado'] ?? '') !== 'APROBADO') {
            throw new HttpException('El plan no se encuentra aprobado.', 422);
        }
    }

    /**
     * @param array<string,mixed> $datos
     * @param array<string,mixed> $detalle
     */
    private function exigirCapacitacionDelDetalle(array $datos, array $detalle): void
    {
        if (!isset($datos['capacitacion_id']) || $datos['capacitacion_id'] === null || $datos['capacitacion_id'] === '') {
            return;
        }
        if ((int)$datos['capacitacion_id'] !== (int)$detalle['capacitacion_id']) {
            throw new HttpException('La capacitación no pertenece al plan seleccionado.', 422);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function exigirDetalle(int $planDetalleId): array
    {
        $detalle = $this->repo->detallePlan($planDetalleId);
        if ($detalle === null) {
            throw new HttpException('El detalle del plan anual no existe.', 404);
        }

        return $detalle;
    }

    /**
     * @return array<string,mixed>
     */
    private function exigirCatalogo(string $tabla, string $pk, int $id, string $etiqueta): array
    {
        if ($id < 1) {
            throw new HttpException("Debe seleccionar un {$etiqueta} válido.", 422);
        }
        $fila = $this->repo->catalogoActivo($tabla, $pk, $id);
        if ($fila === null) {
            throw new HttpException("El {$etiqueta} seleccionado no existe.", 422);
        }
        if ((int)($fila['activo'] ?? 0) !== 1) {
            throw new HttpException("El {$etiqueta} seleccionado está inactivo.", 422);
        }

        return $fila;
    }

    private function exigirCatalogoId(string $tabla, string $pk, int $id, string $etiqueta): int
    {
        $this->exigirCatalogo($tabla, $pk, $id, $etiqueta);

        return $id;
    }

    /**
     * Debe llamarse dentro de una transacción. Si $yaBloqueada es false, bloquea la sesión.
     *
     * @param list<int> $ids
     */
    private function insertarConvocadosAtomico(
        int $sesionId,
        int $capacitacionId,
        int $cupo,
        array $ids,
        ?int $usuarioId,
        bool $sesionRecienCreada,
        bool $reprogramacion = false
    ): int {
        if (!$sesionRecienCreada) {
            $this->repo->bloquearPorId($sesionId);
        }

        $ya = $this->repo->idsParticipantes($sesionId);
        $yaMapa = array_fill_keys($ya, true);
        $nuevos = [];
        foreach ($ids as $id) {
            if (!isset($yaMapa[$id])) {
                $nuevos[] = $id;
                $yaMapa[$id] = true;
            }
        }

        if ($nuevos === []) {
            throw new HttpException('El trabajador ya está convocado a esta sesión.', 409);
        }

        foreach ($nuevos as $asignacionId) {
            $asig = $this->repo->buscarAsignacion($asignacionId);
            if ($asig === null || $asig['persona_estado'] === null) {
                throw new HttpException('No es posible convocar un trabajador que no existe en Personal Corporativo.', 422);
            }
            if ((int)$asig['capacitacion_id'] !== $capacitacionId) {
                throw new HttpException('El trabajador no tiene una asignación de esta capacitación.', 422);
            }
            if ((string)$asig['persona_estado'] !== 'Activo') {
                throw new HttpException('No es posible convocar a un trabajador inactivo.', 422);
            }
        }

        $actuales = count($ya);
        $disponibles = max(0, $cupo - $actuales);
        if (count($nuevos) > $disponibles) {
            throw new HttpException(
                $reprogramacion
                    ? 'La sesión seleccionada no tiene cupos suficientes para los trabajadores seleccionados.'
                    : $this->mensajeCupo($cupo, $actuales, count($nuevos)),
                422
            );
        }

        $this->repo->insertarParticipantes($sesionId, $nuevos, $usuarioId);

        return count($nuevos);
    }

    private function mensajeCupo(int $cupo, int $actuales, int $nuevos): string
    {
        $disponibles = max(0, $cupo - $actuales);
        if ($disponibles === 0) {
            return "La sesión ya alcanzó el cupo máximo de {$cupo} trabajadores.";
        }

        return "Solo hay {$disponibles} cupos disponibles.";
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizarIds(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        $vistos = [];
        foreach ($raw as $valor) {
            $id = (int)$valor;
            if ($id < 1 || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $ids[] = $id;
        }

        return $ids;
    }

    private function enteroONulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return is_numeric($valor) ? (int)$valor : null;
    }

    private function errorPersistencia(PDOException $e, string $fallback): HttpException
    {
        if ($e->getCode() === self::SQLSTATE_INTEGRIDAD) {
            return new HttpException('El trabajador ya está convocado a esta sesión.', 409);
        }

        return new HttpException($fallback, 500);
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizar(array $fila): array
    {
        $resumen = $this->normalizarResumen($fila);
        $resumen['plan_anual_id'] = $fila['plan_anual_id'] !== null ? (int)$fila['plan_anual_id'] : null;
        $resumen['plan_estado'] = $fila['plan_estado'] ?? null;
        $resumen['anio'] = $fila['anio'] !== null ? (int)$fila['anio'] : null;
        $resumen['observaciones'] = $fila['observaciones'] ?? null;
        $resumen['creado_por_usuario_id_ext'] = $fila['creado_por_usuario_id_ext'] !== null
            ? (int)$fila['creado_por_usuario_id_ext']
            : null;
        $resumen['created_at'] = $fila['created_at'] ?? null;
        $resumen['updated_at'] = $fila['updated_at'] ?? null;

        return $resumen;
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizarResumen(array $fila): array
    {
        $cupo = $fila['cupo_maximo'] !== null ? (int)$fila['cupo_maximo'] : 0;
        $convocados = (int)($fila['convocados'] ?? 0);
        $disponibles = max(0, $cupo - $convocados);
        $fechaHora = (string)($fila['fecha_hora'] ?? '');
        $fecha = $fechaHora !== '' ? substr($fechaHora, 0, 10) : null;
        $hora = $fechaHora !== '' && strlen($fechaHora) >= 16 ? substr($fechaHora, 11, 5) : null;

        return [
            'sesion_id' => (int)$fila['sesion_id'],
            'plan_detalle_id' => $fila['plan_detalle_id'] !== null ? (int)$fila['plan_detalle_id'] : null,
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'capacitacion_codigo' => (string)($fila['capacitacion_codigo'] ?? ''),
            'capacitacion_nombre' => (string)($fila['capacitacion_nombre'] ?? ''),
            'fecha_hora' => $fechaHora,
            'fecha' => $fecha,
            'hora' => $hora,
            'modalidad_id' => (int)$fila['modalidad_id'],
            'modalidad_nombre' => $fila['modalidad_nombre'] !== null && $fila['modalidad_nombre'] !== ''
                ? (string)$fila['modalidad_nombre']
                : null,
            'ubicacion_id' => $fila['ubicacion_id'] !== null ? (int)$fila['ubicacion_id'] : null,
            'ubicacion_nombre' => $fila['ubicacion_nombre'] !== null && $fila['ubicacion_nombre'] !== ''
                ? (string)$fila['ubicacion_nombre']
                : null,
            'enlace_virtual' => $fila['enlace_virtual'] !== null && $fila['enlace_virtual'] !== ''
                ? (string)$fila['enlace_virtual']
                : null,
            'proveedor_id' => $fila['proveedor_id'] !== null ? (int)$fila['proveedor_id'] : null,
            'proveedor_nombre' => $fila['proveedor_nombre'] !== null && $fila['proveedor_nombre'] !== ''
                ? (string)$fila['proveedor_nombre']
                : null,
            'cupo_maximo' => $cupo,
            'convocados' => $convocados,
            'disponibles' => $disponibles,
            'cupo_completo' => $cupo > 0 && $convocados >= $cupo,
            'estado' => (string)($fila['estado'] ?? 'PROGRAMADA'),
            'requiere_certificado' => (int)($fila['capacitacion_certificado'] ?? 0) === 1,
            'requiere_evaluacion' => (int)($fila['capacitacion_evaluacion'] ?? 0) === 1,
            'nota_minima' => isset($fila['capacitacion_nota_minima'])
                ? round((float)$fila['capacitacion_nota_minima'], 2)
                : 0.0,
        ];
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizarParticipante(array $fila): array
    {
        return [
            'sesion_participante_id' => (int)$fila['sesion_participante_id'],
            'asignacion_id' => (int)$fila['asignacion_id'],
            'persona_id_ext' => (int)$fila['persona_id_ext'],
            'persona_nombre' => (string)($fila['persona_nombre'] ?? ''),
            'numero_documento' => (string)($fila['numero_documento'] ?? ''),
            'persona_estado' => $fila['persona_estado'] ?? null,
            'estado_asistencia' => (string)($fila['estado_asistencia'] ?? ''),
            'motivo_ausencia' => $fila['motivo_ausencia'] ?? null,
            'observacion' => $fila['observacion'] ?? null,
            'registrado_por_usuario_id_ext' => isset($fila['registrado_por_usuario_id_ext'])
                && $fila['registrado_por_usuario_id_ext'] !== null
                ? (int)$fila['registrado_por_usuario_id_ext']
                : null,
            'updated_at' => $fila['updated_at'] ?? null,
            'cumplimiento_id' => isset($fila['cumplimiento_id']) && $fila['cumplimiento_id'] !== null
                ? (int)$fila['cumplimiento_id']
                : null,
            'cumplimiento_resultado' => $fila['cumplimiento_resultado'] ?? null,
            'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
            'horas_efectivas' => isset($fila['horas_efectivas']) && $fila['horas_efectivas'] !== null
                ? (float)$fila['horas_efectivas']
                : null,
            'fecha_vencimiento' => $fila['fecha_vencimiento'] ?? null,
            'nota_evaluacion' => isset($fila['nota_evaluacion']) && $fila['nota_evaluacion'] !== null
                ? (float)$fila['nota_evaluacion']
                : null,
        ];
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizarIntento(array $fila): array
    {
        $fechaHora = (string)($fila['fecha_hora'] ?? '');

        return [
            'sesion_participante_id' => (int)$fila['sesion_participante_id'],
            'sesion_id' => (int)$fila['sesion_id'],
            'asignacion_id' => (int)$fila['asignacion_id'],
            'persona_id_ext' => (int)$fila['persona_id_ext'],
            'estado_asistencia' => (string)($fila['estado_asistencia'] ?? ''),
            'motivo_ausencia' => $fila['motivo_ausencia'] ?? null,
            'observacion' => $fila['observacion'] ?? null,
            'fecha_hora' => $fechaHora,
            'fecha' => $fechaHora !== '' ? substr($fechaHora, 0, 10) : null,
            'sesion_estado' => (string)($fila['sesion_estado'] ?? ''),
            'capacitacion_codigo' => (string)($fila['capacitacion_codigo'] ?? ''),
            'capacitacion_nombre' => (string)($fila['capacitacion_nombre'] ?? ''),
            'updated_at' => $fila['updated_at'] ?? null,
        ];
    }

    /**
     * @param list<array<string,mixed>> $participantes
     * @return array{convocados:int,asistieron:int,tarde:int,ausentes:int,pendientes:int}
     */
    private function resumenAsistencia(array $participantes): array
    {
        $resumen = [
            'convocados' => count($participantes),
            'asistieron' => 0,
            'tarde' => 0,
            'ausentes' => 0,
            'pendientes' => 0,
        ];

        foreach ($participantes as $p) {
            $estado = (string)($p['estado_asistencia'] ?? '');
            if ($estado === 'ASISTIO') {
                $resumen['asistieron']++;
            } elseif ($estado === 'TARDE') {
                $resumen['tarde']++;
            } elseif ($estado === 'AUSENTE') {
                $resumen['ausentes']++;
            } else {
                $resumen['pendientes']++;
            }
        }

        return $resumen;
    }

    /**
     * @param mixed $items
     * @param array<int,array<string,mixed>> $porAsig
     * @return list<array{asignacion_id:int,estado_asistencia:string,motivo_ausencia:?string,observacion:?string}>
     */
    private function normalizarItemsAsistencia(mixed $items, array $porAsig): array
    {
        if (!is_array($items)) {
            return [];
        }

        $vistos = [];
        $normalizados = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $asigId = (int)($item['asignacion_id'] ?? 0);
            if ($asigId < 1 || isset($vistos[$asigId])) {
                continue;
            }
            $vistos[$asigId] = true;
            if (!isset($porAsig[$asigId])) {
                throw new HttpException('El trabajador no está convocado a esta sesión.', 422);
            }

            $estado = strtoupper(trim((string)($item['estado_asistencia'] ?? '')));
            if (!in_array($estado, ['CONVOCADO', 'ASISTIO', 'TARDE', 'AUSENTE'], true)) {
                throw new HttpException('El estado de asistencia no es válido.', 422);
            }

            $motivo = nullable_trimmed_string($item['motivo_ausencia'] ?? null);
            $observacion = nullable_trimmed_string($item['observacion'] ?? null);
            if ($observacion !== null) {
                $observacion = $this->recortar($observacion, 500);
            }

            if ($estado === 'AUSENTE') {
                if ($motivo === null || $motivo === '') {
                    throw new HttpException('Debe registrar la razón de ausencia.', 422);
                }
                $motivo = $this->recortar($motivo, 255);
            } else {
                $motivo = null;
            }

            $normalizados[] = [
                'asignacion_id' => $asigId,
                'estado_asistencia' => $estado,
                'motivo_ausencia' => $motivo,
                'observacion' => $observacion,
            ];
        }

        if ($normalizados === []) {
            throw new HttpException('Debe enviar los resultados de asistencia.', 422);
        }

        return $normalizados;
    }

    /**
     * @param array<string,mixed>|null $cap
     */
    private function sincronizarCumplimiento(
        int $sesionId,
        int $asignacionId,
        string $estado,
        string $fechaReal,
        ?array $cap,
        ?int $usuarioId
    ): void {
        $asistio = $estado === 'ASISTIO' || $estado === 'TARDE';
        $existente = $this->repo->cumplimientoPorAsignacion($asignacionId);
        $sesionDelCump = $existente !== null && $existente['sesion_id'] !== null
            ? (int)$existente['sesion_id']
            : null;
        $yaAprobado = $existente !== null
            && strtoupper((string)($existente['resultado'] ?? '')) === 'APROBADO';

        if (!$asistio) {
            if ($existente !== null && $sesionDelCump === $sesionId) {
                $this->soportes->eliminarArchivosDeCumplimiento((int)$existente['cumplimiento_id']);
                $this->repo->borrarCumplimiento((int)$existente['cumplimiento_id']);
            }
            return;
        }

        if ($yaAprobado) {
            return;
        }

        $horas = isset($cap['duracion_estimada_horas']) && $cap['duracion_estimada_horas'] !== null
            ? (float)$cap['duracion_estimada_horas']
            : 0.0;
        $fecha = $fechaReal !== '' ? $fechaReal : date('Y-m-d');
        $vence = $this->vencimiento->fechaVencimientoDeAsignacion($asignacionId, $fecha);
        $campos = [
            'sesion_id' => $sesionId,
            'fecha_realizacion' => $fecha,
            'resultado' => $estado,
            'horas_efectivas' => $horas,
            'fecha_vencimiento' => $vence,
            'registrado_por_usuario_id_ext' => $usuarioId,
        ];

        if ($existente === null) {
            $campos['asignacion_id'] = $asignacionId;
            $this->repo->crearCumplimiento($campos);
            return;
        }

        if ($sesionDelCump === null || $sesionDelCump === $sesionId) {
            $this->repo->actualizarCumplimiento((int)$existente['cumplimiento_id'], $campos);
        }
    }

    private function recortar(string $texto, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_strlen($texto) > $max ? mb_substr($texto, 0, $max) : $texto;
        }

        return strlen($texto) > $max ? substr($texto, 0, $max) : $texto;
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizarConvocable(array $fila): array
    {
        return [
            'asignacion_id' => (int)$fila['asignacion_id'],
            'persona_id_ext' => (int)$fila['persona_id_ext'],
            'persona_nombre' => (string)($fila['persona_nombre'] ?? ''),
            'numero_documento' => (string)($fila['numero_documento'] ?? ''),
            'origen' => (string)($fila['origen'] ?? ''),
            'en_plan' => (int)$fila['en_plan'] === 1,
        ];
    }
}
