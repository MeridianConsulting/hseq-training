<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CapacitacionRepository;
use PDOException;

class CapacitacionService
{
    private const SQLSTATE_INTEGRIDAD = '23000';

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

    public function __construct()
    {
        $this->repo = new CapacitacionRepository();
    }

    public function reglas(bool $esActualizacion = false): array
    {
        $codigo = $esActualizacion ? 'nullable|string|max:30' : 'required|string|max:30';
        $nombre = $esActualizacion ? 'nullable|string|max:180' : 'required|string|max:180';
        $objetivo = $esActualizacion ? 'nullable|string' : 'required|string';
        $horas = $esActualizacion ? 'nullable|numeric|min:0' : 'required|numeric|min:0';

        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'objetivo' => $objetivo,
            'descripcion_temario' => 'nullable|string',
            'categoria_id' => 'nullable|integer',
            'tipo_capacitacion_id' => 'nullable|integer',
            'duracion_estimada_horas' => $horas,
            'criticidad' => ($esActualizacion ? 'nullable' : 'required') . '|in:BAJA,MEDIA,ALTA',
            'es_tarea_critica' => 'nullable|integer|min:0|max:1',
            'responsable' => 'nullable|string|max:120',
            'proveedor_default_id' => 'nullable|integer',
            'periodicidad_default_id' => 'nullable|integer',
            'vigencia_id' => 'nullable|integer',
            'modalidad_default_id' => 'nullable|integer',
            'evaluacion' => 'nullable|integer|min:0|max:1',
            'nota_minima' => 'nullable|numeric|min:0',
            'certificado' => 'nullable|integer|min:0|max:1',
            'requiere_listado_asistencia' => 'nullable|integer|min:0|max:1',
            'fuente_normativa_id' => 'nullable|integer',
            'estado' => 'nullable|in:ACTIVA,INACTIVA',
        ];
    }

    public function listar(int $pagina, int $porPagina, ?string $buscar, ?string $estado, ?int $categoriaId): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $items = array_map(
            [$this, 'normalizar'],
            $this->repo->listar($porPagina, $offset, $buscar, $estado, $categoriaId)
        );

        return [
            'items' => $items,
            'total' => $this->repo->contar($buscar, $estado, $categoriaId),
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

    public function crear(array $datos, int $usuarioId): array
    {
        $datos = $this->preparar($datos);

        if ($this->repo->codigoDuplicado((string)$datos['codigo'])) {
            throw new HttpException('Ya existe una capacitación con ese código', 409);
        }

        $this->validarFks($datos);

        $datos['creado_por_usuario_id_ext'] = $usuarioId;
        $id = $this->repo->crear($datos);

        return $this->ver($id);
    }

    public function actualizar(int $id, array $datos): array
    {
        $this->ver($id);
        $datos = $this->preparar($datos, true);

        if (isset($datos['codigo']) && $this->repo->codigoDuplicado((string)$datos['codigo'], $id)) {
            throw new HttpException('Ya existe otra capacitación con ese código', 409);
        }

        $this->validarFks($datos);

        if ($datos === []) {
            return $this->ver($id);
        }

        $this->repo->actualizar($id, $datos);

        return $this->ver($id);
    }

    public function eliminar(int $id): string
    {
        $this->ver($id);

        try {
            $this->repo->eliminar($id);

            return 'Capacitación eliminada';
        } catch (PDOException $e) {
            if ($e->getCode() === self::SQLSTATE_INTEGRIDAD) {
                $this->repo->inactivar($id);

                return 'La capacitación está en uso; se inactivó para conservar el histórico';
            }

            throw $e;
        }
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

        if (array_key_exists('nota_minima', $datos) && $datos['nota_minima'] !== null) {
            $datos['nota_minima'] = (float)$datos['nota_minima'];
        }

        if ($parcial) {
            $datos = array_filter($datos, static fn ($valor) => $valor !== null || true);
            foreach ($datos as $clave => $valor) {
                if ($valor === null && in_array($clave, ['codigo', 'nombre', 'objetivo', 'duracion_estimada_horas', 'criticidad'], true)) {
                    unset($datos[$clave]);
                }
            }
        }

        return $datos;
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
