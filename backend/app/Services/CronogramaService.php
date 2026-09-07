<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Repositories\CronogramaRepository;
use App\Repositories\MatrizRepository;
use App\Repositories\PlanAnualRepository;
use App\Repositories\SesionRepository;
use PDOException;

class CronogramaService
{
    /** @var list<string> */
    private const PROYECTOS_OBRA = ['FRONTERA'];

    private CronogramaRepository $repo;
    private DashboardService $periodos;
    private SesionRepository $sesiones;
    private SesionService $sesionService;
    private MatrizRepository $matriz;
    private PersonalService $personal;
    private AlertaRepository $alertas;
    private PlanAnualRepository $planes;

    public function __construct()
    {
        $this->repo = new CronogramaRepository();
        $this->periodos = new DashboardService();
        $this->sesiones = new SesionRepository();
        $this->sesionService = new SesionService();
        $this->matriz = new MatrizRepository();
        $this->personal = new PersonalService();
        $this->alertas = new AlertaRepository();
        $this->planes = new PlanAnualRepository();
    }

    /**
     * @param array<string,mixed> $filtros
     * @return array<string,mixed>
     */
    public function tablero(array $filtros): array
    {
        $periodo = $this->periodos->periodo($filtros);
        $procesoId = isset($filtros['proceso_id']) && $filtros['proceso_id'] !== null && $filtros['proceso_id'] !== ''
            ? (int)$filtros['proceso_id']
            : null;
        $proyecto = $this->proyectoFiltro($procesoId, $filtros['proyecto'] ?? null);
        $buscar = nullable_trimmed_string($filtros['buscar'] ?? null);

        $filas = $this->repo->programadas($periodo, $procesoId, $proyecto, $buscar);
        $procesos = $this->alertas->procesosActivos();
        $detalleIds = array_map(static fn (array $fila): int => (int)$fila['plan_detalle_id'], $filas);
        $sesionesPorDetalle = $this->sesionesPorDetalle($detalleIds);
        $porMes = [];
        $items = [];
        foreach ($filas as $fila) {
            $item = $this->item($fila, $sesionesPorDetalle);
            $items[] = $item;
            $porMes[(int)$fila['mes_programado']][] = $item;
        }

        $meses = [];
        foreach ($periodo['meses'] as $mes) {
            $itemsMes = $porMes[$mes] ?? [];
            $meses[] = [
                'mes' => $mes,
                'nombre' => $this->nombreMes($mes),
                'total' => count($itemsMes),
                'items' => $itemsMes,
            ];
        }

        $procesoNombre = null;
        if ($procesoId !== null) {
            foreach ($procesos as $proceso) {
                if ($proceso['proceso_id'] === $procesoId) {
                    $procesoNombre = $proceso['nombre'];
                    break;
                }
            }
        }

        return [
            'periodo' => [
                'tipo' => $periodo['tipo'],
                'anio' => $periodo['anio'],
                'mes' => $periodo['mes'],
                'trimestre' => $periodo['trimestre'],
                'semestre' => $periodo['semestre'] ?? null,
                'etiqueta' => $periodo['etiqueta'],
            ],
            'proceso_id' => $procesoId,
            'proceso_nombre' => $procesoNombre,
            'proyecto' => $proyecto,
            'total' => count($items),
            'estado_plan' => 'APROBADO',
            'procesos' => $procesos,
            'proyectos' => self::PROYECTOS_OBRA,
            'items' => $items,
            'meses' => $meses,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function ver(int $detalleId): array
    {
        $fila = $this->exigirAprobado($detalleId);
        $sesiones = $this->sesionesPorDetalle([$detalleId]);

        return $this->item($fila, $sesiones);
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,cantidad_programada:int}
     */
    public function trabajadores(int $detalleId): array
    {
        $fila = $this->exigirAprobado($detalleId);
        $procesoId = $fila['proceso_id'] !== null ? (int)$fila['proceso_id'] : 0;
        $proyecto = $fila['proyecto'] !== null && $fila['proyecto'] !== '' ? (string)$fila['proyecto'] : null;
        $cargos = $procesoId > 0
            ? $this->cargosAplicables((int)$fila['capacitacion_id'], $procesoId, $proyecto)
            : [];
        $cargoIds = array_map(static fn (array $c): int => (int)$c['cargo_id'], $cargos);
        $filas = $this->repo->trabajadoresProgramados((int)$fila['capacitacion_id'], $cargoIds);

        $items = [];
        foreach ($filas as $t) {
            $items[] = [
                'asignacion_id' => (int)$t['asignacion_id'],
                'persona_id_ext' => (int)$t['persona_id_ext'],
                'numero_documento' => (string)($t['numero_documento'] ?? ''),
                'persona_nombre' => (string)($t['persona_nombre'] ?? ''),
                'nombre_cargo' => $t['nombre_cargo'] !== null && $t['nombre_cargo'] !== ''
                    ? (string)$t['nombre_cargo']
                    : null,
                'estado_asignacion' => (string)($t['estado_calculado'] ?? ''),
            ];
        }

        return [
            'items' => $items,
            'total' => count($items),
            'cantidad_programada' => (int)$fila['cantidad_programada'],
        ];
    }

    /**
     * @param array<string,mixed> $datos
     * @return array<string,mixed>
     */
    public function reprogramar(int $detalleId, array $datos): array
    {
        $fila = $this->exigirAprobado($detalleId);
        if (strtoupper((string)($fila['estado_programacion'] ?? 'PROGRAMADA')) === 'CANCELADA') {
            throw new HttpException('No es posible reprogramar una programación cancelada.', 409);
        }

        $anio = (int)$fila['anio'];
        $fecha = $this->fechaEnAnio((string)($datos['fecha_programada'] ?? ''), $anio);
        $mes = (int)substr($fecha, 5, 2);
        $procesoId = $fila['proceso_id'] !== null ? (int)$fila['proceso_id'] : null;
        $proyecto = $fila['proyecto'] !== null && $fila['proyecto'] !== '' ? (string)$fila['proyecto'] : null;

        $duplicado = $this->planes->buscarDetalleActividad(
            (int)$fila['plan_anual_id'],
            (int)$fila['capacitacion_id'],
            $procesoId,
            $proyecto,
            $fecha,
            $detalleId
        );
        if ($duplicado !== null) {
            throw new HttpException(
                'Ya existe una actividad con la misma capacitación, proceso, proyecto y fecha.',
                409
            );
        }

        try {
            $this->planes->actualizarDetalle($detalleId, [
                'fecha_programada' => $fecha,
                'mes_programado' => $mes,
            ]);
        } catch (PDOException $e) {
            throw new HttpException('No fue posible guardar la programación.', 500);
        }

        return $this->ver($detalleId);
    }

    /**
     * @return array<string,mixed>
     */
    public function cancelar(int $detalleId): array
    {
        $fila = $this->exigirAprobado($detalleId);
        if (strtoupper((string)($fila['estado_programacion'] ?? 'PROGRAMADA')) === 'CANCELADA') {
            throw new HttpException('La programación ya está cancelada.', 409);
        }

        try {
            $this->planes->actualizarDetalle($detalleId, [
                'estado_programacion' => 'CANCELADA',
            ]);
        } catch (PDOException $e) {
            throw new HttpException('No fue posible guardar la programación.', 500);
        }

        return $this->ver($detalleId);
    }

    /**
     * @return array<string,mixed>
     */
    public function iniciar(int $detalleId, int $usuarioId): array
    {
        $fila = $this->exigirAprobado($detalleId);
        if (strtoupper((string)($fila['estado_programacion'] ?? 'PROGRAMADA')) === 'CANCELADA') {
            throw new HttpException('No es posible iniciar una programación cancelada.', 409);
        }

        $existentes = $this->sesionesPorDetalle([$detalleId])[$detalleId] ?? [];
        foreach ($existentes as $sesion) {
            $estado = strtoupper((string)($sesion['estado'] ?? ''));
            if ($estado === 'PROGRAMADA') {
                return $this->ver($detalleId);
            }
            if ($estado === 'EJECUTADA') {
                throw new HttpException('La capacitación ya fue finalizada.', 409);
            }
        }

        $fecha = $fila['fecha_programada'] !== null && $fila['fecha_programada'] !== ''
            ? substr((string)$fila['fecha_programada'], 0, 10)
            : sprintf('%04d-%02d-01', (int)$fila['anio'], (int)$fila['mes_programado']);
        $lista = $this->trabajadores($detalleId);
        $asignacionIds = array_map(
            static fn (array $t): int => (int)$t['asignacion_id'],
            $lista['items']
        );
        $cupo = max(count($asignacionIds), (int)$fila['cantidad_programada'], 1);
        $catalogo = $this->datosInicioSesion($fila);

        $this->sesionService->crear([
            'plan_detalle_id' => $detalleId,
            'fecha' => $fecha,
            'hora' => '08:00',
            'modalidad_id' => $catalogo['modalidad_id'],
            'ubicacion_id' => $catalogo['ubicacion_id'],
            'enlace_virtual' => $catalogo['enlace_virtual'],
            'proveedor_id' => $catalogo['proveedor_id'],
            'cupo_maximo' => $cupo,
            'asignacion_ids' => $asignacionIds,
        ], $usuarioId);

        return $this->ver($detalleId);
    }

    /**
     * @param array<string,mixed> $fila
     * @return array{modalidad_id:int,proveedor_id:int,ubicacion_id:?int,enlace_virtual:?string}
     */
    private function datosInicioSesion(array $fila): array
    {
        $modalidades = $this->sesiones->catalogoListar('modalidades', 'modalidad_id');
        $modalidadId = isset($fila['modalidad_default_id']) ? (int)$fila['modalidad_default_id'] : 0;
        $nombreModalidad = is_string($fila['metodologia'] ?? null) ? (string)$fila['metodologia'] : '';
        if ($modalidadId < 1) {
            if ($modalidades === []) {
                throw new HttpException('No hay modalidades activas en el catálogo.', 422);
            }
            $modalidadId = (int)$modalidades[0]['modalidad_id'];
            $nombreModalidad = (string)($modalidades[0]['nombre'] ?? '');
        } elseif ($nombreModalidad === '') {
            foreach ($modalidades as $modalidad) {
                if ((int)$modalidad['modalidad_id'] === $modalidadId) {
                    $nombreModalidad = (string)($modalidad['nombre'] ?? '');
                    break;
                }
            }
        }

        $proveedores = $this->sesiones->catalogoListar('proveedores_capacitadores', 'proveedor_id');
        $proveedorId = isset($fila['proveedor_default_id']) ? (int)$fila['proveedor_default_id'] : 0;
        if ($proveedorId < 1) {
            if ($proveedores === []) {
                throw new HttpException('No hay proveedores o capacitadores activos en el catálogo.', 422);
            }
            $proveedorId = (int)$proveedores[0]['proveedor_id'];
        }

        $clave = mb_strtoupper($nombreModalidad, 'UTF-8');
        $esVirtual = str_contains($clave, 'VIRTUAL');
        $esPresencial = str_contains($clave, 'PRESENCIAL');
        $esMixta = str_contains($clave, 'MIXTA');
        $ubicacionId = null;
        $enlace = null;
        if ($esPresencial || $esMixta) {
            $ubicaciones = $this->sesiones->catalogoListar('ubicaciones', 'ubicacion_id');
            if ($ubicaciones === []) {
                throw new HttpException('No hay ubicaciones activas en el catálogo.', 422);
            }
            $ubicacionId = (int)$ubicaciones[0]['ubicacion_id'];
        }
        if ($esVirtual || $esMixta) {
            throw new HttpException(
                'Para iniciar una capacitación virtual o mixta debe crear la sesión con el enlace correspondiente.',
                422
            );
        }

        return [
            'modalidad_id' => $modalidadId,
            'proveedor_id' => $proveedorId,
            'ubicacion_id' => $ubicacionId,
            'enlace_virtual' => $enlace,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function exigirAprobado(int $detalleId): array
    {
        $fila = $this->repo->buscarProgramacion($detalleId);
        if ($fila === null) {
            throw new HttpException('La programación no existe.', 404);
        }
        if (strtoupper((string)($fila['plan_estado'] ?? '')) !== 'APROBADO') {
            throw new HttpException('El plan no se encuentra aprobado.', 409);
        }

        return $fila;
    }

    private function proyectoFiltro(?int $procesoId, mixed $proyecto): ?string
    {
        if ($procesoId === null || $procesoId < 1) {
            return null;
        }
        if (!$this->alertas->procesoEsGestionProyectos($procesoId)) {
            return null;
        }

        $normalizado = nullable_trimmed_string($proyecto);
        if ($normalizado === null || $normalizado === '') {
            return null;
        }

        $clave = mb_strtoupper($normalizado, 'UTF-8');
        foreach (self::PROYECTOS_OBRA as $canonico) {
            if (mb_strtoupper($canonico, 'UTF-8') === $clave) {
                return $canonico;
            }
        }

        throw new HttpException('El proyecto no es válido.', 422);
    }

    private function fechaEnAnio(string $fecha, int $anio): string
    {
        $fecha = trim($fecha);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        $errores = \DateTimeImmutable::getLastErrors();
        $conError = is_array($errores)
            && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0);
        if ($dt === false || $conError) {
            throw new HttpException('La fecha de programación no es válida.', 422);
        }
        if ((int)$dt->format('Y') !== $anio) {
            throw new HttpException('La fecha debe pertenecer al año del plan.', 422);
        }

        return $dt->format('Y-m-d');
    }

    /**
     * @param array<string,mixed> $fila
     * @param array<int, list<array<string,mixed>>> $sesionesPorDetalle
     * @return array<string,mixed>
     */
    private function item(array $fila, array $sesionesPorDetalle): array
    {
        $mes = (int)$fila['mes_programado'];
        $horas = $fila['duracion_estimada_horas'];
        $metodologia = $fila['metodologia'] ?? null;
        $detalleId = (int)$fila['plan_detalle_id'];
        $sesiones = $sesionesPorDetalle[$detalleId] ?? [];
        $procesoId = $fila['proceso_id'] !== null ? (int)$fila['proceso_id'] : null;
        $proyecto = $fila['proyecto'] !== null && $fila['proyecto'] !== '' ? (string)$fila['proyecto'] : null;
        $cargos = ($procesoId !== null && $procesoId > 0)
            ? $this->cargosAplicables((int)$fila['capacitacion_id'], $procesoId, $proyecto)
            : [];

        return [
            'plan_detalle_id' => $detalleId,
            'plan_anual_id' => (int)$fila['plan_anual_id'],
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'codigo' => (string)$fila['codigo'],
            'tema' => (string)$fila['nombre'],
            'objetivo' => (string)$fila['objetivo'],
            'horas' => $horas !== null && $horas !== '' ? round((float)$horas, 2) : null,
            'metodologia' => is_string($metodologia) && $metodologia !== '' ? $metodologia : null,
            'mes' => $mes,
            'mes_nombre' => $this->nombreMes($mes),
            'fecha_programada' => $fila['fecha_programada'] !== null
                ? substr((string)$fila['fecha_programada'], 0, 10)
                : null,
            'cantidad_programada' => (int)$fila['cantidad_programada'],
            'anio' => (int)$fila['anio'],
            'proceso_id' => $procesoId,
            'proceso_nombre' => $fila['proceso_nombre'] !== null && $fila['proceso_nombre'] !== ''
                ? (string)$fila['proceso_nombre']
                : null,
            'ambito' => $fila['ambito'] !== null && $fila['ambito'] !== '' ? (string)$fila['ambito'] : null,
            'proyecto' => $proyecto,
            'estado_programacion' => strtoupper((string)($fila['estado_programacion'] ?? 'PROGRAMADA')),
            'estado_operativo' => $this->estadoOperativo(
                (string)($fila['estado_programacion'] ?? 'PROGRAMADA'),
                $sesiones
            ),
            'requiere_evaluacion' => (int)($fila['evaluacion'] ?? 0) === 1,
            'requiere_certificado' => (int)($fila['certificado'] ?? 0) === 1,
            'vigencia_id' => $fila['vigencia_id'] !== null ? (int)$fila['vigencia_id'] : null,
            'vigencia_nombre' => $fila['vigencia_nombre'] !== null && $fila['vigencia_nombre'] !== ''
                ? (string)$fila['vigencia_nombre']
                : null,
            'vigencia_cantidad' => $fila['vigencia_cantidad'] !== null ? (int)$fila['vigencia_cantidad'] : null,
            'vigencia_unidad' => $fila['vigencia_unidad'] !== null && $fila['vigencia_unidad'] !== ''
                ? (string)$fila['vigencia_unidad']
                : null,
            'cargos_aplicables' => $cargos,
            'sesiones' => $sesiones,
        ];
    }

    /**
     * @param list<array<string,mixed>> $sesiones
     */
    private function estadoOperativo(string $estadoProgramacion, array $sesiones): string
    {
        if (strtoupper($estadoProgramacion) === 'CANCELADA') {
            return 'CANCELADA';
        }
        if ($sesiones === []) {
            return 'PROGRAMADA';
        }

        $hayProgramada = false;
        foreach ($sesiones as $sesion) {
            if (strtoupper((string)($sesion['estado'] ?? '')) === 'PROGRAMADA') {
                $hayProgramada = true;
                break;
            }
        }

        if ($hayProgramada) {
            return 'EN_EJECUCION';
        }

        return 'FINALIZADA';
    }

    /**
     * @return list<array{cargo_id:int,nombre_cargo:string}>
     */
    private function cargosAplicables(int $capacitacionId, int $procesoId, ?string $proyecto): array
    {
        $filas = $this->matriz->cargosActivosDeCapacitacion($capacitacionId, $procesoId, $proyecto);
        $ids = [];
        foreach ($filas as $fila) {
            $id = (int)($fila['cargo_id_ext'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $nombres = $this->personal->nombresCargosPorIds(array_values($ids));
        $salida = [];
        foreach ($ids as $id) {
            $salida[] = [
                'cargo_id' => $id,
                'nombre_cargo' => $nombres[$id] ?? ('Cargo ' . $id),
            ];
        }
        usort(
            $salida,
            static fn (array $a, array $b): int => strcasecmp((string)$a['nombre_cargo'], (string)$b['nombre_cargo'])
        );

        return $salida;
    }

    /**
     * @param list<int> $detalleIds
     * @return array<int, list<array<string,mixed>>>
     */
    private function sesionesPorDetalle(array $detalleIds): array
    {
        $mapa = [];
        foreach ($this->sesionService->resumir($this->sesiones->listarPorDetalles($detalleIds)) as $sesion) {
            $detalleId = (int)($sesion['plan_detalle_id'] ?? 0);
            if ($detalleId < 1) {
                continue;
            }
            $mapa[$detalleId][] = $sesion;
        }

        return $mapa;
    }

    private function nombreMes(int $mes): string
    {
        $nombres = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $nombres[$mes] ?? (string)$mes;
    }
}
