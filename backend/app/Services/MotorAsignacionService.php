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
        $reglas = $this->matriz->reglasActivasParaMotor();
        $reglas = $this->filtrarReglas($reglas, $filtro);
        $personas = $this->personal->listarActivosParaMotor();
        $pendientes = $this->asignaciones->paresPendientes();

        $creadas = 0;
        $omitidas = 0;
        $hoy = (new DateTimeImmutable('today'))->format('Y-m-d');

        $this->asignaciones->transaccion(function () use (
            $reglas,
            $personas,
            &$pendientes,
            &$creadas,
            &$omitidas,
            $hoy,
            $usuarioId
        ): int {
                foreach ($personas as $persona) {
                    $personaId = (int)$persona['persona_id'];
                    $cargoId = (int)$persona['cargo_id'];
                    $proyectoPersona = is_string($persona['proyecto'] ?? null)
                        ? trim((string)$persona['proyecto'])
                        : '';

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
                            'contrato_id_ext' => $persona['contrato_id'] !== null ? (int)$persona['contrato_id'] : null,
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
                }

                return $creadas;
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
    private function fechaLimite(array $regla): string
    {
        $hoy = new DateTimeImmutable('today');
        $cantidad = isset($regla['per_cantidad']) ? (int)$regla['per_cantidad'] : 0;
        $unidad = isset($regla['per_unidad']) ? strtoupper((string)$regla['per_unidad']) : '';

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
}
