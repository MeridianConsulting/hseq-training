<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CapacitacionRepository;
use PDOException;

class CapacitacionService
{
    private const SQLSTATE_INTEGRIDAD = '23000';
    public const MENSAJE_CODIGO_DUPLICADO = 'Este código de capacitación ya está registrado.';
    public const MENSAJE_CREADA = 'Capacitación creada correctamente.';
    public const MENSAJE_ACTUALIZADA = 'Capacitación actualizada correctamente.';
    public const MENSAJE_ELIMINADA = 'Capacitación eliminada';
    public const MENSAJE_INACTIVADA = 'La capacitación está en uso; se inactivó para conservar el histórico';
    public const MENSAJE_YA_INACTIVA = 'La capacitación ya está inactiva y conserva el histórico.';

    /** @var array<string,string> */
    public const CAMPOS_AUDITABLES = [
        'codigo' => 'Código',
        'nombre' => 'Nombre',
        'objetivo' => 'Objetivo',
        'duracion_estimada_horas' => 'Duración',
        'criticidad' => 'Criticidad',
        'es_tarea_critica' => 'Tarea crítica',
        'evaluacion' => 'Evaluación',
        'nota_minima' => 'Nota mínima',
        'certificado' => 'Certificado',
        'estado' => 'Estado',
    ];

    private const FKS = [
        'categoria_id' => ['tabla' => 'categorias_capacitacion', 'pk' => 'categoria_id', 'etiqueta' => 'categoría'],
        'tipo_capacitacion_id' => ['tabla' => 'tipos_capacitacion', 'pk' => 'tipo_capacitacion_id', 'etiqueta' => 'tipo'],
        'proveedor_default_id' => ['tabla' => 'proveedores_capacitadores', 'pk' => 'proveedor_id', 'etiqueta' => 'proveedor'],
        'periodicidad_default_id' => ['tabla' => 'periodicidades', 'pk' => 'periodicidad_id', 'etiqueta' => 'periodicidad'],
        'vigencia_id' => ['tabla' => 'vigencias', 'pk' => 'vigencia_id', 'etiqueta' => 'vigencia'],
        'modalidad_default_id' => ['tabla' => 'modalidades', 'pk' => 'modalidad_id', 'etiqueta' => 'modalidad'],
        'fuente_normativa_id' => ['tabla' => 'fuentes_normativas', 'pk' => 'fuente_normativa_id', 'etiqueta' => 'fuente normativa'],
    ];

    private CapacitacionRepository $repo;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->repo = new CapacitacionRepository();
        $this->auditoria = new AuditoriaService();
    }

    public function reglas(bool $esActualizacion = false): array
    {
        $codigo = $esActualizacion ? 'nullable|string|max:30' : 'required|string|max:30';

        return [
            'codigo' => $codigo,
            'nombre' => 'required|string|max:180',
            'objetivo' => 'required|string',
            'descripcion_temario' => 'nullable|string',
            'categoria_id' => 'nullable|integer',
            'tipo_capacitacion_id' => 'required|integer',
            'duracion_estimada_horas' => 'required|numeric|gt:0',
            'criticidad' => 'nullable|in:BAJA,MEDIA,ALTA',
            'es_tarea_critica' => 'nullable|integer|min:0|max:1',
            'responsable' => 'nullable|string|max:120',
            'proveedor_default_id' => 'nullable|integer',
            'periodicidad_default_id' => 'nullable|integer',
            'vigencia_id' => 'nullable|integer',
            'modalidad_default_id' => 'required|integer',
            'evaluacion' => 'nullable|integer|min:0|max:1',
            'nota_minima' => 'nullable|numeric|min:0',
            'certificado' => 'nullable|integer|min:0|max:1',
            'requiere_listado_asistencia' => 'nullable|integer|min:0|max:1',
            'fuente_normativa_id' => 'nullable|integer',
            'estado' => 'nullable|in:ACTIVA,INACTIVA',
        ];
    }

    public function mensajes(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'nombre.required' => 'El nombre es obligatorio.',
            'objetivo.required' => 'El objetivo es obligatorio.',
            'duracion_estimada_horas.required' => 'La duración es obligatoria.',
            'duracion_estimada_horas.numeric' => 'La duración debe ser un valor numérico.',
            'duracion_estimada_horas.gt' => 'La duración debe ser mayor que cero.',
            'tipo_capacitacion_id.required' => 'El tipo de capacitación es obligatorio.',
            'tipo_capacitacion_id.integer' => 'El tipo de capacitación no es válido.',
            'modalidad_default_id.required' => 'La modalidad es obligatoria.',
            'modalidad_default_id.integer' => 'La modalidad no es válida.',
            'vigencia_id.integer' => 'La vigencia no es válida.',
            'es_tarea_critica.min' => 'La tarea crítica solo admite Sí o No.',
            'es_tarea_critica.max' => 'La tarea crítica solo admite Sí o No.',
            'evaluacion.min' => 'Requiere evaluación solo admite Sí o No.',
            'evaluacion.max' => 'Requiere evaluación solo admite Sí o No.',
            'nota_minima.numeric' => 'La nota mínima debe ser un valor numérico.',
            'nota_minima.min' => 'La nota mínima no puede ser negativa.',
        ];
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function listar(int $pagina, int $porPagina, array $filtros): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;
        $limpios = $this->normalizarFiltros($filtros);

        $items = array_map(
            [$this, 'normalizar'],
            $this->repo->listar($porPagina, $offset, $limpios)
        );

        return [
            'items' => $items,
            'total' => $this->repo->contar($limpios),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    public function ver(int $id): array
    {
        $fila = $this->repo->buscarPorId($id);

        if ($fila === null) {
            throw new HttpException('Capacitación no encontrada', 404);
        }

        return $this->normalizar($fila);
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function crear(array $datos, int $usuarioId, ?array $actor = null): array
    {
        $datos = $this->preparar($datos);

        if ($this->repo->codigoDuplicado((string)$datos['codigo'])) {
            throw new HttpException(self::MENSAJE_CODIGO_DUPLICADO, 409);
        }

        $this->validarFks($datos);
        $this->exigirTipoYModalidad($datos);
        $datos = $this->aplicarReglasEvaluacion($datos);

        $datos['creado_por_usuario_id_ext'] = $usuarioId;

        return $this->repo->transaccion(function () use ($datos, $actor): array {
            $id = $this->repo->crear($datos);
            $creado = $this->ver($id);
            if ($actor !== null) {
                $this->auditoria->deActor($actor, 'crear', 'capacitaciones', $id, $creado);
            }

            return $creado;
        });
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function actualizar(int $id, array $datos, ?array $actor = null): array
    {
        $antes = $this->ver($id);
        $datos = $this->preparar($datos, true);

        if (isset($datos['codigo']) && $this->repo->codigoDuplicado((string)$datos['codigo'], $id)) {
            throw new HttpException(self::MENSAJE_CODIGO_DUPLICADO, 409);
        }

        $this->validarFks($datos);
        $this->exigirTipoYModalidad($datos);
        $datos = $this->aplicarReglasEvaluacion($datos, $antes);

        if ($datos === []) {
            return $antes;
        }

        return $this->repo->transaccion(function () use ($id, $datos, $antes, $actor): array {
            $this->repo->actualizar($id, $datos);
            $despues = $this->ver($id);
            if ($actor !== null) {
                $cambios = $this->auditoria->diff($antes, $despues, self::CAMPOS_AUDITABLES);
                if ($cambios !== []) {
                    $this->auditoria->deActor(
                        $actor,
                        'actualizar',
                        'capacitaciones',
                        $id,
                        $this->auditoria->payloadNuevo($cambios, AuditoriaService::ORIGEN_USUARIO),
                        $this->recorte($antes, self::CAMPOS_AUDITABLES)
                    );
                }
            }

            return $despues;
        });
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function eliminar(int $id, ?array $actor = null): string
    {
        $antes = $this->ver($id);

        if (($antes['estado'] ?? '') === 'INACTIVA') {
            return self::MENSAJE_YA_INACTIVA;
        }

        try {
            return $this->repo->transaccion(function () use ($id, $antes, $actor): string {
                $this->repo->eliminar($id);
                if ($actor !== null) {
                    $this->auditoria->deActor(
                        $actor,
                        'eliminar',
                        'capacitaciones',
                        $id,
                        ['mensaje' => self::MENSAJE_ELIMINADA],
                        $antes
                    );
                }

                return self::MENSAJE_ELIMINADA;
            });
        } catch (PDOException $e) {
            if ($e->getCode() === self::SQLSTATE_INTEGRIDAD) {
                $this->repo->inactivar($id);
                if ($actor !== null) {
                    $despues = $this->ver($id);
                    $cambios = $this->auditoria->diff($antes, $despues, ['estado' => 'Estado']);
                    $this->auditoria->deActor(
                        $actor,
                        'inactivar',
                        'capacitaciones',
                        $id,
                        $this->auditoria->payloadNuevo($cambios, AuditoriaService::ORIGEN_USUARIO),
                        ['estado' => $antes['estado'] ?? null]
                    );
                }

                return self::MENSAJE_INACTIVADA;
            }

            throw $e;
        }
    }

    /**
     * @param array<string,string> $campos
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function recorte(array $fila, array $campos): array
    {
        $out = [];
        foreach (array_keys($campos) as $clave) {
            $out[$clave] = $fila[$clave] ?? null;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    private function normalizarFiltros(array $filtros): array
    {
        $estado = isset($filtros['estado']) ? trim((string)$filtros['estado']) : '';
        if ($estado !== '' && !in_array($estado, ['ACTIVA', 'INACTIVA'], true)) {
            throw new HttpException('El estado debe ser ACTIVA o INACTIVA.', 422);
        }

        $tarea = $this->filtroBinario($filtros['es_tarea_critica'] ?? null);
        $evaluacion = $this->filtroBinario($filtros['evaluacion'] ?? null);

        return [
            'buscar' => isset($filtros['buscar']) && trim((string)$filtros['buscar']) !== ''
                ? trim((string)$filtros['buscar'])
                : null,
            'estado' => $estado !== '' ? $estado : null,
            'tipo_capacitacion_id' => isset($filtros['tipo_capacitacion_id']) && (int)$filtros['tipo_capacitacion_id'] > 0
                ? (int)$filtros['tipo_capacitacion_id']
                : null,
            'modalidad_default_id' => isset($filtros['modalidad_default_id']) && (int)$filtros['modalidad_default_id'] > 0
                ? (int)$filtros['modalidad_default_id']
                : null,
            'es_tarea_critica' => $tarea,
            'evaluacion' => $evaluacion,
        ];
    }

    private function filtroBinario(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return (int)$valor === 1 ? 1 : 0;
    }

    private function preparar(array $datos, bool $parcial = false): array
    {
        $enteros = [
            'categoria_id',
            'tipo_capacitacion_id',
            'proveedor_default_id',
            'periodicidad_default_id',
            'vigencia_id',
            'modalidad_default_id',
            'fuente_normativa_id',
            'es_tarea_critica',
            'evaluacion',
            'certificado',
            'requiere_listado_asistencia',
        ];

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

        if (array_key_exists('duracion_estimada_horas', $datos) && $datos['duracion_estimada_horas'] !== null) {
            $datos['duracion_estimada_horas'] = (float)$datos['duracion_estimada_horas'];
        }

        if (array_key_exists('nota_minima', $datos) && $datos['nota_minima'] !== null && $datos['nota_minima'] !== '') {
            $datos['nota_minima'] = (float)$datos['nota_minima'];
        }

        if (!$parcial && (!array_key_exists('criticidad', $datos) || $datos['criticidad'] === null || $datos['criticidad'] === '')) {
            $datos['criticidad'] = 'MEDIA';
        }

        if ($parcial) {
            foreach ($datos as $clave => $valor) {
                if ($valor === null && $clave === 'codigo') {
                    unset($datos[$clave]);
                }
            }
        }

        return $datos;
    }

    /**
     * @param array<string,mixed> $datos
     * @param array<string,mixed>|null $antes
     * @return array<string,mixed>
     */
    private function aplicarReglasEvaluacion(array $datos, ?array $antes = null): array
    {
        $evaluacion = 0;
        if (array_key_exists('evaluacion', $datos)) {
            $evaluacion = (int)$datos['evaluacion'];
        } elseif ($antes !== null && !empty($antes['evaluacion'])) {
            $evaluacion = 1;
        }

        if ($evaluacion === 1) {
            $nota = $datos['nota_minima'] ?? ($antes['nota_minima'] ?? null);
            if ($nota === null || $nota === '') {
                throw new HttpException('La nota mínima es obligatoria cuando la capacitación requiere evaluación.', 422);
            }
            $datos['nota_minima'] = (float)$nota;
        } else {
            $datos['nota_minima'] = 0.0;
        }

        return $datos;
    }

    private function exigirTipoYModalidad(array $datos): void
    {
        if (array_key_exists('tipo_capacitacion_id', $datos) && empty($datos['tipo_capacitacion_id'])) {
            throw new HttpException('El tipo de capacitación es obligatorio.', 422);
        }
        if (array_key_exists('modalidad_default_id', $datos) && empty($datos['modalidad_default_id'])) {
            throw new HttpException('La modalidad es obligatoria.', 422);
        }
    }

    private function validarFks(array $datos): void
    {
        foreach (self::FKS as $campo => $def) {
            if (empty($datos[$campo])) {
                continue;
            }

            if (!$this->repo->catalogoExiste($def['tabla'], $def['pk'], (int)$datos[$campo])) {
                throw new HttpException("La {$def['etiqueta']} seleccionada no existe", 422);
            }
        }
    }

    private function normalizar(array $fila): array
    {
        return [
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'codigo' => $fila['codigo'],
            'nombre' => $fila['nombre'],
            'objetivo' => $fila['objetivo'],
            'descripcion_temario' => $fila['descripcion_temario'],
            'categoria_id' => $fila['categoria_id'] !== null ? (int)$fila['categoria_id'] : null,
            'categoria_nombre' => $fila['categoria_nombre'] ?? null,
            'tipo_capacitacion_id' => $fila['tipo_capacitacion_id'] !== null ? (int)$fila['tipo_capacitacion_id'] : null,
            'tipo_nombre' => $fila['tipo_nombre'] ?? null,
            'duracion_estimada_horas' => $fila['duracion_estimada_horas'] !== null ? (float)$fila['duracion_estimada_horas'] : null,
            'criticidad' => $fila['criticidad'],
            'es_tarea_critica' => (int)$fila['es_tarea_critica'] === 1,
            'responsable' => $fila['responsable'],
            'proveedor_default_id' => $fila['proveedor_default_id'] !== null ? (int)$fila['proveedor_default_id'] : null,
            'proveedor_nombre' => $fila['proveedor_nombre'] ?? null,
            'periodicidad_default_id' => $fila['periodicidad_default_id'] !== null ? (int)$fila['periodicidad_default_id'] : null,
            'periodicidad_nombre' => $fila['periodicidad_nombre'] ?? null,
            'vigencia_id' => $fila['vigencia_id'] !== null ? (int)$fila['vigencia_id'] : null,
            'vigencia_nombre' => $fila['vigencia_nombre'] ?? null,
            'vigencia_cantidad' => $fila['vigencia_cantidad'] !== null ? (int)$fila['vigencia_cantidad'] : null,
            'vigencia_unidad' => $fila['vigencia_unidad'] ?? null,
            'modalidad_default_id' => $fila['modalidad_default_id'] !== null ? (int)$fila['modalidad_default_id'] : null,
            'modalidad_nombre' => $fila['modalidad_nombre'] ?? null,
            'evaluacion' => (int)$fila['evaluacion'] === 1,
            'nota_minima' => $fila['nota_minima'] !== null ? (float)$fila['nota_minima'] : null,
            'certificado' => (int)$fila['certificado'] === 1,
            'requiere_listado_asistencia' => (int)$fila['requiere_listado_asistencia'] === 1,
            'fuente_normativa_id' => $fila['fuente_normativa_id'] !== null ? (int)$fila['fuente_normativa_id'] : null,
            'fuente_normativa_nombre' => $fila['fuente_normativa_nombre'] ?? null,
            'estado' => $fila['estado'],
            'created_at' => $fila['created_at'] ?? null,
            'updated_at' => $fila['updated_at'] ?? null,
        ];
    }
}
