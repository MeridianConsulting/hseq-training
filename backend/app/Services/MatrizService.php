<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Repositories\CapacitacionRepository;
use App\Repositories\MatrizRepository;
use App\Repositories\PersonalRepository;

class MatrizService
{
    private const MENSAJE_DUPLICADO = 'La capacitación ya está asociada a este cargo, proceso y proyecto.';

    /** @var array<string,string> */
    public const CAMPOS_AUDITABLES = [
        'capacitacion_nombre' => 'Capacitación',
        'cargo_nombre' => 'Cargo',
        'proceso_nombre' => 'Proceso',
        'proyecto' => 'Proyecto',
        'aplica' => 'Aplica',
    ];

    private MatrizRepository $repo;
    private CapacitacionRepository $capacitaciones;
    private PersonalService $personal;
    private AlertaRepository $alertas;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->repo = new MatrizRepository();
        $this->capacitaciones = new CapacitacionRepository();
        $this->personal = new PersonalService();
        $this->alertas = new AlertaRepository();
        $this->auditoria = new AuditoriaService();
    }

    public function reglas(bool $esActualizacion = false): array
    {
        $obligatorio = $esActualizacion ? 'nullable' : 'required';

        return [
            'capacitacion_id' => ($esActualizacion ? 'nullable' : 'required') . '|integer',
            'cargo_id_ext' => $obligatorio . '|integer',
            'area_id' => 'nullable|integer',
            'proceso_id' => $obligatorio . '|integer',
            'ambito' => 'nullable|in:ADMINISTRACION,PROYECTO',
            'proyecto' => 'nullable|string|max:120',
            'periodicidad_id' => 'nullable|integer',
            'obligatoria' => 'nullable|integer|min:0|max:1',
            'activa' => 'nullable|integer|min:0|max:1',
        ];
    }

    public function reglasMasiva(): array
    {
        return [
            'capacitacion_id' => 'required|integer',
            'cargo_ids_ext' => 'required|array',
            'area_id' => 'nullable|integer',
            'proceso_id' => 'required|integer',
            'ambito' => 'nullable|in:ADMINISTRACION,PROYECTO',
            'proyecto' => 'nullable|string|max:120',
            'periodicidad_id' => 'nullable|integer',
            'obligatoria' => 'nullable|integer|min:0|max:1',
        ];
    }

    public function reglasSincronizar(): array
    {
        return [
            'proceso_id' => 'required|integer',
            'proyecto' => 'nullable|string|max:120',
            'aplica' => 'nullable|array',
        ];
    }

    /**
     * @param array{capacitacion_id?:?int, cargo_id_ext?:?int, proceso_id?:?int, proyecto?:?string, activa?:?int} $filtros
     */
    public function listar(int $pagina, int $porPagina, array $filtros): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        $filas = $this->repo->listar($porPagina, $offset, $filtros);
        $nombresCargo = $this->personal->nombresCargosPorIds(array_column($filas, 'cargo_id_ext'));

        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->normalizar($fila, $nombresCargo);
        }

        return [
            'items' => $items,
            'total' => $this->repo->contar($filtros),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    public function ver(int $id): array
    {
        $fila = $this->repo->buscarPorId($id);

        if ($fila === null) {
            throw new HttpException('Registro de matriz no encontrado', 404);
        }

        $nombres = $this->personal->nombresCargosPorIds([$fila['cargo_id_ext'] ?? null]);

        return $this->normalizar($fila, $nombres);
    }

    public function aplicables(?int $cargoId, ?int $procesoId, ?string $proyecto): array
    {
        $filas = $this->repo->aplicables($cargoId, $procesoId, $proyecto);
        $nombresCargo = $this->personal->nombresCargosPorIds(array_column($filas, 'cargo_id_ext'));

        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->normalizar($fila, $nombresCargo);
        }

        return [
            'total' => count($items),
            'items' => $items,
        ];
    }

    /**
     * @return array{procesos:list<array{proceso_id:int,nombre:string}>,proyectos:list<string>,cargos:list<array{cargo_id:int,nombre_cargo:string}>,capacitaciones:list<array{capacitacion_id:int,codigo:string,nombre:string,es_tarea_critica:bool}>}
     */
    public function opciones(): array
    {
        return [
            'procesos' => $this->alertas->procesosActivos(),
            'proyectos' => $this->alertas->proyectos(),
            'cargos' => $this->personal->cargos(),
            'capacitaciones' => $this->capacitaciones->listarActivasResumen(),
        ];
    }

    public function vista(?int $procesoId, ?string $proyecto): array
    {
        if ($procesoId === null || $procesoId < 1) {
            throw new HttpException('El proceso es obligatorio.', 422);
        }

        $proceso = $this->procesoDeMatriz($procesoId);
        $proyectoNorm = $this->proyectoSegunProceso($procesoId, $proyecto, true, true);

        $filas = $this->repo->listarContexto($procesoId, $proyectoNorm);
        $celdas = [];
        foreach ($filas as $fila) {
            $clave = (int)$fila['cargo_id_ext'] . ':' . (int)$fila['capacitacion_id'];
            $activa = (int)$fila['activa'] === 1;
            if (!isset($celdas[$clave]) || $activa) {
                $celdas[$clave] = [
                    'cargo_id_ext' => (int)$fila['cargo_id_ext'],
                    'capacitacion_id' => (int)$fila['capacitacion_id'],
                    'matriz_aplicabilidad_id' => (int)$fila['matriz_aplicabilidad_id'],
                    'activa' => $activa,
                ];
            }
        }

        $catalogo = $this->personal->cargos();

        return [
            'proceso_id' => $procesoId,
            'proceso_nombre' => $proceso['nombre'],
            'proyecto' => $proyectoNorm,
            'cargos' => $this->cargosDeVista($filas, $catalogo, $proceso['nombre']),
            'cargos_catalogo' => $catalogo,
            'capacitaciones' => $this->capacitaciones->listarActivasResumen(),
            'celdas' => array_values($celdas),
        ];
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     * @return array{creadas:int, reactivadas:int, inactivadas:int, sin_cambio:int, agregadas:list<array<string,mixed>>, reactivadas_detalle:list<array<string,mixed>>, retiradas:list<array<string,mixed>>, vista:array<string,mixed>}
     */
    public function sincronizar(array $entrada, int $usuarioId, ?array $actor = null): array
    {
        $procesoId = (int)($entrada['proceso_id'] ?? 0);
        if ($procesoId < 1) {
            throw new HttpException('El proceso es obligatorio.', 422);
        }

        $this->procesoDeMatriz($procesoId);
        $proyecto = $this->proyectoSegunProceso($procesoId, $entrada['proyecto'] ?? null, true, true);
        $ambito = $this->ambitoDeProceso($procesoId);
        $aplica = $this->normalizarAplica($entrada['aplica'] ?? []);

        $existentes = $this->repo->listarContexto($procesoId, $proyecto);
        $porCelda = [];
        foreach ($existentes as $fila) {
            $clave = (int)$fila['cargo_id_ext'] . ':' . (int)$fila['capacitacion_id'];
            $porCelda[$clave][] = $fila;
        }

        $creadas = 0;
        $reactivadas = 0;
        $inactivadas = 0;
        $sinCambio = 0;
        $agregadas = [];
        $reactivadasDetalle = [];
        $retiradas = [];

        $this->repo->transaccion(function () use (
            $aplica,
            $porCelda,
            $procesoId,
            $proyecto,
            $ambito,
            $usuarioId,
            &$creadas,
            &$reactivadas,
            &$inactivadas,
            &$sinCambio,
            &$agregadas,
            &$reactivadasDetalle,
            &$retiradas
        ): void {
            foreach ($aplica as $clave => $par) {
                $filas = $porCelda[$clave] ?? [];
                if ($filas === []) {
                    $this->repo->crear([
                        'capacitacion_id' => $par['capacitacion_id'],
                        'cargo_id_ext' => $par['cargo_id_ext'],
                        'area_id' => null,
                        'proceso_id' => $procesoId,
                        'ambito' => $ambito,
                        'proyecto' => $proyecto,
                        'periodicidad_id' => null,
                        'obligatoria' => 1,
                        'activa' => 1,
                        'creado_por_usuario_id_ext' => $usuarioId,
                    ]);
                    $creadas++;
                    $agregadas[] = $this->celdaMatriz([
                        'cargo_id_ext' => $par['cargo_id_ext'],
                        'capacitacion_id' => $par['capacitacion_id'],
                    ]);
                    continue;
                }

                $primera = array_shift($filas);
                if ((int)$primera['activa'] !== 1) {
                    $this->repo->activar((int)$primera['matriz_aplicabilidad_id']);
                    $reactivadas++;
                    $reactivadasDetalle[] = $this->celdaMatriz($primera);
                } else {
                    $sinCambio++;
                }

                foreach ($filas as $extra) {
                    if ((int)$extra['activa'] === 1) {
                        $this->repo->inactivar((int)$extra['matriz_aplicabilidad_id']);
                        $inactivadas++;
                        $retiradas[] = $this->celdaMatriz($extra);
                    }
                }
            }

            foreach ($porCelda as $clave => $filas) {
                if (isset($aplica[$clave])) {
                    continue;
                }
                foreach ($filas as $fila) {
                    if ((int)$fila['activa'] === 1) {
                        $this->repo->inactivar((int)$fila['matriz_aplicabilidad_id']);
                        $inactivadas++;
                        $retiradas[] = $this->celdaMatriz($fila);
                    }
                }
            }
        });

        if ($actor !== null) {
            $this->auditoria->deActor(
                $actor,
                'sincronizar',
                'matriz_aplicabilidad',
                $procesoId,
                [
                    'proceso_id' => $procesoId,
                    'proyecto' => $proyecto,
                    'creadas' => $creadas,
                    'reactivadas' => $reactivadas,
                    'inactivadas' => $inactivadas,
                    'sin_cambio' => $sinCambio,
                    'agregadas' => $agregadas,
                    'reactivadas_detalle' => $reactivadasDetalle,
                    'retiradas' => $retiradas,
                ]
            );
        }

        return [
            'creadas' => $creadas,
            'reactivadas' => $reactivadas,
            'inactivadas' => $inactivadas,
            'sin_cambio' => $sinCambio,
            'agregadas' => $agregadas,
            'reactivadas_detalle' => $reactivadasDetalle,
            'retiradas' => $retiradas,
            'vista' => $this->vista($procesoId, $proyecto),
        ];
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function crear(array $datos, int $usuarioId, ?array $actor = null): array
    {
        $datos = $this->preparar($datos);
        $this->aplicarContextoObligatorio($datos, false);
        $this->validarReferencias($datos, false);

        if ($this->repo->duplicado($datos)) {
            throw new HttpException(self::MENSAJE_DUPLICADO, 409);
        }

        $datos['creado_por_usuario_id_ext'] = $usuarioId;
        $id = $this->repo->crear($datos);
        $creado = $this->ver($id);
        if ($actor !== null) {
            $this->auditoria->deActor(
                $actor,
                'crear',
                'matriz_aplicabilidad',
                $id,
                $this->vistaAuditoria($creado)
            );
        }

        return $creado;
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     * @return array{creadas:int, omitidas:int, items:list<array<string,mixed>>, omitidas_detalle:list<array<string,mixed>>}
     */
    public function asociarMasivo(array $entrada, int $usuarioId, ?array $actor = null): array
    {
        $base = $this->preparar([
            'capacitacion_id' => $entrada['capacitacion_id'] ?? null,
            'area_id' => $entrada['area_id'] ?? null,
            'proceso_id' => $entrada['proceso_id'] ?? null,
            'ambito' => $entrada['ambito'] ?? null,
            'proyecto' => $entrada['proyecto'] ?? null,
            'periodicidad_id' => $entrada['periodicidad_id'] ?? null,
            'obligatoria' => $entrada['obligatoria'] ?? 1,
            'activa' => 1,
        ]);

        $this->aplicarContextoObligatorio($base, false);
        $this->validarReferencias($base, true);

        $cargos = $this->normalizarCargosMasivos($entrada['cargo_ids_ext'] ?? []);

        $creadasIds = [];
        $omitidas = [];

        $this->repo->transaccion(function () use ($base, $cargos, $usuarioId, &$creadasIds, &$omitidas): int {
            foreach ($cargos as $cargoId) {
                $fila = $base;
                $fila['cargo_id_ext'] = $cargoId;
                $fila['creado_por_usuario_id_ext'] = $usuarioId;

                if ($this->repo->duplicado($fila)) {
                    $omitidas[] = [
                        'cargo_id_ext' => $cargoId,
                        'motivo' => self::MENSAJE_DUPLICADO,
                    ];
                    continue;
                }

                $creadasIds[] = $this->repo->crear($fila);
            }

            return count($creadasIds);
        });

        $items = [];
        foreach ($creadasIds as $id) {
            $items[] = $this->ver($id);
        }

        if ($actor !== null) {
            $this->auditoria->deActor(
                $actor,
                'asociar_masivo',
                'matriz_aplicabilidad',
                isset($base['capacitacion_id']) ? (int)$base['capacitacion_id'] : null,
                [
                    'capacitacion_id' => $base['capacitacion_id'] ?? null,
                    'proceso_id' => $base['proceso_id'] ?? null,
                    'proyecto' => $base['proyecto'] ?? null,
                    'creadas' => count($items),
                    'omitidas' => count($omitidas),
                    'matriz_ids' => $creadasIds,
                    'omitidas_detalle' => $omitidas,
                ]
            );
        }

        return [
            'creadas' => count($items),
            'omitidas' => count($omitidas),
            'items' => $items,
            'omitidas_detalle' => $omitidas,
        ];
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function actualizar(int $id, array $datos, ?array $actor = null): array
    {
        $actual = $this->ver($id);
        $datos = $this->preparar($datos, true);

        $combinado = array_merge([
            'capacitacion_id' => $actual['capacitacion_id'],
            'cargo_id_ext' => $actual['cargo_id_ext'],
            'area_id' => $actual['area_id'],
            'proceso_id' => $actual['proceso_id'],
            'ambito' => $actual['ambito'],
            'proyecto' => $actual['proyecto'],
        ], $datos);

        $this->aplicarContextoObligatorio($combinado, true);
        if (array_key_exists('proyecto', $datos) || array_key_exists('proceso_id', $datos)) {
            $datos['proyecto'] = $combinado['proyecto'] ?? null;
        }
        $this->validarReferencias($combinado, false);

        if ($this->repo->duplicado($combinado, $id)) {
            throw new HttpException(self::MENSAJE_DUPLICADO, 409);
        }

        if ($datos !== []) {
            $this->repo->actualizar($id, $datos);
        }

        $despues = $this->ver($id);
        if ($actor !== null) {
            $antesVista = $this->vistaAuditoria($actual);
            $despuesVista = $this->vistaAuditoria($despues);
            $cambios = $this->auditoria->diff($antesVista, $despuesVista, self::CAMPOS_AUDITABLES);
            $accion = 'actualizar';
            if (($antesVista['aplica'] ?? null) === 'Aplica' && ($despuesVista['aplica'] ?? null) === 'No aplica') {
                $accion = 'inactivar';
            } elseif (($antesVista['aplica'] ?? null) === 'No aplica' && ($despuesVista['aplica'] ?? null) === 'Aplica') {
                $accion = 'reactivar';
            }
            if ($cambios !== []) {
                $this->auditoria->deActor(
                    $actor,
                    $accion,
                    'matriz_aplicabilidad',
                    $id,
                    $this->auditoria->payloadNuevo($cambios, AuditoriaService::ORIGEN_USUARIO),
                    $antesVista
                );
            }
        }

        return $despues;
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string}|null $actor
     */
    public function eliminar(int $id, ?array $actor = null): string
    {
        $actual = $this->ver($id);

        if ((int)$actual['activa'] === 0 || $actual['activa'] === false) {
            return 'El registro ya está inactivo.';
        }

        $this->repo->inactivar($id);
        if ($actor !== null) {
            $despues = $this->ver($id);
            $cambios = $this->auditoria->diff(
                $this->vistaAuditoria($actual),
                $this->vistaAuditoria($despues),
                self::CAMPOS_AUDITABLES
            );
            $this->auditoria->deActor(
                $actor,
                'inactivar',
                'matriz_aplicabilidad',
                $id,
                $this->auditoria->payloadNuevo($cambios, AuditoriaService::ORIGEN_USUARIO),
                $this->vistaAuditoria($actual)
            );
        }

        return 'El registro fue inactivado correctamente.';
    }

    public function mensajeMasivo(array $resultado): string
    {
        $creadas = (int)$resultado['creadas'];
        $omitidas = (int)$resultado['omitidas'];

        if ($creadas > 0 && $omitidas > 0) {
            return "{$creadas} asociaciones creadas, {$omitidas} omitida(s) porque ya existía(n).";
        }

        if ($creadas > 0) {
            return $creadas === 1 ? '1 asociación creada.' : "{$creadas} asociaciones creadas.";
        }

        if ($omitidas > 0) {
            return self::MENSAJE_DUPLICADO;
        }

        return 'No se crearon asociaciones.';
    }

    /**
     * @return list<int>
     */
    private function normalizarCargosMasivos(mixed $bruto): array
    {
        if (!is_array($bruto) || $bruto === []) {
            throw new HttpException('Debe seleccionar al menos un cargo.', 422);
        }

        $ids = [];
        foreach ($bruto as $valor) {
            $id = (int)$valor;
            if ($id <= 0) {
                throw new HttpException('El cargo no existe en el maestro de personal corporativo', 422);
            }
            $ids[$id] = $id;
        }

        $ids = array_values($ids);

        foreach ($ids as $id) {
            if (!$this->personal->cargoExiste($id)) {
                throw new HttpException('El cargo no existe en el maestro de personal corporativo', 422);
            }
        }

        return $ids;
    }

    private function preparar(array $datos, bool $parcial = false): array
    {
        $enteros = ['capacitacion_id', 'cargo_id_ext', 'area_id', 'proceso_id', 'periodicidad_id', 'obligatoria', 'activa'];

        foreach ($enteros as $campo) {
            if (!array_key_exists($campo, $datos)) {
                continue;
            }
            if ($datos[$campo] === null || $datos[$campo] === '') {
                $datos[$campo] = null;
            } else {
                $datos[$campo] = (int)$datos[$campo];
            }
        }

        if (array_key_exists('proyecto', $datos)) {
            $datos['proyecto'] = nullable_trimmed_string($datos['proyecto']);
        }

        if (array_key_exists('ambito', $datos) && $datos['ambito'] === '') {
            $datos['ambito'] = null;
        }

        if (!$parcial && !array_key_exists('obligatoria', $datos)) {
            $datos['obligatoria'] = 1;
        }

        if (!$parcial && !array_key_exists('activa', $datos)) {
            $datos['activa'] = 1;
        }

        unset($datos['cargo_ids_ext']);

        return $datos;
    }

    /**
     * @param array<string,mixed> $datos
     */
    private function aplicarContextoObligatorio(array &$datos, bool $parcial): void
    {
        if (!$parcial) {
            if (empty($datos['proceso_id'])) {
                throw new HttpException('El proceso es obligatorio.', 422);
            }
            if (array_key_exists('cargo_id_ext', $datos) && empty($datos['cargo_id_ext'])) {
                throw new HttpException('El cargo es obligatorio.', 422);
            }
        }

        $procesoId = isset($datos['proceso_id']) ? (int)$datos['proceso_id'] : 0;
        if ($procesoId < 1) {
            return;
        }

        $datos['proyecto'] = $this->proyectoSegunProceso($procesoId, $datos['proyecto'] ?? null, !$parcial);
        if (!array_key_exists('ambito', $datos) || $datos['ambito'] === null || $datos['ambito'] === '') {
            $datos['ambito'] = $this->ambitoDeProceso($procesoId);
        }
    }

    /**
     * Unión: cargos del Excel para el proceso + cargos con marca activa en esta hoja.
     * No se arma la lista con la nómina ni con filas inactivas.
     *
     * @param list<array<string,mixed>> $filasContexto
     * @param list<array{cargo_id:int,nombre_cargo:string}> $catalogo
     * @return list<array{cargo_id:int,nombre_cargo:string}>
     */
    private function cargosDeVista(array $filasContexto, array $catalogo, string $nombreProceso): array
    {
        $porId = [];
        foreach ($catalogo as $cargo) {
            $porId[(int)$cargo['cargo_id']] = $cargo;
        }

        $salida = [];
        $ids = [];
        foreach ($this->cargosDelProcesoExcel($nombreProceso) as $cargo) {
            $id = (int)$cargo['cargo_id'];
            $ids[$id] = true;
            $salida[] = $cargo;
        }

        $extras = [];
        foreach ($filasContexto as $fila) {
            $id = (int)($fila['cargo_id_ext'] ?? 0);
            if ($id > 0 && (int)($fila['activa'] ?? 0) === 1 && !isset($ids[$id])) {
                $extras[$id] = true;
                $ids[$id] = true;
            }
        }

        $faltantes = [];
        foreach (array_keys($extras) as $id) {
            if (!isset($porId[$id])) {
                $faltantes[] = $id;
            }
        }
        if ($faltantes !== []) {
            foreach ($this->personal->nombresCargosPorIds($faltantes) as $id => $nombre) {
                $porId[$id] = [
                    'cargo_id' => $id,
                    'nombre_cargo' => $nombre,
                ];
            }
        }

        foreach (array_keys($extras) as $id) {
            if (isset($porId[$id])) {
                $salida[] = $porId[$id];
            }
        }

        return $salida;
    }

    /**
     * Filas de la hoja MATRIZ POR CARGO, con el nombre del Excel.
     *
     * @return list<array{cargo_id:int,nombre_cargo:string}>
     */
    private function cargosDelProcesoExcel(string $nombreProceso): array
    {
        $repo = $this->personal->repositorio();
        $mapa = $repo->mapaCargos();
        $usados = [];
        $salida = [];

        foreach ($this->filasCargosExcel($nombreProceso) as $fila) {
            $id = $this->resolverCargoPorAlias($fila['alias'], $repo, $mapa);
            if ($id === null || isset($usados[$id])) {
                continue;
            }
            $usados[$id] = true;
            $salida[] = [
                'cargo_id' => $id,
                'nombre_cargo' => $fila['nombre'],
            ];
        }

        return $salida;
    }

    /**
     * @return list<array{nombre:string, alias:list<string>}>
     */
    private function filasCargosExcel(string $nombreProceso): array
    {
        $porProceso = config('matriz_cargos.por_proceso', []);
        $items = $porProceso[$this->claveProcesoExcel($nombreProceso)] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $filas = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $nombre = trim($item);
                if ($nombre === '') {
                    continue;
                }
                $filas[] = ['nombre' => $nombre, 'alias' => [$nombre]];
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            $nombre = trim((string)($item['nombre'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            $alias = $item['alias'] ?? [$nombre];
            if (!is_array($alias) || $alias === []) {
                $alias = [$nombre];
            }
            $filas[] = [
                'nombre' => $nombre,
                'alias' => array_values(array_filter(array_map('strval', $alias))),
            ];
        }

        return $filas;
    }

    /**
     * @param list<string> $alias
     * @param array{por_nombre: array<string,int>, por_id: array<int,string>} $mapa
     */
    private function resolverCargoPorAlias(array $alias, PersonalRepository $repo, array $mapa): ?int
    {
        foreach ($alias as $nombre) {
            $clave = $repo->claveCargo((string)$nombre);
            if ($clave !== '' && isset($mapa['por_nombre'][$clave])) {
                return (int)$mapa['por_nombre'][$clave];
            }
        }

        return null;
    }

    private function claveProcesoExcel(string $nombre): string
    {
        $nombre = mb_strtoupper(trim($nombre), 'UTF-8');
        $nombre = strtr($nombre, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ä' => 'A', 'Ë' => 'E', 'Ï' => 'I', 'Ö' => 'O', 'Ü' => 'U',
            'Ñ' => 'N',
        ]);

        return preg_replace('/\s+/', ' ', $nombre) ?? $nombre;
    }

    /**
     * @return array{proceso_id:int,nombre:string}
     */
    private function procesoDeMatriz(int $procesoId): array
    {
        foreach ($this->alertas->procesosActivos() as $proceso) {
            if ((int)$proceso['proceso_id'] === $procesoId) {
                return $proceso;
            }
        }

        throw new HttpException('El proceso seleccionado no es válido para la matriz.', 422);
    }

    private function proyectoSegunProceso(int $procesoId, mixed $proyecto, bool $exigirSiGestion, bool $soloCatalogo = false): ?string
    {
        if (!$this->alertas->procesoEsGestionProyectos($procesoId)) {
            return null;
        }

        $normalizado = nullable_trimmed_string($proyecto);
        if ($normalizado === null || $normalizado === '') {
            if ($exigirSiGestion) {
                throw new HttpException('El proyecto es obligatorio para Gestión de Proyectos.', 422);
            }

            return null;
        }

        if (!$soloCatalogo) {
            return $normalizado;
        }

        $canonico = $this->alertas->resolverProyecto($normalizado, true);
        if ($canonico === null) {
            throw new HttpException('El proyecto no es válido.', 422);
        }

        return $canonico;
    }

    private function ambitoDeProceso(int $procesoId): string
    {
        return $this->alertas->procesoEsGestionProyectos($procesoId) ? 'PROYECTO' : 'ADMINISTRACION';
    }

    /**
     * @return array<string,array{cargo_id_ext:int,capacitacion_id:int}>
     */
    private function normalizarAplica(mixed $bruto): array
    {
        if (!is_array($bruto)) {
            throw new HttpException('Debe enviar las celdas que aplican.', 422);
        }

        $pares = [];
        foreach ($bruto as $item) {
            if (!is_array($item)) {
                throw new HttpException('Cada celda debe indicar cargo y capacitación.', 422);
            }

            $cargoId = (int)($item['cargo_id_ext'] ?? 0);
            $capId = (int)($item['capacitacion_id'] ?? 0);
            if ($cargoId < 1 || $capId < 1) {
                throw new HttpException('Cada celda debe indicar cargo y capacitación.', 422);
            }

            if (!$this->personal->cargoExiste($cargoId)) {
                throw new HttpException('El cargo no existe en el maestro de personal corporativo', 422);
            }

            $cap = $this->capacitaciones->buscarPorId($capId);
            if ($cap === null) {
                throw new HttpException('La capacitación no existe', 422);
            }
            if (strtoupper((string)($cap['estado'] ?? '')) !== 'ACTIVA') {
                throw new HttpException('Solo se pueden asociar capacitaciones activas.', 422);
            }

            $pares[$cargoId . ':' . $capId] = [
                'cargo_id_ext' => $cargoId,
                'capacitacion_id' => $capId,
            ];
        }

        return $pares;
    }

    private function validarReferencias(array $datos, bool $altaNueva): void
    {
        if (!empty($datos['capacitacion_id'])) {
            $cap = $this->capacitaciones->buscarPorId((int)$datos['capacitacion_id']);
            if ($cap === null) {
                throw new HttpException('La capacitación no existe', 422);
            }
            if ($altaNueva && strtoupper((string)($cap['estado'] ?? '')) !== 'ACTIVA') {
                throw new HttpException('Solo se pueden asociar capacitaciones activas.', 422);
            }
        }

        if (!empty($datos['cargo_id_ext']) && !$this->personal->cargoExiste((int)$datos['cargo_id_ext'])) {
            throw new HttpException('El cargo no existe en el maestro de personal corporativo', 422);
        }

        if (!empty($datos['area_id']) && !$this->capacitaciones->catalogoExiste('areas', 'area_id', (int)$datos['area_id'])) {
            throw new HttpException('El área seleccionada no existe', 422);
        }

        if (!empty($datos['proceso_id'])) {
            if (!$this->capacitaciones->catalogoExiste('procesos', 'proceso_id', (int)$datos['proceso_id'])) {
                throw new HttpException('El proceso seleccionado no existe', 422);
            }
            if ($altaNueva && !$this->capacitaciones->catalogoActivo('procesos', 'proceso_id', (int)$datos['proceso_id'])) {
                throw new HttpException('El proceso seleccionado está inactivo.', 422);
            }
        }

        if (!empty($datos['periodicidad_id'])) {
            if (!$this->capacitaciones->catalogoExiste('periodicidades', 'periodicidad_id', (int)$datos['periodicidad_id'])) {
                throw new HttpException('La periodicidad seleccionada no existe', 422);
            }
            if ($altaNueva && !$this->capacitaciones->catalogoActivo('periodicidades', 'periodicidad_id', (int)$datos['periodicidad_id'])) {
                throw new HttpException('La periodicidad seleccionada está inactiva.', 422);
            }
        }
    }

    /**
     * @param array<string,mixed> $fila
     * @return array{cargo_id_ext:?int,capacitacion_id:?int,cargo_nombre:?string,capacitacion_nombre:?string}
     */
    private function celdaMatriz(array $fila): array
    {
        return [
            'cargo_id_ext' => isset($fila['cargo_id_ext']) ? (int)$fila['cargo_id_ext'] : null,
            'capacitacion_id' => isset($fila['capacitacion_id']) ? (int)$fila['capacitacion_id'] : null,
            'cargo_nombre' => isset($fila['cargo_nombre']) ? (string)$fila['cargo_nombre'] : null,
            'capacitacion_nombre' => isset($fila['capacitacion_nombre']) ? (string)$fila['capacitacion_nombre'] : null,
        ];
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function vistaAuditoria(array $fila): array
    {
        $activa = !empty($fila['activa']);

        return [
            'capacitacion_nombre' => $fila['capacitacion_nombre'] ?? $fila['capacitacion_id'] ?? null,
            'cargo_nombre' => $fila['cargo_nombre'] ?? $fila['cargo_id_ext'] ?? null,
            'proceso_nombre' => $fila['proceso_nombre'] ?? $fila['proceso_id'] ?? null,
            'proyecto' => $fila['proyecto'] ?? null,
            'aplica' => $activa ? 'Aplica' : 'No aplica',
        ];
    }

    private function normalizar(array $fila, array $nombresCargo): array
    {
        $cargoId = $fila['cargo_id_ext'] !== null ? (int)$fila['cargo_id_ext'] : null;

        return [
            'matriz_aplicabilidad_id' => (int)$fila['matriz_aplicabilidad_id'],
            'capacitacion_id' => (int)$fila['capacitacion_id'],
            'capacitacion_codigo' => $fila['capacitacion_codigo'] ?? null,
            'capacitacion_nombre' => $fila['capacitacion_nombre'] ?? null,
            'cargo_id_ext' => $cargoId,
            'cargo_nombre' => $cargoId !== null ? ($nombresCargo[$cargoId] ?? null) : null,
            'area_id' => $fila['area_id'] !== null ? (int)$fila['area_id'] : null,
            'area_nombre' => $fila['area_nombre'] ?? null,
            'proceso_id' => $fila['proceso_id'] !== null ? (int)$fila['proceso_id'] : null,
            'proceso_nombre' => $fila['proceso_nombre'] ?? null,
            'ambito' => $fila['ambito'],
            'proyecto' => $fila['proyecto'],
            'periodicidad_id' => $fila['periodicidad_id'] !== null ? (int)$fila['periodicidad_id'] : null,
            'periodicidad_nombre' => humanizar_nombre_unidad($fila['periodicidad_nombre'] ?? null),
            'obligatoria' => (int)$fila['obligatoria'] === 1,
            'activa' => (int)$fila['activa'] === 1,
            'created_at' => $fila['created_at'] ?? null,
            'updated_at' => $fila['updated_at'] ?? null,
        ];
    }
}
