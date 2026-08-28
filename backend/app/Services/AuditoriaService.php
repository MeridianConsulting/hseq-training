<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\AuditoriaRepository;

/**
 * Escribe en meridian_capacitaciones.auditoria.
 * El esquema en uso tiene valor_anterior / valor_nuevo (no detalle_json).
 */
class AuditoriaService
{
    private AuditoriaRepository $repo;

    public function __construct()
    {
        $this->repo = new AuditoriaRepository();
    }

    public function dePeticion(
        Request $request,
        string $accion,
        ?string $entidad,
        ?int $entidadId,
        mixed $detalle = null,
        mixed $anterior = null
    ): void {
        $usuario = $request->user() ?? [];
        $nombre = isset($usuario['nombre_usuario']) ? (string)$usuario['nombre_usuario'] : null;

        $this->registrar(
            $request->userId() ?: null,
            $accion,
            $entidad,
            $entidadId,
            $detalle,
            $request->ip(),
            $nombre !== '' ? $nombre : null,
            $anterior
        );
    }

    public function registrar(
        ?int $usuarioId,
        string $accion,
        ?string $entidad = null,
        ?int $entidadId = null,
        mixed $detalle = null,
        ?string $ip = null,
        ?string $usuarioNombre = null,
        mixed $anterior = null
    ): void {
        $this->repo->registrar(
            $usuarioId,
            $usuarioNombre,
            $accion,
            $entidad,
            $entidadId,
            $this->aJson($anterior),
            $this->aJson($detalle),
            $ip
        );
    }

    public function listar(int $pagina, int $porPagina, ?string $entidad, ?string $accion): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $items = $this->repo->listar($porPagina, $offset, $entidad, $accion);

        foreach ($items as &$item) {
            $item['auditoria_id'] = (int)$item['auditoria_id'];
            $item['usuario_id_ext'] = $item['usuario_id_ext'] !== null ? (int)$item['usuario_id_ext'] : null;
            $item['entidad_id'] = $item['entidad_id'] !== null ? (int)$item['entidad_id'] : null;
            $item['nombre_usuario'] = $item['usuario_nombre'] ?: $item['nombre_usuario'];
            $item['detalle'] = $this->desdeJson($item['valor_nuevo'] ?? null);
            unset($item['valor_anterior'], $item['valor_nuevo'], $item['usuario_nombre']);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $this->repo->contar($entidad, $accion),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    private function aJson(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $codificado = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $codificado === false ? null : $codificado;
    }

    private function desdeJson(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $decodificado = json_decode((string)$raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decodificado : $raw;
    }
}
