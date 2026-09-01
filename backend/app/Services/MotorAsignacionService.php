<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AsignacionRepository;
use App\Repositories\CapacitacionRepository;
use App\Repositories\MatrizRepository;
use DateTimeImmutable;

/**
 * Motor RF-008: matriz (AUTOMATICA) más reglas especiales de INDUCCION / REINDUCCION.
 */
class MotorAsignacionService
{
    private MatrizRepository $matriz;
    private AsignacionRepository $asignaciones;
    private CapacitacionRepository $capacitaciones;
    private PersonalService $personal;

    /** @var list<array<string,mixed>>|null */
    private ?array $especialesCache = null;

    public function __construct()
    {
        $this->matriz = new MatrizRepository();
        $this->asignaciones = new AsignacionRepository();
        $this->capacitaciones = new CapacitacionRepository();
        $this->personal = new PersonalService();
    }

    /**
     * @param array{capacitacion_id?:int, proyecto?:string} $filtro
     * @return array{creadas:int, omitidas:int, creadas_especiales:list<string>}
     */
    public function generar(?int $usuarioId = null, array $filtro = []): array
    {
        $hayFiltro = $this->hayFiltro($filtro);
        $reglas = $this->filtrarReglas($this->matriz->reglasActivasParaMotor(), $filtro);
        $personas = $this->personal->listarActivosParaMotor();
        $pendientes = $this->asignaciones->paresPendientes();
        $existentes = $this->asignaciones->paresExistentes();
        $vencimientos = $this->asignaciones->ultimasFechasVencimiento();

        $creadas = 0;
        $omitidas = 0;
        $nombres = [];

        foreach ($personas as $persona) {
            $personaId = (int)($persona['persona_id'] ?? 0);
            $this->asignaciones->conLockPersona($personaId, function () use (
                $persona,
                $personaId,
                $reglas,
                $hayFiltro,
                &$pendientes,
                &$existentes,
                &$vencimientos,
                &$creadas,
                &$omitidas,
                &$nombres,
                $usuarioId
            ): void {
                $this->asignaciones->transaccion(function () use (
                    $persona,
                    $personaId,
                    $reglas,
                    $hayFiltro,
                    &$pendientes,
                    &$existentes,
                    &$vencimientos,
                    &$creadas,
                    &$omitidas,
                    &$nombres,
                    $usuarioId
                ): int {
                    foreach ($this->asignaciones->paresPendientes($personaId > 0 ? $personaId : null) as $clave => $valor) {
                        $pendientes[$clave] = $valor;
                    }
                    foreach ($this->asignaciones->paresExistentes($personaId > 0 ? $personaId : null) as $clave => $valor) {
                        $existentes[$clave] = $valor;
                    }
                    foreach ($this->asignaciones->ultimasFechasVencimiento($personaId > 0 ? $personaId : null) as $clave => $valor) {
                        $vencimientos[$clave] = $valor;
                    }
                    $r = $this->aplicarTodo(
                        $persona,
                        $reglas,
                        $pendientes,
                        $existentes,
                        $vencimientos,
                        $usuarioId,
                        !$hayFiltro
                    );
                    $creadas += $r['creadas'];
                    $omitidas += $r['omitidas'];
                    foreach ($r['creadas_especiales'] as $nombre) {
                        $nombres[] = $nombre;
                    }

                    return $r['creadas'];
                });
            });
        }

        return [
            'creadas' => $creadas,
            'omitidas' => $omitidas,
            'creadas_especiales' => $nombres,
        ];
    }

    /**
     * Sincroniza un solo trabajador. Idempotente.
     *
     * @param array<string,mixed> $persona
     * @return array{creadas:int, omitidas:int, creadas_especiales:list<string>}
     */
    public function sincronizarPersona(array $persona, ?int $usuarioId = null): array
    {
        $personaId = (int)($persona['persona_id'] ?? 0);
        if ($personaId <= 0) {
            return ['creadas' => 0, 'omitidas' => 0, 'creadas_especiales' => []];
        }

        $reglas = $this->matriz->reglasActivasParaMotor();
        $creadas = 0;
        $omitidas = 0;
        $nombres = [];

        $this->asignaciones->conLockPersona($personaId, function () use (
            $persona,
            $personaId,
            $reglas,
            &$creadas,
            &$omitidas,
            &$nombres,
            $usuarioId
        ): int {
            return $this->asignaciones->transaccion(function () use (
                $persona,
                $personaId,
                $reglas,
                &$creadas,
                &$omitidas,
                &$nombres,
                $usuarioId
            ): int {
                $pendientes = $this->asignaciones->paresPendientes($personaId);
                $existentes = $this->asignaciones->paresExistentes($personaId);
                $vencimientos = $this->asignaciones->ultimasFechasVencimiento($personaId);
                $r = $this->aplicarTodo(
                    $persona,
                    $reglas,
                    $pendientes,
                    $existentes,
                    $vencimientos,
                    $usuarioId,
                    true
                );
                $creadas = $r['creadas'];
                $omitidas = $r['omitidas'];
                $nombres = $r['creadas_especiales'];

                return $creadas;
            });
        });

        return [
            'creadas' => $creadas,
            'omitidas' => $omitidas,
            'creadas_especiales' => $nombres,
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
     * @param array<string,true> $existentes
     * @param array<string,string|null> $vencimientos
     * @return array{creadas:int, omitidas:int, creadas_especiales:list<string>}
     */
    private function aplicarTodo(
        array $persona,
        array $reglas,
        array &$pendientes,
        array &$existentes,
        array &$vencimientos,
        ?int $usuarioId,
        bool $incluirEspeciales
    ): array {
        $creadas = 0;
        $omitidas = 0;
        $nombres = [];

        if ($incluirEspeciales) {
            $esp = $this->aplicarEspeciales($persona, $pendientes, $existentes, $vencimientos, $usuarioId);
            $creadas += $esp['creadas'];
            $omitidas += $esp['omitidas'];
            $nombres = $esp['creadas_especiales'];
        }

        $mat = $this->aplicarMatriz($persona, $reglas, $pendientes, $usuarioId);
        $creadas += $mat['creadas'];
        $omitidas += $mat['omitidas'];

        return [
            'creadas' => $creadas,
            'omitidas' => $omitidas,
            'creadas_especiales' => $nombres,
        ];
    }

    /**
     * @param array<string,mixed> $persona
     * @param array<string,true> $pendientes
     * @param array<string,true> $existentes
     * @param array<string,string|null> $vencimientos
     * @return array{creadas:int, omitidas:int, creadas_especiales:list<string>}
     */
    private function aplicarEspeciales(
        array $persona,
        array &$pendientes,
        array &$existentes,
        array &$vencimientos,
        ?int $usuarioId
    ): array {
        $personaId = (int)($persona['persona_id'] ?? 0);
        if ($personaId <= 0 || !$this->tieneFechaIngreso($persona)) {
            return ['creadas' => 0, 'omitidas' => 0, 'creadas_especiales' => []];
        }

        $hoy = (new DateTimeImmutable('today'))->format('Y-m-d');
        $creadas = 0;
        $omitidas = 0;
        $nombres = [];
        $cargoId = (int)($persona['cargo_id'] ?? 0);
        $contratoId = $persona['contrato_id'] ?? null;
        $proyectoPersona = is_string($persona['proyecto'] ?? null) ? trim((string)$persona['proyecto']) : '';

        foreach ($this->capsEspeciales() as $cap) {
            $capacitacionId = (int)$cap['capacitacion_id'];
            $clave = $personaId . ':' . $capacitacionId;
            $origen = (string)$cap['origen'];

            if ($origen === 'INDUCCION') {
                if (isset($existentes[$clave])) {
                    $omitidas++;
                    continue;
                }
            } else {
                if (isset($pendientes[$clave])) {
                    $omitidas++;
                    continue;
                }
                if (isset($existentes[$clave]) && $this->reinduccionSigueVigente($vencimientos[$clave] ?? null, $hoy)) {
                    $omitidas++;
                    continue;
                }
            }

            $this->asignaciones->crear([
                'persona_id_ext' => $personaId,
                'contrato_id_ext' => $contratoId !== null ? (int)$contratoId : null,
                'capacitacion_id' => $capacitacionId,
                'matriz_aplicabilidad_id' => null,
                'fecha_asignacion' => $hoy,
                'fecha_limite_cumplimiento' => $this->fechaLimiteDesdePeriodicidad(
                    $cap['per_cantidad'] !== null ? (int)$cap['per_cantidad'] : null,
                    $cap['per_unidad'] !== null ? (string)$cap['per_unidad'] : null
                ),
                'origen' => $origen,
                'cargo_id_ext' => $cargoId > 0 ? $cargoId : null,
                'area_id' => null,
                'proceso_id' => null,
                'ambito' => null,
                'proyecto' => $proyectoPersona !== '' ? $proyectoPersona : null,
                'creada_por_usuario_id_ext' => $usuarioId,
            ]);

            $pendientes[$clave] = true;
            $existentes[$clave] = true;
            $creadas++;
            $nombres[] = (string)$cap['nombre'];
        }

        return [
            'creadas' => $creadas,
            'omitidas' => $omitidas,
            'creadas_especiales' => $nombres,
        ];
    }

    /**
     * @param array<string,mixed> $persona
     * @param list<array<string,mixed>> $reglas
     * @param array<string,true> $pendientes
     * @return array{creadas:int, omitidas:int}
     */
    private function aplicarMatriz(array $persona, array $reglas, array &$pendientes, ?int $usuarioId): array
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

    /** @return list<array<string,mixed>> */
    private function capsEspeciales(): array
    {
        return $this->especialesCache ??= $this->capacitaciones->activasInduccionReinduccion();
    }

    /** @param array{capacitacion_id?:int, proyecto?:string} $filtro */
    private function hayFiltro(array $filtro): bool
    {
        $capId = isset($filtro['capacitacion_id']) ? (int)$filtro['capacitacion_id'] : 0;
        $proyecto = isset($filtro['proyecto']) ? trim((string)$filtro['proyecto']) : '';

        return $capId > 0 || $proyecto !== '';
    }

    /** @param array<string,mixed> $persona */
    private function tieneFechaIngreso(array $persona): bool
    {
        $bruto = trim((string)($persona['contrato_fecha_inicio'] ?? ''));
        if ($bruto === '') {
            return false;
        }

        $fecha = substr($bruto, 0, 10);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $fecha);

        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $fecha;
    }

    private function reinduccionSigueVigente(?string $fechaVencimiento, string $hoy): bool
    {
        if ($fechaVencimiento === null || $fechaVencimiento === '') {
            return true;
        }

        return substr($fechaVencimiento, 0, 10) >= $hoy;
    }
}
