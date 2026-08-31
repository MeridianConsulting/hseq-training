<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CatalogRepository;

class CatalogService
{
    private CatalogRepository $repo;

    public function __construct()
    {
        $this->repo = new CatalogRepository();
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
        return $this->repo->listar($def, $filtroEstado, $buscar);
    }

    public function ver(array $def, int $id): array
    {
        $registro = $this->repo->buscarPorId($def, $id);

        if ($registro === null) {
            throw new HttpException('Registro no encontrado', 404);
        }

        return $registro;
    }

    public function crear(array $def, array $datos): array
    {
        $datos = $this->limpiar($datos);

        if ($this->repo->nombreDuplicado($def, (string)$datos['nombre'])) {
            throw new HttpException('Ya existe un registro con este nombre.', 409);
        }

        $id = $this->repo->crear($def, $datos);

        return $this->ver($def, $id);
    }

    public function actualizar(array $def, int $id, array $datos): array
    {
        $actual = $this->ver($def, $id);
        $datos = $this->limpiar($datos);

        if (isset($datos['nombre']) && $this->repo->nombreDuplicado($def, (string)$datos['nombre'], $id)) {
            throw new HttpException('Ya existe un registro con este nombre.', 409);
        }

        if (array_key_exists('activo', $datos) && (int)$datos['activo'] === 0) {
            $this->asegurarPuedeInactivar($def, $id, $actual);
        }

        $this->repo->actualizar($def, $id, $datos);

        return $this->ver($def, $id);
    }

    /**
     * Inactiva el registro. Nunca elimina fisicamente.
     */
    public function eliminar(array $def, int $id): string
    {
        $actual = $this->ver($def, $id);

        if (!empty($def['soft_delete']) && (int)($actual['activo'] ?? 1) === 0) {
            return 'El registro ya está inactivo.';
        }

        $this->asegurarPuedeInactivar($def, $id, $actual);
        $this->repo->inactivar($def, $id);

        return 'El registro fue inactivado correctamente.';
    }

    public function reactivar(array $def, int $id): array
    {
        $this->ver($def, $id);
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
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function limpiar(array $datos): array
    {
        if (array_key_exists('activo', $datos) && $datos['activo'] === null) {
            unset($datos['activo']);
        }

        if (array_key_exists('nombre', $datos) && is_string($datos['nombre'])) {
            $datos['nombre'] = trim($datos['nombre']);
            if ($datos['nombre'] === '') {
                throw new HttpException('El nombre es obligatorio.', 422);
            }
        }

        if (isset($datos['descripcion']) && is_string($datos['descripcion'])) {
            $texto = trim($datos['descripcion']);
            $datos['descripcion'] = $texto === '' ? null : $texto;
        }

        return $datos;
    }
}
