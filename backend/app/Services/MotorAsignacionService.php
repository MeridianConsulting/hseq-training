<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AsignacionRepository;
use App\Repositories\MatrizRepository;
use DateTimeImmutable;

/**
 * Motor RF-008: genera asignaciones AUTOMATICA a partir de la matriz activa.
 * La matriz es la fuente de verdad; este servicio solo la consume.
 */
class MotorAsignacionService
{
    private MatrizRepository $matriz;
    private AsignacionRepository $asignaciones;
    private PersonalService $personal;

    public function __construct()
    {
        $this->matriz = new MatrizRepository();
        $this->asignaciones = new AsignacionRepository();
        $this->personal = new PersonalService();
    }

    /**
     * @param array{capacitacion_id?:int, proyecto?:string} $filtro
     * @return array{creadas:int, omitidas:int}
     */
    public function generar(?int $usuarioId = null, array $filtro = []): array
    {
        $reglas = $this->filtrarReglas($this->matriz->reglasActivasParaMotor(), $filtro);
        $personas = $this->personal->listarActivosParaMotor();
        $pendientes = [];

        $creadas = 0;
        $omitidas = 0;

        foreach ($personas as $persona) {
            $personaId = (int)($persona['persona_id'] ?? 0);
            $this->asignaciones->conLockPersona($personaId, function () use (
                $persona,
                $personaId,
                $reglas,
                &$pendientes,
                &$creadas,
                &$omitidas,
                $usuarioId
            ): void {
                $this->asignaciones->transaccion(function () use (
                    $persona,
                    $personaId,
                    $reglas,
                    &$pendientes,
                    &$creadas,
                    &$omitidas,
                    $usuarioId
                ): int {
                    foreach ($this->asignaciones->paresPendientes($personaId > 0 ? $personaId : null) as $clave => $valor) {
                        $pendientes[$clave] = $valor;
                    }
                    $r = $this->aplicarReglas($persona, $reglas, $pendientes, $usuarioId);
                    $creadas += $r['creadas'];
                    $omitidas += $r['omitidas'];

                    return $r['creadas'];
                });
            });
        }

        return [
            'creadas' => $creadas,
            'omitidas' => $omitidas,
        ];
    }

    /**
     * Sincroniza un solo trabajador con las reglas activas. Idempotente.
     *
     * @param array<string,mixed> $persona
     * @return array{creadas:int, omitidas:int}
     */
    public function sincronizarPersona(array $persona, ?int $usuarioId = null): array
    {
        if (empty($persona['cargo_id']) && empty($persona['persona_id'])) {
            return ['creadas' => 0, 'omitidas' => 0];
        }

        $personaId = (int)($persona['persona_id'] ?? 0);
        $reglas = $this->matriz->reglasActivasParaMotor();
        $creadas = 0;
        $omitidas = 0;

        $this->asignaciones->conLockPersona($personaId, function () use (
            $persona,
            $personaId,
            $reglas,
            &$creadas,
            &$omitidas,
            $usuarioId
        ): int {
            return $this->asignaciones->transaccion(function () use (
                $persona,
                $personaId,
                $reglas,
                &$creadas,
                &$omitidas,
                $usuarioId
            ): int {
                $pendientes = $this->asignaciones->paresPendientes($personaId > 0 ? $personaId : null);
                $r = $this->aplicarReglas($persona, $reglas, $pendientes, $usuarioId);
                $creadas = $r['creadas'];
                $omitidas = $r['omitidas'];

                return $creadas;
            });
        });

        return [
            'creadas' => $creadas,
            'omitidas' => $omitidas,
        ];
    }

    /**
     * @param list<array<string,mixed>> $reglas
     * @param array{capacitacion_id?:int, proyecto?:string} $filtro
     * @return list<array<string,mixed>>
     */
    private function filtrarReglas(array $reglas, array $filtro): array
    {
        $capId = isset($filtro['capacitacion_id']) ? (int)$filtro['capacitacion_id'] : 0;
        $proyecto = isset($filtro['proyecto']) ? trim((string)$filtro['proyecto']) : '';

        if ($capId <= 0 && $proyecto === '') {
            return $reglas;
        }

        $filtradas = [];
        foreach ($reglas as $regla) {
            if ($capId > 0 && (int)$regla['capacitacion_id'] !== $capId) {
                continue;
            }
            if ($proyecto !== '') {
                $deRegla = is_string($regla['proyecto'] ?? null) ? trim((string)$regla['proyecto']) : '';
                if (strcasecmp($deRegla, $proyecto) !== 0) {
                    continue;
                }
            }
            $filtradas[] = $regla;
        }

        return $filtradas;
    }

    public function mensaje(array $resultado): string
    {
        $creadas = (int)$resultado['creadas'];
        $omitidas = (int)$resultado['omitidas'];

        if ($creadas === 0 && $omitidas === 0) {
            return 'No había reglas aplicables para trabajadores activos.';
        }

        return "{$creadas} asignaciones automáticas creadas, {$omitidas} omitida(s) porque ya existía una pendiente.";
    }

    /**
     * @param array<string,mixed> $persona
     * @param list<array<string,mixed>> $reglas
     * @param array<string,true> $pendientes
     * @return array{creadas:int, omitidas:int}
     */
    private function aplicarReglas(array $persona, array $reglas, array &$pendientes, ?int $usuarioId): array
    {
        $personaId = (int)($persona['persona_id'] ?? 0);
        $cargoId = (int)($persona['cargo_id'] ?? 0);
        if ($personaId <= 0 || $cargoId <= 0) {
            return ['creadas' => 0, 'omitidas' => 0];
        }

        $proyectoPersona = is_string($persona['proyecto'] ?? null) ? trim((string)$persona['proyecto']) : '';
        $hoy = (new DateTimeImmutable('today'))->format('Y-m-d');
        $creadas = 0;
        $omitidas = 0;
        $contratoId = $persona['contrato_id'] ?? null;

        foreach ($reglas as $regla) {
            if (!$this->coincide($regla, $cargoId, $proyectoPersona)) {
                continue;
            }

            $capacitacionId = (int)$regla['capacitacion_id'];
            $clave = $personaId . ':' . $capacitacionId;

            if (isset($pendientes[$clave])) {
                $omitidas++;
                continue;
            }

            $this->asignaciones->crear([
                'persona_id_ext' => $personaId,
                'contrato_id_ext' => $contratoId !== null ? (int)$contratoId : null,
                'capacitacion_id' => $capacitacionId,
                'matriz_aplicabilidad_id' => (int)$regla['matriz_aplicabilidad_id'],
                'fecha_asignacion' => $hoy,
                'fecha_limite_cumplimiento' => $this->fechaLimite($regla),
                'origen' => 'AUTOMATICA',
                'cargo_id_ext' => $cargoId,
                'area_id' => $regla['area_id'] !== null ? (int)$regla['area_id'] : null,
                'proceso_id' => $regla['proceso_id'] !== null ? (int)$regla['proceso_id'] : null,
                'ambito' => $regla['ambito'] !== null && $regla['ambito'] !== '' ? $regla['ambito'] : null,
                'proyecto' => $proyectoPersona !== '' ? $proyectoPersona : null,
                'creada_por_usuario_id_ext' => $usuarioId,
            ]);

            $pendientes[$clave] = true;
            $creadas++;
        }

        return ['creadas' => $creadas, 'omitidas' => $omitidas];
    }

    /**
     * @param array<string,mixed> $regla
     */
    private function coincide(array $regla, int $cargoId, string $proyectoPersona): bool
    {
        $cargoRegla = $regla['cargo_id_ext'] !== null ? (int)$regla['cargo_id_ext'] : null;
        if ($cargoRegla !== null && $cargoRegla !== $cargoId) {
            return false;
        }

        $proyectoRegla = is_string($regla['proyecto'] ?? null) ? trim((string)$regla['proyecto']) : '';
        if ($proyectoRegla !== '' && strcasecmp($proyectoRegla, $proyectoPersona) !== 0) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $regla
     */
    public function fechaLimiteDesdePeriodicidad(?int $cantidad, ?string $unidad): string
    {
        $hoy = new DateTimeImmutable('today');
        $cantidad = (int)$cantidad;
        $unidad = strtoupper((string)$unidad);

        if ($cantidad <= 0) {
            return $hoy->format('Y-m-d');
        }

        $mapa = ['DIAS' => 'days', 'MESES' => 'months', 'ANIOS' => 'years'];
        $mod = $mapa[$unidad] ?? null;

        if ($mod === null) {
            return $hoy->format('Y-m-d');
        }

        return $hoy->modify('+' . $cantidad . ' ' . $mod)->format('Y-m-d');
    }

    /**
     * @param array<string,mixed> $regla
     */
    private function fechaLimite(array $regla): string
    {
        return $this->fechaLimiteDesdePeriodicidad(
            isset($regla['per_cantidad']) ? (int)$regla['per_cantidad'] : 0,
            isset($regla['per_unidad']) ? (string)$regla['per_unidad'] : ''
        );
    }
}
