<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\CatalogRepository;
use PDOException;

class CatalogService
{
    /** Codigo SQLSTATE de violacion de integridad referencial. */
    private const SQLSTATE_INTEGRIDAD = '23000';

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
                'permite_inactivar' => $def['soft_delete'],
                'campos' => array_keys($def['campos']),
            ];
        }

        return $tipos;
    }

    public function reglas(array $def, bool $esActualizacion = false): array
    {
        $reglas = $def['campos'];

        if ($esActualizacion && $def['soft_delete']) {
            $reglas['activo'] = 'nullable|integer|min:0|max:1';
        }

        return $reglas;
    }

    public function listar(array $def, bool $soloActivos, ?string $buscar): array
    {
        return $this->repo->listar($def, $soloActivos, $buscar);
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
            throw new HttpException("Ya existe un registro con el nombre '{$datos['nombre']}'", 409);
        }

        $id = $this->repo->crear($def, $datos);

        return $this->ver($def, $id);
    }

    public function actualizar(array $def, int $id, array $datos): array
    {
        $this->ver($def, $id);

        $datos = $this->limpiar($datos);

        if (isset($datos['nombre']) && $this->repo->nombreDuplicado($def, (string)$datos['nombre'], $id)) {
            throw new HttpException("Ya existe otro registro con el nombre '{$datos['nombre']}'", 409);
        }

        $this->repo->actualizar($def, $id, $datos);

        return $this->ver($def, $id);
    }

    /**
     * Inactiva el registro cuando la tabla tiene columna `activo`. En las tablas que no
     * la tienen el borrado es definitivo, y si el registro esta referenciado la base lo
     * impide y se responde con un mensaje claro.
     */
    public function eliminar(array $def, int $id): string
    {
        $this->ver($def, $id);

        if ($def['soft_delete']) {
            $this->repo->inactivar($def, $id);

            return 'Registro inactivado';
        }

        try {
            $this->repo->eliminar($def, $id);
        } catch (PDOException $e) {
            if ($e->getCode() === self::SQLSTATE_INTEGRIDAD) {
                throw new HttpException(
                    'No se puede eliminar porque esta siendo usado por otros registros',
                    409
                );
            }

            throw $e;
        }

        return 'Registro eliminado';
    }

    /**
     * Descarta las claves nulas que corresponden a columnas obligatorias, para no
     * enviar NULL a una columna NOT NULL cuando el campo llega vacio.
     */
    private function limpiar(array $datos): array
    {
        if (array_key_exists('activo', $datos) && $datos['activo'] === null) {
            unset($datos['activo']);
        }

        return $datos;
    }
}
