<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Punto de extensión para restringir datos por rol / área / proyecto.
 *
 * Decisión de negocio pendiente (2026-09): hoy el acceso es por permiso de
 * módulo (p. ej. asignaciones.ver) y el listado es global. Cuando HSEQ defina
 * la matriz de aislamiento, implementar aquí el alcance del actor y aplicarlo
 * en los repositorios de listado sin cambiar la firma de los controladores.
 *
 * Estados previstos:
 * - global: ve todos los registros del módulo (comportamiento actual)
 * - procesos: solo proceso_id ∈ lista del usuario
 * - proyectos: solo proyecto ∈ lista del usuario
 */
class AlcanceDatosService
{
    public const MODO_GLOBAL = 'global';

    /**
     * @param array{usuario_id:?int, roles?:list<string>} $actor
     * @return array{
     *   modo:string,
     *   proceso_ids:list<int>,
     *   proyectos:list<string>,
     *   activo:bool
     * }
     */
    public function alcanceDe(array $actor): array
    {
        unset($actor);

        return [
            'modo' => self::MODO_GLOBAL,
            'proceso_ids' => [],
            'proyectos' => [],
            'activo' => false,
        ];
    }

    /**
     * Aplica el alcance a un arreglo de filtros de listado.
     * Sin efecto mientras activo=false (modo global).
     *
     * @param array<string,mixed> $filtros
     * @param array{modo:string,proceso_ids:list<int>,proyectos:list<string>,activo:bool} $alcance
     * @return array<string,mixed>
     */
    public function aplicarAFiltros(array $filtros, array $alcance): array
    {
        if (empty($alcance['activo']) || ($alcance['modo'] ?? self::MODO_GLOBAL) === self::MODO_GLOBAL) {
            return $filtros;
        }

        if (($alcance['modo'] ?? '') === 'procesos' && $alcance['proceso_ids'] !== []) {
            $filtros['_alcance_proceso_ids'] = $alcance['proceso_ids'];
        }

        if (($alcance['modo'] ?? '') === 'proyectos' && $alcance['proyectos'] !== []) {
            $filtros['_alcance_proyectos'] = $alcance['proyectos'];
        }

        return $filtros;
    }
}
