<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CatalogRepository;

class CatalogService
{
    private CatalogRepository $repo;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->repo = new CatalogRepository();
        $this->auditoria = new AuditoriaService();
    }

    public function definicion(string $tipo): array
    {
        $catalogo = config('catalogs.' . $tipo);

        if (!is_array($catalogo)) {
            throw new HttpException("El catalogo '{$tipo}' no existe", 404);
        }

        $catalogo['tipo'] = $tipo;

        return $catalogo;
    }

    public function tiposDisponibles(): array
    {
        $tipos = [];

        foreach (config('catalogs', []) as $tipo => $def) {
            if (array_key_exists('mostrar_en_ui', $def) && $def['mostrar_en_ui'] === false) {
                continue;
            }

            $tipos[] = [
                'tipo' => $tipo,
                'etiqueta' => $def['etiqueta'],
                'permite_inactivar' => (bool)$def['soft_delete'],
                'campos' => array_keys($def['campos']),
            ];
        }

        return $tipos;
    }

    public function reglas(array $def, bool $esActualizacion = false): array
    {
        $reglas = $def['campos'];

        if ($esActualizacion) {
            foreach ($reglas as $campo => $regla) {
                $texto = is_string($regla) ? $regla : '';
                $texto = str_replace('required|', 'nullable|', $texto);
                if (!str_starts_with($texto, 'nullable')) {
                    $texto = 'nullable|' . $texto;
                }
                $reglas[$campo] = $texto;
            }

            if (!empty($def['soft_delete'])) {
                $reglas['activo'] = 'nullable|integer|min:0|max:1';
            }
        }

        return $reglas;
    }

    public function listar(array $def, string $filtroEstado, ?string $buscar): array
    {
        return $this->presentarFilas($def, $this->repo->listar($def, $filtroEstado, $buscar));
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function listarPaginado(
        array $def,
        string $filtroEstado,
        ?string $buscar,
        int $pagina,
        int $porPagina
    ): array {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        return [
            'items' => $this->presentarFilas(
                $def,
                $this->repo->listar($def, $filtroEstado, $buscar, $porPagina, $offset)
            ),
            'total' => $this->repo->contar($def, $filtroEstado, $buscar),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    public function ver(array $def, int $id): array
    {
        $registro = $this->repo->buscarPorId($def, $id);

        if ($registro === null) {
            throw new HttpException('Registro no encontrado', 404);
        }

        return $this->presentarFila($def, $registro);
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function crear(array $def, array $datos, ?array $actor = null): array
    {
        $datos = $this->limpiar($def, $datos);

        if ($this->repo->nombreDuplicado($def, (string)$datos['nombre'])) {
            throw new HttpException('Ya existe un registro con este nombre.', 409);
        }

        $this->asegurarDuracionUnica($def, $datos, null);

        $id = $this->repo->crear($def, $datos);
        $creado = $this->ver($def, $id);
        if ($actor !== null) {
            $this->auditoria->deActor(
                $actor,
                'crear',
                (string)$def['tabla'],
                $id,
                $this->vistaAuditoria($def, $creado)
            );
        }

        return $creado;
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function actualizar(array $def, int $id, array $datos, ?array $actor = null): array
    {
        $actual = $this->ver($def, $id);
        $datos = $this->limpiar($def, $datos);

        if (isset($datos['nombre']) && $this->repo->nombreDuplicado($def, (string)$datos['nombre'], $id)) {
            throw new HttpException('Ya existe un registro con este nombre.', 409);
        }

        $quedaraActivo = array_key_exists('activo', $datos)
            ? (int)$datos['activo'] === 1
            : (int)($actual['activo'] ?? 1) === 1;
        if ($quedaraActivo) {
            $this->asegurarDuracionUnica($def, array_merge($actual, $datos), $id);
        }

        if (array_key_exists('activo', $datos) && (int)$datos['activo'] === 0) {
            $this->asegurarPuedeInactivar($def, $id, $actual);
        }

        $this->repo->actualizar($def, $id, $datos);
        $despues = $this->ver($def, $id);
        if ($actor !== null) {
            $antesVista = $this->vistaAuditoria($def, $actual);
            $despuesVista = $this->vistaAuditoria($def, $despues);
            $cambios = $this->auditoria->diff($antesVista, $despuesVista, $this->etiquetasCampos($def));
            $accion = 'actualizar';
            if (($antesVista['activo'] ?? null) === 'Activo' && ($despuesVista['activo'] ?? null) === 'Inactivo') {
                $accion = 'inactivar';
            } elseif (($antesVista['activo'] ?? null) === 'Inactivo' && ($despuesVista['activo'] ?? null) === 'Activo') {
                $accion = 'reactivar';
            }
            if ($cambios !== []) {
                $this->auditoria->deActor(
                    $actor,
                    $accion,
                    (string)$def['tabla'],
                    $id,
                    $this->auditoria->payloadNuevo($cambios, AuditoriaService::ORIGEN_USUARIO),
                    $antesVista
                );
            }
        }

        return $despues;
    }

    /**
     * Inactiva el registro. Nunca elimina fisicamente.
     *
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function eliminar(array $def, int $id, ?array $actor = null): string
    {
        $actual = $this->ver($def, $id);

        if (!empty($def['soft_delete']) && (int)($actual['activo'] ?? 1) === 0) {
            return 'El registro ya está inactivo.';
        }

        $this->asegurarPuedeInactivar($def, $id, $actual);
        $this->repo->inactivar($def, $id);
        if ($actor !== null) {
            $despues = $this->ver($def, $id);
            $antesVista = $this->vistaAuditoria($def, $actual);
            $despuesVista = $this->vistaAuditoria($def, $despues);
            $cambios = $this->auditoria->diff($antesVista, $despuesVista, $this->etiquetasCampos($def));
            $this->auditoria->deActor(
                $actor,
                'inactivar',
                (string)$def['tabla'],
                $id,
                $this->auditoria->payloadNuevo($cambios, AuditoriaService::ORIGEN_USUARIO),
                $antesVista
            );
        }

        return 'El registro fue inactivado correctamente.';
    }

    public function reactivar(array $def, int $id): array
    {
        $actual = $this->ver($def, $id);
        $this->asegurarDuracionUnica($def, $actual, $id);
        $this->repo->reactivar($def, $id);

        return $this->ver($def, $id);
    }

    public function contarDependencias(array $def, int $id): array
    {
        return $this->repo->contarDependencias($def, $id);
    }

    public function mensajeDependencias(array $def): string
    {
        $personalizado = $def['mensaje_dependencias'] ?? null;

        if (is_string($personalizado) && $personalizado !== '') {
            return $personalizado;
        }

        return 'No es posible eliminar este registro porque tiene información asociada. Puede inactivarlo para evitar su uso en nuevos registros.';
    }

    /**
     * @param array<string, mixed> $actual
     */
    private function asegurarPuedeInactivar(array $def, int $id, array $actual): void
    {
        if (($def['tipo'] ?? '') !== 'roles') {
            return;
        }

        $nombre = strtolower(trim((string)($actual['nombre'] ?? '')));
        if (!in_array($nombre, ['administrador hseq', 'admin'], true)) {
            return;
        }

        if ($this->repo->contarRolesAdminActivos($id) === 0) {
            throw new HttpException(
                'No es posible inactivar el único rol Administrador HSEQ.',
                409
            );
        }
    }

    /**
     * 1 año y 12 meses (o 2 años y 24 meses) no pueden convivir activos.
     *
     * @param array<string, mixed> $datos
     */
    private function asegurarDuracionUnica(array $def, array $datos, ?int $exceptoId): void
    {
        $tabla = (string)($def['tabla'] ?? '');
        if (!in_array($tabla, ['vigencias', 'periodicidades'], true)) {
            return;
        }

        $meses = meses_equivalentes($datos['cantidad'] ?? null, $datos['unidad'] ?? null);
        if ($meses === null) {
            return;
        }

        $pk = (string)$def['pk'];
        $tipo = $tabla === 'vigencias' ? 'vigencia' : 'periodicidad';
        foreach ($this->repo->listar($def, 'activos', null) as $fila) {
            $id = (int)($fila[$pk] ?? 0);
            if ($exceptoId !== null && $id === $exceptoId) {
                continue;
            }
            $eq = meses_equivalentes($fila['cantidad'] ?? null, $fila['unidad'] ?? null);
            if ($eq !== $meses) {
                continue;
            }
            $nombre = humanizar_nombre_unidad($fila['nombre'] ?? null) ?? (string)($fila['nombre'] ?? '');
            throw new HttpException(
                "Ya existe una {$tipo} equivalente: {$nombre}. Un año equivale a 12 meses.",
                409
            );
        }
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function limpiar(array $def, array $datos): array
    {
        if (array_key_exists('activo', $datos) && $datos['activo'] === null) {
            unset($datos['activo']);
        }

        if (array_key_exists('nombre', $datos) && is_string($datos['nombre'])) {
            $datos['nombre'] = trim($datos['nombre']);
            if ($datos['nombre'] === '') {
                throw new HttpException('El nombre es obligatorio.', 422);
            }
            $tabla = (string)($def['tabla'] ?? '');
            if (in_array($tabla, ['procesos', 'proyectos', 'areas', 'modalidades', 'tipos_capacitacion'], true)) {
                $datos['nombre'] = mb_strtoupper($datos['nombre'], 'UTF-8');
            }
            if (in_array($tabla, ['vigencias', 'periodicidades'], true)) {
                $datos['nombre'] = humanizar_nombre_unidad($datos['nombre']) ?? $datos['nombre'];
            }
        }

        if (isset($datos['descripcion']) && is_string($datos['descripcion'])) {
            $texto = trim($datos['descripcion']);
            $datos['descripcion'] = $texto === '' ? null : $texto;
        }

        return $datos;
    }

    /**
     * @param array<string,mixed> $def
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function vistaAuditoria(array $def, array $fila): array
    {
        $out = [];
        foreach (array_keys($def['campos'] ?? []) as $campo) {
            if (!is_string($campo)) {
                continue;
            }
            $out[$campo] = $fila[$campo] ?? null;
        }
        if (!empty($def['soft_delete'])) {
            $out['activo'] = ((int)($fila['activo'] ?? 1) === 1) ? 'Activo' : 'Inactivo';
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $def
     * @return array<string,string>
     */
    private function etiquetasCampos(array $def): array
    {
        $map = [
            'nombre' => 'Nombre',
            'descripcion' => 'Descripción',
            'cantidad' => 'Cantidad',
            'unidad' => 'Unidad',
            'activo' => 'Estado',
        ];
        $campos = [];
        foreach (array_keys($def['campos'] ?? []) as $campo) {
            if (!is_string($campo)) {
                continue;
            }
            $campos[$campo] = $map[$campo] ?? $campo;
        }
        if (!empty($def['soft_delete'])) {
            $campos['activo'] = 'Estado';
        }

        return $campos;
    }

    /**
     * @param list<array<string,mixed>> $filas
     * @return list<array<string,mixed>>
     */
    private function presentarFilas(array $def, array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $out[] = $this->presentarFila($def, $fila);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function presentarFila(array $def, array $fila): array
    {
        $tabla = (string)($def['tabla'] ?? '');
        if (in_array($tabla, ['vigencias', 'periodicidades'], true) && isset($fila['nombre'])) {
            $fila['nombre'] = humanizar_nombre_unidad($fila['nombre']) ?? $fila['nombre'];
        }

        return $fila;
    }
}
