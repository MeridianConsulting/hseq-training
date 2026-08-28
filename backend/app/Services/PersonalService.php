<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\PersonalRepository;
use PDOException;
use Throwable;

class PersonalService
{
    private PersonalRepository $repo;

    public function __construct()
    {
        $this->repo = new PersonalRepository();
    }

    public function listar(int $pagina, int $porPagina, ?string $buscar, ?string $estado, ?int $cargoId): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        try {
            $items = array_map([$this, 'normalizar'], $this->repo->listar(
                $porPagina,
                $offset,
                $buscar,
                $estado,
                $cargoId
            ));

            return [
                'items' => $items,
                'total' => $this->repo->contar($buscar, $estado, $cargoId),
                'page' => $pagina,
                'per_page' => $porPagina,
            ];
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }
    }

    public function ver(int $personaId): array
    {
        try {
            $fila = $this->repo->buscarPorId($personaId);
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }

        if ($fila === null) {
            throw new HttpException('La persona no existe en el maestro de personal corporativo', 404);
        }

        return $this->normalizar($fila);
    }

    public function cargos(): array
    {
        try {
            return array_map(static function (array $fila): array {
                return [
                    'cargo_id' => (int)$fila['cargo_id'],
                    'nombre_cargo' => (string)$fila['nombre_cargo'],
                ];
            }, $this->repo->cargos());
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }
    }

    public function cargoExiste(int $cargoId): bool
    {
        try {
            return $this->repo->cargoExiste($cargoId);
        } catch (Throwable $e) {
            $this->falloPersonal($e);
        }
    }

    /** @return array<int,string> */
    public function nombresCargosPorIds(array $ids): array
    {
        try {
            return $this->repo->nombresCargosPorIds($ids);
        } catch (Throwable $e) {
            return [];
        }
    }

    private function falloPersonal(Throwable $e): void
    {
        if ($e instanceof HttpException) {
            throw $e;
        }

        throw new HttpException(
            'No fue posible consultar el maestro de personal corporativo',
            $e instanceof PDOException ? 503 : 500
        );
    }

    private function normalizar(array $fila): array
    {
        return [
            'persona_id' => (int)$fila['persona_id'],
            'numero_documento' => $fila['numero_documento'],
            'nombre_completo' => $fila['nombre_completo'],
            'estado' => $fila['estado'],
            'cargo_id' => $fila['cargo_id'] !== null ? (int)$fila['cargo_id'] : null,
            'cargo' => $fila['cargo'],
            'correo_corporativo' => $fila['correo_corporativo'],
            'correo_personal' => $fila['correo_personal'],
            'celular' => $fila['celular'],
            'contrato_id' => $fila['contrato_id'] !== null ? (int)$fila['contrato_id'] : null,
            'numero_contrato' => $fila['numero_contrato'],
            'proyecto' => $fila['proyecto'],
            'contrato_fecha_inicio' => $fila['contrato_fecha_inicio'],
            'contrato_fecha_terminacion' => $fila['contrato_fecha_terminacion'],
        ];
    }
}
