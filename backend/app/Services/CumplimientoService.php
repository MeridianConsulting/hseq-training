<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CumplimientoRepository;
use PDOException;

class CumplimientoService
{
    public const RESULTADO_APROBADO = 'APROBADO';
    public const MENSAJE_YA_REGISTRADO = 'El cumplimiento ya fue registrado.';
    public const MENSAJE_NOTA_OBLIGATORIA = 'La nota es obligatoria.';
    public const MENSAJE_NOTA_NUMERICA = 'La nota debe ser numérica.';
    public const MENSAJE_NOTA_RANGO = 'La nota está fuera del rango permitido.';
    public const MENSAJE_NOTA_NO_REQUERIDA = 'Esta capacitación no requiere evaluación.';
    public const MENSAJE_NOTA_MINIMA = 'La nota no alcanza la mínima aprobatoria.';
    public const NOTA_MIN = 0.0;
    public const NOTA_MAX = 5.0;

    private const SQLSTATE_INTEGRIDAD = '23000';
    private const ASISTENCIAS_VALIDAS = ['ASISTIO', 'TARDE'];

    private CumplimientoRepository $repo;
    private VencimientoService $vencimiento;
    private SoporteService $soportes;
    private AuditoriaService $auditoria;

    /** @var array<string,string> */
    public const CAMPOS_AUDITABLES = [
        'fecha_realizacion' => 'Fecha de realización',
        'resultado' => 'Resultado',
        'horas_efectivas' => 'Horas efectivas',
        'fecha_vencimiento' => 'Fecha de vencimiento',
        'observaciones' => 'Observaciones',
        'nota_evaluacion' => 'Nota de evaluación',
    ];

    public function __construct()
    {
        $this->repo = new CumplimientoRepository();
        $this->vencimiento = new VencimientoService();
        $this->soportes = new SoporteService();
        $this->auditoria = new AuditoriaService();
    }

    public function reglasIndividual(): array
    {
        return [
            'asignacion_id' => 'required|integer|min:1',
            'sesion_id' => 'required|integer|min:1',
            'fecha_realizacion' => 'required|date',
            'resultado' => 'required|in:' . self::RESULTADO_APROBADO,
            'horas_efectivas' => 'required|numeric|gt:0',
            'observaciones' => 'nullable|string|max:500',
            'nota_evaluacion' => 'nullable',
        ];
    }

    public function reglasMasivo(): array
    {
        return [
            'sesion_id' => 'required|integer|min:1',
            'asignacion_ids' => 'required|array',
            'fecha_realizacion' => 'required|date',
            'resultado' => 'required|in:' . self::RESULTADO_APROBADO,
            'horas_efectivas' => 'required|numeric|gt:0',
            'observaciones' => 'nullable|string|max:500',
            'notas' => 'nullable',
        ];
    }

    public function reglasEditar(): array
    {
        return [
            'fecha_realizacion' => 'required|date',
            'resultado' => 'nullable|in:' . self::RESULTADO_APROBADO,
            'horas_efectivas' => 'required|numeric|gt:0',
            'observaciones' => 'nullable|string|max:500',
            'nota_evaluacion' => 'nullable',
            'fecha_vencimiento' => 'nullable|date',
        ];
    }

    public function reglasEvaluaciones(): array
    {
        return [
            'sesion_id' => 'required|integer|min:1',
            'items' => 'required|array',
        ];
    }

    public function mensajes(): array
    {
        return [
            'fecha_realizacion.required' => 'La fecha de realización es obligatoria.',
            'fecha_realizacion.date' => 'La fecha de realización no es válida.',
            'resultado.required' => 'El resultado es obligatorio.',
            'resultado.in' => 'El resultado debe ser APROBADO.',
            'horas_efectivas.required' => 'Las horas efectivas son obligatorias.',
            'horas_efectivas.numeric' => 'Las horas efectivas deben ser un valor numérico.',
            'horas_efectivas.gt' => 'Las horas efectivas deben ser mayores que cero.',
            'fecha_vencimiento.date' => 'La fecha de vencimiento no es válida.',
            'asignacion_id.required' => 'Debe indicar la asignación.',
            'sesion_id.required' => 'Debe indicar la sesión.',
            'asignacion_ids.required' => 'Debe seleccionar al menos un trabajador.',
            'items.required' => 'Debe indicar las evaluaciones.',
            'nota_evaluacion.required' => self::MENSAJE_NOTA_OBLIGATORIA,
        ];
    }

    /**
     * @param array{persona_id?:?int, sesion_id?:?int, buscar?:?string, evidencia_faltante?:?int} $filtros
     */
    public function listar(int $pagina, int $porPagina, array $filtros): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $filas = $this->repo->listar($porPagina, $offset, $filtros);
        $items = $this->normalizarConSoportes($filas);

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
            throw new HttpException('El cumplimiento no existe.', 404);
        }

        return $this->normalizarConSoportes([$fila])[0];
    }

    /**
     * @param list<int> $asignacionIds
     * @return array<string,mixed>
     */
    public function previsualizar(int $sesionId, array $asignacionIds, ?string $fechaRealizacion): array
    {
        $sesion = $this->exigirSesion($sesionId);
        $fecha = $this->fechaRealizacion($fechaRealizacion, $sesion);
        $ids = $this->normalizarIds($asignacionIds);

        if ($ids === []) {
            throw new HttpException('Debe seleccionar al menos un trabajador.', 422);
        }

        $items = [];
        $clavesPer = [];
        foreach ($ids as $asignacionId) {
            $preview = $this->previewDe($sesionId, $asignacionId, $fecha);
            $items[] = $preview;
            $clave = ($preview['periodicidad_cantidad'] ?? 'x') . ':' . ($preview['periodicidad_unidad'] ?? '');
            $clavesPer[$clave] = true;
        }

        $distintas = count($clavesPer) > 1;

        return [
            'sesion_id' => $sesionId,
            'fecha_realizacion' => $fecha,
            'items' => $items,
            'periodicidades_distintas' => $distintas,
            'aviso' => $distintas
                ? 'Hay más de una periodicidad en el lote. El vencimiento se calcula por trabajador.'
                : null,
        ];
    }

    /**
     * @param array<string,mixed> $datos
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function registrar(array $datos, ?int $usuarioId, ?array $actor = null): array
    {
        $datos = $this->ignorarCliente($datos);
        unset($datos['fecha_vencimiento']);
        $campos = $this->exigirCampos($datos, false);
        $campos['nota_evaluacion'] = array_key_exists('nota_evaluacion', $datos)
            ? $datos['nota_evaluacion']
            : null;
        $campos['nota_enviada'] = array_key_exists('nota_evaluacion', $datos);

        return $this->repo->transaccion(function () use ($campos, $usuarioId, $actor): array {
            $this->completarUno($campos, $usuarioId);
            $fila = $this->repo->buscarPorAsignacion((int)$campos['asignacion_id']);
            $normalizado = $fila === null ? [] : $this->normalizarConSoportes([$fila])[0];
            if ($actor !== null && $normalizado !== []) {
                $this->auditoria->deActor(
                    $actor,
                    'crear',
                    'cumplimientos_capacitacion',
                    (int)$normalizado['cumplimiento_id'],
                    [
                        'origen' => AuditoriaService::ORIGEN_SISTEMA,
                        'fecha_realizacion' => $normalizado['fecha_realizacion'] ?? null,
                        'resultado' => $normalizado['resultado'] ?? null,
                        'horas_efectivas' => $normalizado['horas_efectivas'] ?? null,
                        'fecha_vencimiento' => $normalizado['fecha_vencimiento'] ?? null,
                    ]
                );
            }

            return $normalizado;
        });
    }

    /**
     * @param array<string,mixed> $datos
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     * @return array{procesados:int,completados:int,errores:int,items:list<array<string,mixed>>,errores_detalle:list<array<string,mixed>>}
     */
    public function registrarMasivo(array $datos, ?int $usuarioId, ?array $actor = null): array
    {
        $datos = $this->ignorarCliente($datos);
        unset($datos['fecha_vencimiento']);
        $sesionId = (int)($datos['sesion_id'] ?? 0);
        $sesion = $this->exigirSesion($sesionId);
        if ((int)($sesion['capacitacion_certificado'] ?? 0) === 1) {
            throw new HttpException(SoporteService::MENSAJE_MASIVO_CERTIFICADO, 422);
        }
        $fecha = $this->fechaRealizacion($datos['fecha_realizacion'] ?? null, null);
        $resultado = $this->exigirResultado($datos['resultado'] ?? null);
        $horas = $this->exigirHoras($datos['horas_efectivas'] ?? null);
        $observaciones = nullable_trimmed_string($datos['observaciones'] ?? null);
        $ids = $this->normalizarIds($datos['asignacion_ids'] ?? []);
        $notas = $this->mapaNotas($datos['notas'] ?? []);
        $requiereEval = (int)($sesion['capacitacion_evaluacion'] ?? 0) === 1;
        $minima = round((float)($sesion['capacitacion_nota_minima'] ?? 0), 2);

        if ($ids === []) {
            throw new HttpException('Debe seleccionar al menos un trabajador.', 422);
        }
        if ($requiereEval) {
            foreach ($ids as $asignacionId) {
                if (!array_key_exists($asignacionId, $notas)) {
                    throw new HttpException(self::MENSAJE_NOTA_OBLIGATORIA, 422);
                }
                $this->exigirNota($notas[$asignacionId]);
            }
        } elseif ($notas !== []) {
            throw new HttpException(self::MENSAJE_NOTA_NO_REQUERIDA, 422);
        }

        $pendientes = [];
        $erroresDetalle = [];
        foreach ($ids as $asignacionId) {
            try {
                $this->validarParticipante($sesionId, $asignacionId, true);
                $pendientes[] = $asignacionId;
            } catch (HttpException $e) {
                $part = $this->repo->participanteEnSesion($sesionId, $asignacionId);
                $erroresDetalle[] = [
                    'asignacion_id' => $asignacionId,
                    'numero_documento' => $part['numero_documento'] ?? null,
                    'motivo' => $e->getMessage(),
                ];
            }
        }

        if ($erroresDetalle !== []) {
            $primero = $erroresDetalle[0];
            $status = str_contains((string)$primero['motivo'], self::MENSAJE_YA_REGISTRADO) ? 409 : 422;
            throw new HttpException((string)$primero['motivo'], $status);
        }

        $completados = [];
        try {
            $this->repo->transaccion(function () use (
                $pendientes,
                $sesionId,
                $fecha,
                $resultado,
                $horas,
                $observaciones,
                $usuarioId,
                $notas,
                $requiereEval,
                $minima,
                $actor,
                &$completados
            ): void {
                foreach ($pendientes as $asignacionId) {
                    $nota = $requiereEval ? $this->exigirNota($notas[$asignacionId] ?? null) : null;
                    $resultadoFila = $resultado;
                    if ($requiereEval && $nota < $minima) {
                        $existente = $this->repo->buscarPorAsignacion($asignacionId);
                        $actual = strtoupper((string)($existente['resultado'] ?? ''));
                        $resultadoFila = $actual !== '' && $actual !== self::RESULTADO_APROBADO
                            ? $actual
                            : 'ASISTIO';
                    }
                    $this->persistir(
                        $sesionId,
                        $asignacionId,
                        $fecha,
                        $resultadoFila,
                        $horas,
                        $observaciones,
                        $usuarioId,
                        $nota,
                        $requiereEval
                    );
                    $fila = $this->repo->buscarPorAsignacion($asignacionId);
                    if ($fila !== null) {
                        $completados[] = $fila;
                    }
                }
                if ($actor !== null && $completados !== []) {
                    $ids = [];
                    foreach ($completados as $fila) {
                        $ids[] = (int)$fila['cumplimiento_id'];
                    }
                    $this->auditoria->deActor(
                        $actor,
                        'registrar_masivo',
                        'cumplimientos_capacitacion',
                        $sesionId,
                        [
                            'origen' => AuditoriaService::ORIGEN_SISTEMA,
                            'sesion_id' => $sesionId,
                            'completados' => count($completados),
                            'cumplimiento_ids' => $ids,
                            'fecha_realizacion' => $fecha,
                        ]
                    );
                }
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (PDOException $e) {
            if ($e->getCode() === self::SQLSTATE_INTEGRIDAD) {
                throw new HttpException(self::MENSAJE_YA_REGISTRADO, 409);
            }
            throw new HttpException('No fue posible registrar los cumplimientos.', 500);
        }

        return [
            'procesados' => count($ids),
            'completados' => count($completados),
            'errores' => 0,
            'items' => $this->normalizarConSoportes($completados),
            'errores_detalle' => [],
        ];
    }

    /**
     * @param array<string,mixed> $datos
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function actualizar(int $id, array $datos, ?int $usuarioId, ?array $actor = null): array
    {
        $datos = $this->ignorarCliente($datos);
        $actual = $this->repo->buscarPorId($id);
        if ($actual === null) {
            throw new HttpException('El cumplimiento no existe.', 404);
        }

        $fecha = $this->fechaRealizacion($datos['fecha_realizacion'] ?? null, null);
        $horas = $this->exigirHoras($datos['horas_efectivas'] ?? null);
        $resultadoEnviado = array_key_exists('resultado', $datos)
            && $datos['resultado'] !== null
            && $datos['resultado'] !== '';
        $resultado = $resultadoEnviado
            ? $this->exigirResultado($datos['resultado'])
            : strtoupper((string)($actual['resultado'] ?? ''));
        $notaEnviada = array_key_exists('nota_evaluacion', $datos);
        $meta = $this->metaEvaluacion($actual, (int)($actual['sesion_id'] ?? 0));
        $nota = $this->resolverNota(
            $datos['nota_evaluacion'] ?? null,
            $notaEnviada,
            $meta,
            $actual['nota_evaluacion'] ?? null
        );
        $this->exigirNotaParaAprobado($resultado, $nota, $meta);
        $this->exigirEvidenciaParaAprobado(
            $resultado,
            $actual,
            (int)($actual['sesion_id'] ?? 0),
            (int)$actual['asignacion_id']
        );
        $observaciones = nullable_trimmed_string($datos['observaciones'] ?? null);
        $venceManual = array_key_exists('fecha_vencimiento', $datos)
            && $datos['fecha_vencimiento'] !== null
            && trim((string)$datos['fecha_vencimiento']) !== '';
        $vence = $venceManual
            ? $this->exigirFechaVencimiento($datos['fecha_vencimiento'])
            : $this->vencimiento->fechaVencimientoDeAsignacion((int)$actual['asignacion_id'], $fecha);

        $campos = [
            'fecha_realizacion' => $fecha,
            'resultado' => $resultado !== '' ? $resultado : ($actual['resultado'] ?? null),
            'horas_efectivas' => $horas,
            'fecha_vencimiento' => $vence,
            'registrado_por_usuario_id_ext' => $usuarioId,
        ];
        if ($observaciones !== null || array_key_exists('observaciones', $datos)) {
            $campos['observaciones'] = $observaciones;
        }
        if ($notaEnviada || ($meta['requiere'] && $nota !== null)) {
            $campos['nota_evaluacion'] = $nota;
        }

        $antes = $this->recorteAuditable($actual);

        return $this->repo->transaccion(function () use ($id, $campos, $antes, $actor, $venceManual): array {
            $this->repo->actualizar($id, $campos);
            $despuesFila = $this->ver($id);
            if ($actor !== null) {
                $cambios = $this->auditoria->diff($antes, $despuesFila, self::CAMPOS_AUDITABLES);
                if ($cambios !== []) {
                    $venceEnDiff = false;
                    foreach ($cambios as $cambio) {
                        if (($cambio['campo'] ?? '') === 'fecha_vencimiento') {
                            $venceEnDiff = true;
                            break;
                        }
                    }
                    $origen = $venceManual || !$venceEnDiff
                        ? AuditoriaService::ORIGEN_USUARIO
                        : AuditoriaService::ORIGEN_SISTEMA;
                    $this->auditoria->deActor(
                        $actor,
                        'actualizar',
                        'cumplimientos_capacitacion',
                        $id,
                        $this->auditoria->payloadNuevo($cambios, $origen),
                        $antes
                    );
                }
            }

            return $despuesFila;
        });
    }

    public function mensajeMasivo(array $resultado): string
    {
        $n = (int)$resultado['completados'];
        if ($n === 1) {
            return '1 cumplimiento registrado.';
        }

        return "{$n} cumplimientos registrados.";
    }

    /**
     * @param array<string,mixed> $datos
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     * @return array{procesados:int,items:list<array<string,mixed>>}
     */
    public function registrarEvaluaciones(array $datos, ?int $usuarioId, ?array $actor = null): array
    {
        $sesionId = (int)($datos['sesion_id'] ?? 0);
        $sesion = $this->exigirSesion($sesionId);
        $requiere = (int)($sesion['capacitacion_evaluacion'] ?? 0) === 1;
        if (!$requiere) {
            throw new HttpException(self::MENSAJE_NOTA_NO_REQUERIDA, 422);
        }

        $items = is_array($datos['items'] ?? null) ? $datos['items'] : [];
        if ($items === []) {
            throw new HttpException('Debe indicar las evaluaciones.', 422);
        }

        $preparados = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $asignacionId = (int)($item['asignacion_id'] ?? 0);
            if ($asignacionId < 1) {
                throw new HttpException('Debe indicar la asignación.', 422);
            }
            $this->validarParticipante($sesionId, $asignacionId, false);
            $nota = $this->exigirNota($item['nota'] ?? $item['nota_evaluacion'] ?? null);
            $existente = $this->repo->buscarPorAsignacion($asignacionId);
            if ($existente === null) {
                throw new HttpException(
                    'Solo se puede registrar la evaluación si el trabajador asistió o llegó tarde.',
                    422
                );
            }
            $resultado = strtoupper((string)($existente['resultado'] ?? ''));
            $minima = round((float)($sesion['capacitacion_nota_minima'] ?? 0), 2);
            if ($resultado === self::RESULTADO_APROBADO && $nota < $minima) {
                throw new HttpException(self::MENSAJE_NOTA_MINIMA, 422);
            }
            $preparados[] = [
                'cumplimiento_id' => (int)$existente['cumplimiento_id'],
                'asignacion_id' => $asignacionId,
                'nota' => $nota,
            ];
        }

        if ($preparados === []) {
            throw new HttpException('Debe indicar las evaluaciones.', 422);
        }

        $filas = [];
        try {
            $this->repo->transaccion(function () use ($preparados, $usuarioId, $actor, $sesionId, &$filas): void {
                foreach ($preparados as $item) {
                    $this->repo->actualizar((int)$item['cumplimiento_id'], [
                        'nota_evaluacion' => $item['nota'],
                        'registrado_por_usuario_id_ext' => $usuarioId,
                    ]);
                    $fila = $this->repo->buscarPorAsignacion((int)$item['asignacion_id']);
                    if ($fila !== null) {
                        $filas[] = $fila;
                    }
                }
                if ($actor !== null && $preparados !== []) {
                    $this->auditoria->deActor(
                        $actor,
                        'registrar_evaluaciones',
                        'cumplimientos_capacitacion',
                        $sesionId,
                        [
                            'origen' => AuditoriaService::ORIGEN_USUARIO,
                            'sesion_id' => $sesionId,
                            'procesados' => count($preparados),
                            'cumplimiento_ids' => array_map(
                                static fn (array $item): int => (int)$item['cumplimiento_id'],
                                $preparados
                            ),
                        ]
                    );
                }
            });
        } catch (HttpException $e) {
            throw $e;
        } catch (PDOException $e) {
            throw new HttpException('No fue posible registrar la evaluación.', 500);
        }

        return [
            'procesados' => count($preparados),
            'items' => $this->normalizarConSoportes($filas),
        ];
    }

    public function mensajeEvaluaciones(array $resultado): string
    {
        $n = (int)$resultado['procesados'];
        if ($n === 1) {
            return '1 evaluación registrada.';
        }

        return "{$n} evaluaciones registradas.";
    }

    /**
     * @param array<string,mixed> $datos
     * @return array{asignacion_id:int,sesion_id:int,fecha_realizacion:string,resultado:string,horas_efectivas:float,observaciones:?string}
     */
    private function exigirCampos(array $datos, bool $masivo): array
    {
        $asignacionId = (int)($datos['asignacion_id'] ?? 0);
        $sesionId = (int)($datos['sesion_id'] ?? 0);
        if (!$masivo && $asignacionId < 1) {
            throw new HttpException('Debe indicar la asignación.', 422);
        }
        if ($sesionId < 1) {
            throw new HttpException('Debe indicar la sesión.', 422);
        }

        return [
            'asignacion_id' => $asignacionId,
            'sesion_id' => $sesionId,
            'fecha_realizacion' => $this->fechaRealizacion($datos['fecha_realizacion'] ?? null, null),
            'resultado' => $this->exigirResultado($datos['resultado'] ?? null),
            'horas_efectivas' => $this->exigirHoras($datos['horas_efectivas'] ?? null),
            'observaciones' => nullable_trimmed_string($datos['observaciones'] ?? null),
        ];
    }

    /**
     * @param array{asignacion_id:int,sesion_id:int,fecha_realizacion:string,resultado:string,horas_efectivas:float,observaciones:?string} $campos
     */
    private function completarUno(array $campos, ?int $usuarioId): void
    {
        $this->exigirSesion((int)$campos['sesion_id']);
        $this->validarParticipante((int)$campos['sesion_id'], (int)$campos['asignacion_id'], true);
        $notaEnviada = !empty($campos['nota_enviada']);
        $notaBruta = $campos['nota_evaluacion'] ?? null;
        $this->persistir(
            (int)$campos['sesion_id'],
            (int)$campos['asignacion_id'],
            $campos['fecha_realizacion'],
            $campos['resultado'],
            $campos['horas_efectivas'],
            $campos['observaciones'],
            $usuarioId,
            $notaEnviada || $notaBruta !== null ? $notaBruta : null,
            $notaEnviada || $notaBruta !== null
        );
    }

    private function persistir(
        int $sesionId,
        int $asignacionId,
        string $fecha,
        string $resultado,
        float $horas,
        ?string $observaciones,
        ?int $usuarioId,
        mixed $notaBruta = null,
        bool $escribirNota = false
    ): void {
        $existente = $this->repo->buscarPorAsignacion($asignacionId);
        $meta = $this->metaEvaluacion($existente, $sesionId);
        $nota = $this->resolverNota(
            $notaBruta,
            $escribirNota,
            $meta,
            $existente['nota_evaluacion'] ?? null
        );
        $this->exigirNotaParaAprobado($resultado, $nota, $meta);
        $this->exigirEvidenciaParaAprobado($resultado, $existente, $sesionId, $asignacionId);
        $vence = $this->vencimiento->fechaVencimientoDeAsignacion($asignacionId, $fecha);
        $campos = [
            'sesion_id' => $sesionId,
            'fecha_realizacion' => $fecha,
            'resultado' => $resultado,
            'horas_efectivas' => $horas,
            'fecha_vencimiento' => $vence,
            'observaciones' => $observaciones,
            'registrado_por_usuario_id_ext' => $usuarioId,
        ];
        if ($escribirNota || ($meta['requiere'] && $nota !== null)) {
            $campos['nota_evaluacion'] = $nota;
        }

        if ($existente === null) {
            $campos['asignacion_id'] = $asignacionId;
            $this->repo->crear($campos);
            return;
        }

        $this->repo->actualizar((int)$existente['cumplimiento_id'], $campos);
    }

    private function exigirEvidenciaParaAprobado(
        string $resultado,
        ?array $existente,
        int $sesionId,
        int $asignacionId
    ): void {
        if ($resultado !== self::RESULTADO_APROBADO) {
            return;
        }

        $capId = (int)($existente['capacitacion_id'] ?? 0);
        if ($capId < 1) {
            $part = $this->repo->participanteEnSesion($sesionId, $asignacionId);
            $capId = (int)($part['capacitacion_id'] ?? 0);
        }
        if ($capId < 1 || !$this->soportes->requiereCertificadoPorCapacitacion($capId)) {
            return;
        }

        $count = $existente !== null ? $this->soportes->contar((int)$existente['cumplimiento_id']) : 0;
        if ($count === 0) {
            throw new HttpException(SoporteService::MENSAJE_REQUIERE_CERTIFICADO, 422);
        }
    }

    private function validarParticipante(int $sesionId, int $asignacionId, bool $bloquearAprobado): void
    {
        $part = $this->repo->participanteEnSesion($sesionId, $asignacionId);
        if ($part === null) {
            throw new HttpException('El trabajador no está convocado a esta sesión.', 422);
        }

        $asistencia = strtoupper((string)($part['estado_asistencia'] ?? ''));
        if (!in_array($asistencia, self::ASISTENCIAS_VALIDAS, true)) {
            throw new HttpException(
                'Solo se puede registrar cumplimiento si el trabajador asistió o llegó tarde.',
                422
            );
        }

        if (!$bloquearAprobado) {
            return;
        }

        $existente = $this->repo->buscarPorAsignacion($asignacionId);
        if ($existente !== null && strtoupper((string)($existente['resultado'] ?? '')) === self::RESULTADO_APROBADO) {
            throw new HttpException(self::MENSAJE_YA_REGISTRADO, 409);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function previewDe(int $sesionId, int $asignacionId, string $fecha): array
    {
        $part = $this->repo->participanteEnSesion($sesionId, $asignacionId);
        $existente = $this->repo->buscarPorAsignacion($asignacionId);
        $per = $this->vencimiento->resolverPeriodicidad($asignacionId);
        $vence = VencimientoService::calcularFechaVencimiento($fecha, $per['cantidad'], $per['unidad']);
        $asistencia = $part !== null ? strtoupper((string)$part['estado_asistencia']) : '';
        $resultadoActual = $existente !== null ? strtoupper((string)($existente['resultado'] ?? '')) : '';
        $puede = $part !== null
            && in_array($asistencia, self::ASISTENCIAS_VALIDAS, true)
            && $resultadoActual !== self::RESULTADO_APROBADO;

        $motivo = null;
        if ($part === null) {
            $motivo = 'El trabajador no está convocado a esta sesión.';
        } elseif (!in_array($asistencia, self::ASISTENCIAS_VALIDAS, true)) {
            $motivo = 'Solo se puede registrar cumplimiento si el trabajador asistió o llegó tarde.';
        } elseif ($resultadoActual === self::RESULTADO_APROBADO) {
            $motivo = self::MENSAJE_YA_REGISTRADO;
        }

        $capId = (int)($part['capacitacion_id'] ?? $existente['capacitacion_id'] ?? 0);
        $requiereCert = $capId > 0 && $this->soportes->requiereCertificadoPorCapacitacion($capId);
        $meta = $this->metaEvaluacion($existente, $sesionId);
        $nota = $existente !== null && $existente['nota_evaluacion'] !== null
            ? (float)$existente['nota_evaluacion']
            : null;
        $cumplimientoId = $existente !== null ? (int)$existente['cumplimiento_id'] : null;
        $soportesCount = $cumplimientoId !== null ? $this->soportes->contar($cumplimientoId) : 0;

        return [
            'asignacion_id' => $asignacionId,
            'cumplimiento_id' => $cumplimientoId,
            'numero_documento' => $part['numero_documento'] ?? null,
            'persona_nombre' => $part['persona_nombre'] ?? null,
            'estado_asistencia' => $part['estado_asistencia'] ?? null,
            'resultado_actual' => $existente['resultado'] ?? null,
            'periodicidad_nombre' => $per['nombre'],
            'periodicidad_cantidad' => $per['cantidad'],
            'periodicidad_unidad' => $per['unidad'],
            'origen_periodicidad' => $per['origen'],
            'etiqueta_periodicidad' => $per['etiqueta'],
            'fecha_vencimiento' => $vence,
            'etiqueta_vencimiento' => $vence === null ? 'Sin vencimiento' : $vence,
            'puede_registrar' => $puede,
            'motivo' => $motivo,
            'requiere_certificado' => $requiereCert,
            'soportes_count' => $soportesCount,
            'requiere_evaluacion' => $meta['requiere'],
            'nota_minima' => $meta['minima'],
            'nota_evaluacion' => $nota,
            'evaluacion_aprobada' => $this->evaluacionAprobada($nota, $meta['requiere'], $meta['minima']),
        ];
    }

    /** @return array<string,mixed> */
    private function exigirSesion(int $sesionId): array
    {
        if ($sesionId < 1) {
            throw new HttpException('Debe indicar la sesión.', 422);
        }
        $sesion = $this->repo->sesionPorId($sesionId);
        if ($sesion === null) {
            throw new HttpException('La sesión no existe.', 404);
        }
        if (($sesion['estado'] ?? '') === 'CANCELADA') {
            throw new HttpException('No es posible registrar cumplimiento en una sesión cancelada.', 409);
        }

        return $sesion;
    }

    /**
     * @param array<string,mixed>|null $sesion
     */
    private function fechaRealizacion(mixed $valor, ?array $sesion): string
    {
        $texto = is_string($valor) ? trim($valor) : '';
        if ($texto === '' && $sesion !== null) {
            $texto = substr((string)($sesion['fecha_hora'] ?? ''), 0, 10);
        }
        if ($texto === '') {
            throw new HttpException('La fecha de realización es obligatoria.', 422);
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $texto);
        if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $texto) {
            $ts = strtotime($texto);
            if (!$ts) {
                throw new HttpException('La fecha de realización no es válida.', 422);
            }
            $texto = date('Y-m-d', $ts);
        }

        return $texto;
    }

    private function exigirResultado(mixed $valor): string
    {
        $resultado = strtoupper(trim((string)$valor));
        if ($resultado === '') {
            throw new HttpException('El resultado es obligatorio.', 422);
        }
        if ($resultado !== self::RESULTADO_APROBADO) {
            throw new HttpException('El resultado debe ser APROBADO.', 422);
        }

        return $resultado;
    }

    private function exigirHoras(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            throw new HttpException('Las horas efectivas son obligatorias.', 422);
        }
        if (!is_numeric($valor)) {
            throw new HttpException('Las horas efectivas deben ser un valor numérico.', 422);
        }
        $horas = (float)$valor;
        if ($horas <= 0) {
            throw new HttpException('Las horas efectivas deben ser mayores que cero.', 422);
        }

        return round($horas, 2);
    }

    private function exigirNota(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            throw new HttpException(self::MENSAJE_NOTA_OBLIGATORIA, 422);
        }
        if (!is_numeric($valor)) {
            throw new HttpException(self::MENSAJE_NOTA_NUMERICA, 422);
        }
        $nota = round((float)$valor, 2);
        if ($nota < self::NOTA_MIN || $nota > self::NOTA_MAX) {
            throw new HttpException(self::MENSAJE_NOTA_RANGO, 422);
        }

        return $nota;
    }

    /**
     * @param array{requiere:bool,minima:float} $meta
     */
    private function resolverNota(mixed $valor, bool $enviada, array $meta, mixed $actual): ?float
    {
        if (!$meta['requiere']) {
            if ($enviada && $valor !== null && $valor !== '') {
                throw new HttpException(self::MENSAJE_NOTA_NO_REQUERIDA, 422);
            }

            return $actual !== null && $actual !== '' ? (float)$actual : null;
        }

        if ($enviada) {
            return $this->exigirNota($valor);
        }

        return $actual !== null && $actual !== '' ? round((float)$actual, 2) : null;
    }

    /**
     * @param array{requiere:bool,minima:float} $meta
     */
    private function exigirNotaParaAprobado(string $resultado, ?float $nota, array $meta): void
    {
        if ($resultado !== self::RESULTADO_APROBADO || !$meta['requiere']) {
            return;
        }
        if ($nota === null) {
            throw new HttpException(self::MENSAJE_NOTA_OBLIGATORIA, 422);
        }
        if ($nota < $meta['minima']) {
            throw new HttpException(self::MENSAJE_NOTA_MINIMA, 422);
        }
    }

    /**
     * @return array{requiere:bool,minima:float}
     */
    private function metaEvaluacion(?array $existente, int $sesionId): array
    {
        if ($existente !== null && array_key_exists('capacitacion_evaluacion', $existente)) {
            return [
                'requiere' => (int)($existente['capacitacion_evaluacion'] ?? 0) === 1,
                'minima' => round((float)($existente['capacitacion_nota_minima'] ?? 0), 2),
            ];
        }

        $sesion = $sesionId > 0 ? $this->repo->sesionPorId($sesionId) : null;

        return [
            'requiere' => (int)($sesion['capacitacion_evaluacion'] ?? 0) === 1,
            'minima' => round((float)($sesion['capacitacion_nota_minima'] ?? 0), 2),
        ];
    }

    private function evaluacionAprobada(?float $nota, bool $requiere, float $minima): ?bool
    {
        if (!$requiere || $nota === null) {
            return null;
        }

        return $nota >= $minima;
    }

    /**
     * @return array<int,mixed>
     */
    private function mapaNotas(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $mapa = [];
        foreach ($raw as $clave => $valor) {
            $id = (int)$clave;
            if ($id < 1) {
                continue;
            }
            $mapa[$id] = $valor;
        }

        return $mapa;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizarIds(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $raw = explode(',', $raw);
        }
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

    /**
     * @param array<string,mixed> $datos
     * @return array<string,mixed>
     */
    private function ignorarCliente(array $datos): array
    {
        unset(
            $datos['usuario_id'],
            $datos['usuario_id_ext'],
            $datos['valor_anterior'],
            $datos['valor_nuevo'],
            $datos['created_at'],
            $datos['ip']
        );

        return $datos;
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function recorteAuditable(array $fila): array
    {
        $recorte = [];
        foreach (self::CAMPOS_AUDITABLES as $campo => $etiqueta) {
            $recorte[$campo] = $fila[$campo] ?? null;
        }

        return $recorte;
    }

    private function exigirFechaVencimiento(mixed $valor): string
    {
        $texto = is_string($valor) ? trim($valor) : '';
        if ($texto === '') {
            throw new HttpException('La fecha de vencimiento no es válida.', 422);
        }
        $texto = substr($texto, 0, 10);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $texto);
        if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $texto) {
            throw new HttpException('La fecha de vencimiento no es válida.', 422);
        }

        return $texto;
    }

    /**
     * @param list<array<string,mixed>> $filas
     * @return list<array<string,mixed>>
     */
    private function normalizarConSoportes(array $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            if (isset($fila['cumplimiento_id'])) {
                $ids[] = (int)$fila['cumplimiento_id'];
            }
        }
        $por = $this->soportes->porCumplimientos($ids);
        $items = [];
        foreach ($filas as $fila) {
            $cid = (int)($fila['cumplimiento_id'] ?? 0);
            $fila['soportes'] = $por[$cid] ?? [];
            $items[] = $this->normalizar($fila);
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizar(array $fila): array
    {
        if ($fila === []) {
            return [];
        }

        $soportes = is_array($fila['soportes'] ?? null) ? $fila['soportes'] : [];
        $resultado = strtoupper((string)($fila['resultado'] ?? ''));
        $calculado = strtoupper((string)($fila['estado_calculado'] ?? ''));
        $vigencia = 'PENDIENTE';
        if ($resultado === self::RESULTADO_APROBADO) {
            $vigencia = in_array($calculado, ['PROXIMA_A_VENCER', 'VENCIDA', 'COMPLETADA'], true)
                ? $calculado
                : 'COMPLETADA';
        }

        return [
            'cumplimiento_id' => (int)$fila['cumplimiento_id'],
            'asignacion_id' => (int)$fila['asignacion_id'],
            'sesion_id' => $fila['sesion_id'] !== null ? (int)$fila['sesion_id'] : null,
            'persona_id_ext' => isset($fila['persona_id_ext']) ? (int)$fila['persona_id_ext'] : null,
            'persona_nombre' => $fila['persona_nombre'] ?? null,
            'numero_documento' => $fila['numero_documento'] ?? null,
            'capacitacion_id' => isset($fila['capacitacion_id']) ? (int)$fila['capacitacion_id'] : null,
            'capacitacion_codigo' => $fila['capacitacion_codigo'] ?? null,
            'capacitacion_nombre' => $fila['capacitacion_nombre'] ?? null,
            'requiere_certificado' => (int)($fila['capacitacion_certificado'] ?? 0) === 1,
            'requiere_evaluacion' => (int)($fila['capacitacion_evaluacion'] ?? 0) === 1,
            'nota_minima' => isset($fila['capacitacion_nota_minima'])
                ? round((float)$fila['capacitacion_nota_minima'], 2)
                : 0.0,
            'nota_evaluacion' => $fila['nota_evaluacion'] !== null ? (float)$fila['nota_evaluacion'] : null,
            'evaluacion_aprobada' => $this->evaluacionAprobada(
                $fila['nota_evaluacion'] !== null ? (float)$fila['nota_evaluacion'] : null,
                (int)($fila['capacitacion_evaluacion'] ?? 0) === 1,
                round((float)($fila['capacitacion_nota_minima'] ?? 0), 2)
            ),
            'fecha_realizacion' => $fila['fecha_realizacion'] ?? null,
            'resultado' => $fila['resultado'] ?? null,
            'horas_efectivas' => $fila['horas_efectivas'] !== null ? (float)$fila['horas_efectivas'] : null,
            'fecha_vencimiento' => $fila['fecha_vencimiento'] ?? null,
            'observaciones' => $fila['observaciones'] ?? null,
            'registrado_por_usuario_id_ext' => $fila['registrado_por_usuario_id_ext'] !== null
                ? (int)$fila['registrado_por_usuario_id_ext']
                : null,
            'estado_calculado' => $calculado !== '' ? $calculado : null,
            'estado_vigencia' => $vigencia,
            'soportes_count' => count($soportes),
            'soportes' => $soportes,
            'created_at' => $fila['created_at'] ?? null,
            'updated_at' => $fila['updated_at'] ?? null,
        ];
    }
}
