<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\AuditoriaRepository;
use RuntimeException;

/**
 * Escribe en meridian_capacitaciones.auditoria.
 * El esquema en uso tiene valor_anterior / valor_nuevo (no detalle_json).
 * Usuario y marca de tiempo salen del backend; nunca del body del cliente.
 */
class AuditoriaService
{
    public const ORIGEN_USUARIO = 'usuario';
    public const ORIGEN_SISTEMA = 'sistema';
    public const ACTOR_SISTEMA = 'Motor de asignaciones';

    /** @var bool Solo pruebas: el siguiente registrar() lanza. */
    public static bool $fallarRegistro = false;

    private AuditoriaRepository $repo;

    public function __construct()
    {
        $this->repo = new AuditoriaRepository();
    }

    /**
     * @return array{usuario_id:?int,nombre:?string,ip:?string}
     */
    public static function actorDe(Request $request): array
    {
        $usuario = $request->user() ?? [];
        $nombre = isset($usuario['nombre_usuario']) ? trim((string)$usuario['nombre_usuario']) : '';

        return [
            'usuario_id' => $request->userId() ?: null,
            'nombre' => $nombre !== '' ? $nombre : null,
            'ip' => $request->ip(),
        ];
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string} $actor
     */
    public function deActor(
        array $actor,
        string $accion,
        ?string $entidad,
        ?int $entidadId,
        mixed $detalle = null,
        mixed $anterior = null
    ): void {
        $this->registrar(
            isset($actor['usuario_id']) ? (int)$actor['usuario_id'] : null,
            $accion,
            $entidad,
            $entidadId,
            $detalle,
            $actor['ip'] ?? null,
            isset($actor['nombre']) && is_string($actor['nombre']) ? $actor['nombre'] : null,
            $anterior
        );
    }

    public function dePeticion(
        Request $request,
        string $accion,
        ?string $entidad,
        ?int $entidadId,
        mixed $detalle = null,
        mixed $anterior = null
    ): void {
        $this->deActor(self::actorDe($request), $accion, $entidad, $entidadId, $detalle, $anterior);
    }

    public function registrarSistema(
        string $accion,
        ?string $entidad,
        ?int $entidadId,
        mixed $detalle = null,
        mixed $anterior = null
    ): void {
        $this->registrar(
            null,
            $accion,
            $entidad,
            $entidadId,
            $detalle,
            null,
            self::ACTOR_SISTEMA,
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
        if (self::$fallarRegistro) {
            self::$fallarRegistro = false;
            throw new RuntimeException('No fue posible registrar la auditoría.');
        }

        $this->repo->registrar(
            $usuarioId !== null && $usuarioId > 0 ? $usuarioId : null,
            $usuarioNombre,
            $accion,
            $entidad,
            $entidadId,
            $this->aJson($anterior),
            $this->aJson($detalle),
            $ip
        );
    }

    /**
     * @param array<string,mixed> $antes
     * @param array<string,mixed> $despues
     * @param array<string,string> $campos mapa campo => etiqueta
     * @return list<array{campo:string,etiqueta:string,anterior:mixed,nuevo:mixed}>
     */
    public function diff(array $antes, array $despues, array $campos): array
    {
        $cambios = [];
        foreach ($campos as $campo => $etiqueta) {
            $a = $this->normalizarValor($antes[$campo] ?? null);
            $b = $this->normalizarValor($despues[$campo] ?? null);
            if ($a === $b) {
                continue;
            }
            $cambios[] = [
                'campo' => $campo,
                'etiqueta' => $etiqueta,
                'anterior' => $a,
                'nuevo' => $b,
            ];
        }

        return $cambios;
    }

    /**
     * @param list<array{campo:string,etiqueta:string,anterior:mixed,nuevo:mixed}> $cambios
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function payloadNuevo(array $cambios, string $origen, array $extra = []): array
    {
        return array_merge($extra, [
            'cambios' => $cambios,
            'origen' => $origen,
        ]);
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function listar(int $pagina, int $porPagina, array $filtros = []): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;
        $limpios = $this->normalizarFiltros($filtros);

        $items = $this->repo->listar($porPagina, $offset, $limpios);
        foreach ($items as &$item) {
            $item['auditoria_id'] = (int)$item['auditoria_id'];
            $item['usuario_id_ext'] = $item['usuario_id_ext'] !== null ? (int)$item['usuario_id_ext'] : null;
            $item['entidad_id'] = $item['entidad_id'] !== null ? (int)$item['entidad_id'] : null;
            $item['nombre_usuario'] = $item['usuario_nombre'] ?: ($item['nombre_usuario'] ?? null);
            $nuevo = $this->desdeJson($item['valor_nuevo'] ?? null);
            $anterior = $this->desdeJson($item['valor_anterior'] ?? null);
            $item['detalle'] = $nuevo;
            $item['valor_anterior'] = $anterior;
            $item['valor_nuevo'] = $nuevo;
            $item['cambios'] = is_array($nuevo) && isset($nuevo['cambios']) && is_array($nuevo['cambios'])
                ? $nuevo['cambios']
                : [];
            $item['origen'] = is_array($nuevo) && isset($nuevo['origen']) ? $nuevo['origen'] : null;
            unset($item['usuario_nombre']);
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $this->repo->contar($limpios),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    /** @param array<string,mixed> $filtros */
    private function normalizarFiltros(array $filtros): array
    {
        $desde = isset($filtros['desde']) ? trim((string)$filtros['desde']) : '';
        $hasta = isset($filtros['hasta']) ? trim((string)$filtros['hasta']) : '';

        return [
            'entidad' => isset($filtros['entidad']) ? trim((string)$filtros['entidad']) : '',
            'accion' => isset($filtros['accion']) ? trim((string)$filtros['accion']) : '',
            'usuario' => isset($filtros['usuario']) ? trim((string)$filtros['usuario']) : '',
            'usuario_id' => isset($filtros['usuario_id']) ? (int)$filtros['usuario_id'] : 0,
            'entidad_id' => isset($filtros['entidad_id']) ? (int)$filtros['entidad_id'] : 0,
            'desde' => $desde !== '' ? $desde : '',
            'hasta' => $hasta !== '' ? $hasta : '',
        ];
    }

    private function normalizarValor(mixed $valor): mixed
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_bool($valor)) {
            return $valor;
        }
        if (is_int($valor) || is_float($valor) || (is_string($valor) && is_numeric($valor))) {
            $numero = (float)$valor;
            if (is_finite($numero) && abs($numero - round($numero)) < 0.00001) {
                return (int)round($numero);
            }

            return round($numero, 4);
        }
        if (is_string($valor)) {
            $txt = trim($valor);
            if ($txt === '') {
                return null;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $txt) === 1) {
                return substr($txt, 0, 10);
            }

            return $txt;
        }

        return $valor;
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
