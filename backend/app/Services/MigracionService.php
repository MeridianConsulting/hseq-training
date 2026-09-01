<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AsignacionRepository;
use App\Repositories\CapacitacionRepository;
use App\Repositories\CumplimientoRepository;
use App\Repositories\MatrizRepository;
use App\Repositories\MigracionRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class MigracionService
{
    public const MSG_ARCHIVO = 'No fue posible procesar el archivo. Verifique que corresponde a la matriz HSEQ requerida.';
    public const ACCION_AUDITORIA = 'migracion_inicial';

    /** @var bool Solo pruebas: el siguiente confirmar() lanza tras insertar. */
    public static bool $fallarImportacion = false;

    private MigracionRepository $repo;
    private MigracionPrg10Parser $parser;
    private PersonalService $personal;
    private CapacitacionRepository $capacitaciones;
    private MatrizRepository $matriz;
    private AsignacionRepository $asignaciones;
    private CumplimientoRepository $cumplimientos;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->repo = new MigracionRepository();
        $this->parser = new MigracionPrg10Parser();
        $this->personal = new PersonalService();
        $this->capacitaciones = new CapacitacionRepository();
        $this->matriz = new MatrizRepository();
        $this->asignaciones = new AsignacionRepository();
        $this->cumplimientos = new CumplimientoRepository();
        $this->auditoria = new AuditoriaService();
    }

    /**
     * @param array<string,mixed> $archivo
     * @param array{usuario_id:?int,nombre:?string,ip:?string} $actor
     */
    public function validar(array $archivo, int $anioPrograma, array $actor): array
    {
        $anioPrograma = $this->exigirAnio($anioPrograma);
        $validado = $this->validarArchivo($archivo);
        $tmp = $this->copiarTemporal($validado);

        try {
            $leido = $this->parser->leer($tmp);
        } catch (Throwable $e) {
            @unlink($tmp);
            throw new HttpException(self::MSG_ARCHIVO, 422);
        }

        $dry = $this->dryRun($leido, $anioPrograma);
        $id = $this->repo->crear([
            'usuario_id_ext' => $actor['usuario_id'] ?? null,
            'usuario_nombre' => $actor['nombre'] ?? null,
            'nombre_archivo' => $validado['nombre'],
            'ruta_archivo' => '',
            'mime_type' => $validado['mime'],
            'tamano_bytes' => $validado['tamano'],
            'anio_programa' => $anioPrograma,
            'estado' => 'VALIDADA',
            'resumen_json' => $this->aJson($dry['resumen']),
            'inconsistencias_json' => $this->aJson($dry['inconsistencias']),
            'conteos_json' => $this->aJson($dry['conteos']),
        ]);

        $relativo = 'migraciones/' . $id . '/origen.' . $validado['extension'];
        $destino = $this->directorioBase() . '/' . $relativo;
        $dir = dirname($destino);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new HttpException(self::MSG_ARCHIVO, 500);
        }
        if (!@rename($tmp, $destino) && !@copy($tmp, $destino)) {
            throw new HttpException(self::MSG_ARCHIVO, 500);
        }
        @unlink($tmp);
        $this->repo->actualizar($id, ['ruta_archivo' => $relativo]);

        return $this->presentar($this->exigir($id));
    }

    public function ver(int $id): array
    {
        return $this->presentar($this->exigir($id));
    }

    public function inconsistencias(int $id, int $pagina, int $porPagina): array
    {
        $fila = $this->exigir($id);
        $todas = $this->desdeJson($fila['inconsistencias_json'] ?? '[]');
        $todas = is_array($todas) ? $todas : [];
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;

        return [
            'items' => array_slice($todas, $offset, $porPagina),
            'total' => count($todas),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    public function reporteExcel(int $id): array
    {
        $fila = $this->exigir($id);
        $items = $this->desdeJson($fila['inconsistencias_json'] ?? '[]');
        $items = is_array($items) ? $items : [];
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Inconsistencias');
        $encabezados = ['Hoja', 'Fila', 'Tipo', 'Identificador', 'Campo', 'Valor', 'Motivo', 'Severidad'];
        foreach ($encabezados as $i => $titulo) {
            $hoja->setCellValueByColumnAndRow($i + 1, 1, $titulo);
        }
        $filaN = 2;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $hoja->setCellValueByColumnAndRow(1, $filaN, (string)($item['hoja'] ?? ''));
            $hoja->setCellValueByColumnAndRow(2, $filaN, $item['fila'] ?? '');
            $hoja->setCellValueByColumnAndRow(3, $filaN, (string)($item['tipo'] ?? ''));
            $hoja->setCellValueByColumnAndRow(4, $filaN, (string)($item['identificador'] ?? ''));
            $hoja->setCellValueByColumnAndRow(5, $filaN, (string)($item['campo'] ?? ''));
            $hoja->setCellValueByColumnAndRow(6, $filaN, (string)($item['valor'] ?? ''));
            $hoja->setCellValueByColumnAndRow(7, $filaN, (string)($item['motivo'] ?? ''));
            $hoja->setCellValueByColumnAndRow(8, $filaN, (string)($item['severidad'] ?? ''));
            $filaN++;
        }
        $escritor = new Xlsx($libro);
        ob_start();
        $escritor->save('php://output');
        $contenido = (string)ob_get_clean();

        return [
            'contenido' => $contenido,
            'nombre' => 'Reporte_inconsistencias_migracion.xlsx',
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * @param array{usuario_id:?int,nombre:?string,ip:?string} $actor
     */
    public function confirmar(int $id, array $actor): array
    {
        $fila = $this->exigir($id);
        if (($fila['estado'] ?? '') !== 'VALIDADA') {
            throw new HttpException('Esta migración ya no puede confirmarse.', 409);
        }
        $resumen = $this->desdeJson($fila['resumen_json'] ?? '{}');
        $resumen = is_array($resumen) ? $resumen : [];
        $plan = is_array($resumen['plan'] ?? null) ? $resumen['plan'] : [];

        try {
            $conteos = $this->repo->transaccion(function () use ($id, $plan, $actor, $fila, $resumen) {
                $conteos = $this->consolidarConteos($resumen, $this->ejecutarPlan($plan));
                if (self::$fallarImportacion) {
                    self::$fallarImportacion = false;
                    throw new RuntimeException('No fue posible completar la importación.');
                }
                $this->repo->actualizar($id, [
                    'estado' => 'CONFIRMADA',
                    'conteos_json' => $this->aJson($conteos),
                    'confirmada_at' => date('Y-m-d H:i:s'),
                ]);
                $this->auditoria->deActor(
                    $actor,
                    self::ACCION_AUDITORIA,
                    'migraciones',
                    $id,
                    [
                        'origen' => AuditoriaService::ORIGEN_USUARIO,
                        'archivo' => $fila['nombre_archivo'] ?? null,
                        'anio_programa' => (int)($fila['anio_programa'] ?? 0),
                        'conteos' => $conteos,
                        'estado' => 'CONFIRMADA',
                    ]
                );

                return $conteos;
            });
        } catch (Throwable $e) {
            $this->repo->actualizar($id, ['estado' => 'FALLIDA']);
            throw $e;
        }

        $actualizada = $this->exigir($id);
        $vista = $this->presentar($actualizada);
        $vista['conteos'] = $conteos;

        return $vista;
    }

    public function cancelar(int $id): array
    {
        $fila = $this->exigir($id);
        if (($fila['estado'] ?? '') !== 'VALIDADA') {
            throw new HttpException('Esta migración ya no puede cancelarse.', 409);
        }
        $this->repo->actualizar($id, ['estado' => 'CANCELADA']);

        return $this->presentar($this->exigir($id));
    }

    public function archivoOrigen(int $id): array
    {
        $fila = $this->exigir($id);
        $relativo = str_replace('\\', '/', (string)($fila['ruta_archivo'] ?? ''));
        if ($relativo === '' || str_contains($relativo, '..')) {
            throw new HttpException('El archivo original no está disponible.', 404);
        }
        $ruta = $this->directorioBase() . '/' . ltrim($relativo, '/');
        if (!is_file($ruta)) {
            throw new HttpException('El archivo original no está disponible.', 404);
        }
        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new HttpException('El archivo original no está disponible.', 404);
        }

        return [
            'contenido' => $contenido,
            'nombre' => (string)($fila['nombre_archivo'] ?? 'origen.xlsx'),
            'mime' => (string)($fila['mime_type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }

    /**
     * @param array<string,mixed> $leido
     * @return array{resumen:array<string,mixed>,inconsistencias:list<array<string,mixed>>,conteos:array<string,mixed>}
     */
    private function dryRun(array $leido, int $anio): array
    {
        $inconsistencias = [];
        $agregar = function (
            string $hoja,
            int $fila,
            string $tipo,
            string $id,
            string $campo,
            mixed $valor,
            string $motivo,
            string $severidad = 'Error'
        ) use (&$inconsistencias): void {
            $inconsistencias[] = [
                'hoja' => $hoja,
                'fila' => $fila,
                'tipo' => $tipo,
                'identificador' => $id,
                'campo' => $campo,
                'valor' => is_scalar($valor) || $valor === null ? (string)$valor : json_encode($valor),
                'motivo' => $motivo,
                'severidad' => $severidad,
            ];
        };

        $mapaCargos = $this->personal->repositorio()->mapaCargos();
        $mapaProcesos = $this->mapaProcesos();
        $mapaModalidades = $this->mapaModalidades();
        $docsEnArchivo = [];
        $planCaps = [];
        $planPersonas = [];
        $planMatriz = [];
        $planE = [];
        $planP = [];
        $docsValidos = [];

        if (empty($leido['estructura_ok'])) {
            $faltantes = is_array($leido['faltantes'] ?? null) ? $leido['faltantes'] : [];
            foreach ($faltantes as $hoja) {
                $agregar((string)$hoja, 0, 'archivo', '', 'hoja', $hoja, 'Hoja faltante: ' . $hoja);
            }
            if ($faltantes === [] && !empty($leido['mensaje'])) {
                $agregar('', 0, 'archivo', '', 'estructura', '', (string)$leido['mensaje']);
            }
        }

        $caps = is_array($leido['capacitaciones'] ?? null) ? $leido['capacitaciones'] : [];
        $capsOk = 0;
        $capsExistentes = 0;
        foreach ($caps as $cap) {
            $codigo = (string)($cap['codigo'] ?? '');
            $nombre = trim((string)($cap['nombre'] ?? ''));
            $fila = (int)($cap['fila'] ?? 0);
            if ($nombre === '') {
                $agregar('CRONOGRAMA', $fila, 'capacitacion', $codigo, 'nombre', $nombre, 'Campo obligatorio vacío.');
                continue;
            }
            $horas = $this->parsearHoras($cap['horas'] ?? null);
            if ($horas === null) {
                $agregar('CRONOGRAMA', $fila, 'capacitacion', $codigo, 'horas', $cap['horas'] ?? '', 'Las horas no son un valor numérico válido.');
                continue;
            }
            $existente = $this->capacitaciones->buscarPorCodigo($codigo);
            if ($existente !== null) {
                $capsExistentes++;
                $capsOk++;
                $planCaps[] = [
                    'codigo' => $codigo,
                    'accion' => 'existente',
                    'capacitacion_id' => (int)$existente['capacitacion_id'],
                    'horas' => $existente['duracion_estimada_horas'] !== null
                        ? (float)$existente['duracion_estimada_horas']
                        : $horas,
                ];
                continue;
            }
            $capsOk++;
            $planCaps[] = [
                'codigo' => $codigo,
                'accion' => 'nuevo',
                'nombre' => $nombre,
                'objetivo' => (string)($cap['objetivo'] ?? $nombre),
                'horas' => $horas,
                'modalidad_id' => $this->resolverModalidad((string)($cap['metodologia'] ?? ''), $mapaModalidades),
            ];
        }

        $estructuraOk = (bool)($leido['estructura_ok'] ?? false);
        $trabajadores = is_array($leido['trabajadores'] ?? null) ? $leido['trabajadores'] : [];
        $personasOk = 0;
        $personasExistentes = 0;
        $docsExistentesBd = $this->personal->repositorio()->documentosExistentes(
            array_values(array_filter(array_map(
                fn ($t) => $this->personal->normalizarDocumento($t['documento'] ?? ''),
                $trabajadores
            )))
        );

        foreach ($trabajadores as $t) {
            $fila = (int)($t['fila'] ?? 0);
            $doc = $this->personal->normalizarDocumento($t['documento'] ?? '');
            $tieneIngreso = !empty($t['tiene_columna_ingreso']);
            if (!$tieneIngreso) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'trabajador',
                    $doc !== '' ? $doc : (string)($t['nombre'] ?? ''),
                    'fecha_ingreso',
                    '',
                    'Campo obligatorio vacío.'
                );
                continue;
            }
            if ($doc === '') {
                $agregar('SEGUIMIENTO_PERSONAL', $fila, 'trabajador', (string)($t['nombre'] ?? ''), 'documento', '', 'Campo obligatorio vacío.');
                continue;
            }
            if (isset($docsEnArchivo[$doc])) {
                $agregar('SEGUIMIENTO_PERSONAL', $fila, 'trabajador', $doc, 'documento', $doc, 'Documento duplicado.');
                continue;
            }
            $docsEnArchivo[$doc] = $fila;
            $entrada = [
                'documento' => $doc,
                'nombre' => $t['nombre'] ?? '',
                'correo' => $t['correo'] ?? '',
                'cargo' => $t['cargo'] ?? '',
                'fecha_ingreso' => $t['fecha_ingreso'] ?? '',
                'proyecto' => $t['area'] ?? '',
            ];
            $prep = $this->personal->prepararEntrada($entrada, null, $docsExistentesBd, false, $mapaCargos);
            if (!$prep['ok']) {
                $motivo = (string)$prep['motivo'];
                if ($motivo === 'El documento ya se encuentra registrado.') {
                    $personasExistentes++;
                    $personasOk++;
                    $docsValidos[$doc] = true;
                    $planPersonas[] = ['documento' => $doc, 'accion' => 'existente'];
                    continue;
                }
                $campo = str_contains($motivo, 'cargo') ? 'cargo'
                    : (str_contains($motivo, 'fecha') ? 'fecha_ingreso'
                    : (str_contains($motivo, 'nombre') ? 'nombre'
                    : (str_contains($motivo, 'correo') ? 'correo' : 'documento')));
                $agregar('SEGUIMIENTO_PERSONAL', $fila, 'trabajador', $doc, $campo, (string)($t[$campo] ?? $doc), $motivo);
                continue;
            }
            $personasOk++;
            $docsValidos[$doc] = true;
            $estado = $this->mapearEstado((string)($t['estado'] ?? ''));
            $datos = $prep['datos'];
            $datos['estado'] = $estado;
            $planPersonas[] = ['documento' => $doc, 'accion' => 'nuevo', 'datos' => $datos];
        }

        $capsPorCodigo = [];
        foreach ($planCaps as $c) {
            $capsPorCodigo[$c['codigo']] = true;
        }

        $matrizFilas = is_array($leido['matriz'] ?? null) ? $leido['matriz'] : [];
        $matrizOk = 0;
        foreach ($matrizFilas as $m) {
            $fila = (int)($m['fila'] ?? 0);
            $codigo = (string)($m['codigo'] ?? '');
            $cargoNom = (string)($m['cargo'] ?? '');
            $cargoId = $mapaCargos['por_nombre'][$this->personal->repositorio()->claveCargo($cargoNom)] ?? null;
            if ($cargoId === null) {
                $agregar('MATRIZ POR CARGO', $fila, 'matriz', $cargoNom, 'cargo', $cargoNom, 'El cargo no existe en el catálogo.');
                continue;
            }
            if (!isset($capsPorCodigo[$codigo])) {
                $agregar('MATRIZ POR CARGO', $fila, 'matriz', $codigo, 'capacitacion', $codigo, 'La capacitación no existe.');
                continue;
            }
            $procesoNom = trim((string)($m['proceso'] ?? ''));
            $procesoId = null;
            if ($procesoNom !== '') {
                $procesoId = $mapaProcesos[$this->clave($procesoNom)] ?? null;
                if ($procesoId === null) {
                    $agregar('MATRIZ POR CARGO', $fila, 'matriz', $cargoNom, 'proceso', $procesoNom, 'El proceso no existe en el catálogo.');
                    continue;
                }
            }
            $ambito = $this->mapearAmbito((string)($m['proyecto'] ?? ''));
            $proyecto = trim((string)($m['proyecto'] ?? ''));
            $matrizOk++;
            $planMatriz[] = [
                'codigo' => $codigo,
                'cargo_id' => (int)$cargoId,
                'proceso_id' => $procesoId,
                'ambito' => $ambito,
                'proyecto' => $proyecto !== '' ? $proyecto : null,
            ];
        }

        $segs = is_array($leido['seguimientos'] ?? null) ? $leido['seguimientos'] : [];
        $eDetectados = 0;
        $eOk = 0;
        $pOk = 0;
        foreach ($segs as $s) {
            $estado = strtoupper(trim((string)($s['estado'] ?? '')));
            $fila = (int)($s['fila'] ?? 0);
            $doc = $this->personal->normalizarDocumento($s['documento'] ?? '');
            $codigo = (string)($s['codigo'] ?? '');
            if ($estado === 'N/A' || $estado === 'NA') {
                continue;
            }
            if ($estado !== 'E' && $estado !== 'P') {
                continue;
            }
            if ($estado === 'E') {
                $eDetectados++;
            }
            if ($doc === '' || !isset($docsValidos[$doc])) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    $estado === 'E' ? 'cumplimiento' : 'asignacion',
                    $doc !== '' ? $doc : $codigo,
                    'documento',
                    $doc,
                    'Trabajador no encontrado.'
                );
                continue;
            }
            if (!isset($capsPorCodigo[$codigo])) {
                $agregar('SEGUIMIENTO_PERSONAL', $fila, 'cumplimiento', $doc, 'capacitacion', $codigo, 'La capacitación no existe.');
                continue;
            }
            $fecha = $this->fechaDesdeMes($s['mes'] ?? null, $anio);
            if ($estado === 'E' && $fecha === null) {
                $agregar('SEGUIMIENTO_PERSONAL', $fila, 'cumplimiento', $doc, 'mes', (string)($s['mes'] ?? ''), 'Fecha de realización inválida.');
                continue;
            }
            if ($estado === 'E') {
                $nota = $this->parsearNota($s['nota'] ?? null);
                $cert = strtoupper(trim((string)($s['certificado'] ?? '')));
                if ($cert === 'SI' || $cert === 'SÍ') {
                    $agregar(
                        'SEGUIMIENTO_PERSONAL',
                        $fila,
                        'cumplimiento',
                        $doc,
                        'certificado',
                        $cert,
                        'Certificado marcado sin archivo adjunto. No se migra evidencia.',
                        'Advertencia'
                    );
                }
                $eOk++;
                $planE[] = [
                    'documento' => $doc,
                    'codigo' => $codigo,
                    'fecha_realizacion' => $fecha,
                    'nota' => $nota,
                    'horas' => $this->horasDeCap($planCaps, $codigo),
                ];
            } else {
                $pOk++;
                $planP[] = [
                    'documento' => $doc,
                    'codigo' => $codigo,
                    'fecha_limite' => $fecha ?? sprintf('%d-12-31', $anio),
                ];
            }
        }

        $resumen = [
            'archivo' => null,
            'hojas_detectadas' => $leido['hojas'] ?? [],
            'hojas_faltantes' => $leido['faltantes'] ?? [],
            'estructura_valida' => $estructuraOk,
            'anio_programa' => $anio,
            'trabajadores' => $this->bloqueConteo(count($trabajadores), $personasOk, count($trabajadores) - $personasOk, $personasExistentes),
            'capacitaciones' => $this->bloqueConteo(count($caps), $capsOk, count($caps) - $capsOk, $capsExistentes),
            'matriz' => $this->bloqueConteo(count($matrizFilas), $matrizOk, count($matrizFilas) - $matrizOk, 0),
            'cumplimientos' => $this->bloqueConteo($eDetectados, $eOk, $eDetectados - $eOk, 0),
            'asignaciones_pendientes' => $this->bloqueConteo($pOk + $this->contarErrores($inconsistencias, 'asignacion'), $pOk, $this->contarErrores($inconsistencias, 'asignacion'), 0),
            'inconsistencias_total' => count($inconsistencias),
            'errores' => $this->contarSeveridad($inconsistencias, 'Error'),
            'advertencias' => $this->contarSeveridad($inconsistencias, 'Advertencia'),
            'plan' => [
                'capacitaciones' => $planCaps,
                'trabajadores' => $planPersonas,
                'matriz' => $planMatriz,
                'cumplimientos' => $planE,
                'pendientes' => $planP,
            ],
        ];

        return [
            'resumen' => $resumen,
            'inconsistencias' => $inconsistencias,
            'conteos' => [
                'excel' => [
                    'trabajadores' => count($trabajadores),
                    'capacitaciones' => count($caps),
                    'matriz' => count($matrizFilas),
                    'cumplimientos' => $eDetectados,
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private function ejecutarPlan(array $plan): array
    {
        $capIds = [];
        $capsNuevas = 0;
        $capsExistentes = 0;
        foreach ($plan['capacitaciones'] ?? [] as $item) {
            $codigo = (string)($item['codigo'] ?? '');
            if (($item['accion'] ?? '') === 'existente') {
                $fila = $this->capacitaciones->buscarPorCodigo($codigo);
                if ($fila !== null) {
                    $capIds[$codigo] = (int)$fila['capacitacion_id'];
                    $capsExistentes++;
                }
                continue;
            }
            $existente = $this->capacitaciones->buscarPorCodigo($codigo);
            if ($existente !== null) {
                $capIds[$codigo] = (int)$existente['capacitacion_id'];
                $capsExistentes++;
                continue;
            }
            $id = $this->capacitaciones->crear([
                'codigo' => $codigo,
                'nombre' => $item['nombre'],
                'objetivo' => $item['objetivo'],
                'duracion_estimada_horas' => $item['horas'],
                'criticidad' => 'MEDIA',
                'modalidad_default_id' => $item['modalidad_id'] ?? null,
                'estado' => 'ACTIVA',
            ]);
            $capIds[$codigo] = $id;
            $capsNuevas++;
        }

        $personaIds = [];
        $personasNuevas = 0;
        $personasExistentes = 0;
        foreach ($plan['trabajadores'] ?? [] as $item) {
            $doc = (string)($item['documento'] ?? '');
            if (($item['accion'] ?? '') === 'existente') {
                $pid = $this->personal->repositorio()->buscarIdPorDocumento($doc);
                if ($pid !== null) {
                    $personaIds[$doc] = $pid;
                    $personasExistentes++;
                }
                continue;
            }
            $pid = $this->personal->repositorio()->buscarIdPorDocumento($doc);
            if ($pid !== null) {
                $personaIds[$doc] = $pid;
                $personasExistentes++;
                continue;
            }
            $datos = is_array($item['datos'] ?? null) ? $item['datos'] : [];
            $personaIds[$doc] = $this->personal->persistirAlta($datos);
            $personasNuevas++;
        }

        $matrizNuevas = 0;
        $matrizExistentes = 0;
        foreach ($plan['matriz'] ?? [] as $item) {
            $capId = $capIds[(string)$item['codigo']] ?? null;
            if ($capId === null) {
                continue;
            }
            $datos = [
                'capacitacion_id' => $capId,
                'cargo_id_ext' => $item['cargo_id'],
                'area_id' => null,
                'proceso_id' => $item['proceso_id'],
                'ambito' => $item['ambito'],
                'proyecto' => $item['proyecto'],
                'obligatoria' => 1,
                'activa' => 1,
            ];
            if ($this->matriz->duplicado($datos)) {
                $matrizExistentes++;
                continue;
            }
            $this->matriz->crear($datos);
            $matrizNuevas++;
        }

        $cumpNuevos = 0;
        $cumpExistentes = 0;
        foreach ($plan['cumplimientos'] ?? [] as $item) {
            $pid = $personaIds[(string)$item['documento']] ?? $this->personal->repositorio()->buscarIdPorDocumento((string)$item['documento']);
            $capId = $capIds[(string)$item['codigo']] ?? null;
            if ($pid === null || $capId === null) {
                continue;
            }
            $asig = $this->asignaciones->buscarPorPersonaYCapacitacion($pid, $capId);
            if ($asig === null) {
                $persona = $this->personal->ver($pid);
                $asigId = $this->asignaciones->crear([
                    'persona_id_ext' => $pid,
                    'contrato_id_ext' => $persona['contrato_id'] ?? null,
                    'capacitacion_id' => $capId,
                    'fecha_asignacion' => $item['fecha_realizacion'],
                    'fecha_limite_cumplimiento' => $item['fecha_realizacion'],
                    'origen' => 'MANUAL',
                    'cargo_id_ext' => $persona['cargo_id'] ?? null,
                    'proyecto' => $persona['proyecto'] ?? null,
                ]);
            } else {
                $asigId = (int)$asig['asignacion_id'];
            }
            if ($this->cumplimientos->buscarPorAsignacion($asigId) !== null) {
                $cumpExistentes++;
                continue;
            }
            $this->cumplimientos->crear([
                'asignacion_id' => $asigId,
                'sesion_id' => null,
                'fecha_realizacion' => $item['fecha_realizacion'],
                'resultado' => 'APROBADO',
                'horas_efectivas' => $item['horas'] ?? 1,
                'nota_evaluacion' => $item['nota'],
                'fecha_vencimiento' => null,
            ]);
            $cumpNuevos++;
        }

        $pendNuevas = 0;
        $pendExistentes = 0;
        foreach ($plan['pendientes'] ?? [] as $item) {
            $pid = $personaIds[(string)$item['documento']] ?? $this->personal->repositorio()->buscarIdPorDocumento((string)$item['documento']);
            $capId = $capIds[(string)$item['codigo']] ?? null;
            if ($pid === null || $capId === null) {
                continue;
            }
            if ($this->asignaciones->buscarPorPersonaYCapacitacion($pid, $capId) !== null) {
                $pendExistentes++;
                continue;
            }
            $persona = $this->personal->ver($pid);
            $this->asignaciones->crear([
                'persona_id_ext' => $pid,
                'contrato_id_ext' => $persona['contrato_id'] ?? null,
                'capacitacion_id' => $capId,
                'fecha_asignacion' => date('Y-m-d'),
                'fecha_limite_cumplimiento' => $item['fecha_limite'],
                'origen' => 'MANUAL',
                'cargo_id_ext' => $persona['cargo_id'] ?? null,
                'proyecto' => $persona['proyecto'] ?? null,
            ]);
            $pendNuevas++;
        }

        $docs = [];
        foreach ($plan['trabajadores'] ?? [] as $item) {
            $docs[] = (string)$item['documento'];
        }
        $codigos = [];
        foreach ($plan['capacitaciones'] ?? [] as $item) {
            $codigos[] = (string)$item['codigo'];
        }

        $matrizSistema = 0;
        foreach ($plan['matriz'] ?? [] as $item) {
            $capId = $capIds[(string)($item['codigo'] ?? '')] ?? null;
            if ($capId === null) {
                continue;
            }
            if ($this->matriz->duplicado([
                'capacitacion_id' => $capId,
                'cargo_id_ext' => $item['cargo_id'] ?? null,
                'area_id' => null,
                'proceso_id' => $item['proceso_id'] ?? null,
                'ambito' => $item['ambito'] ?? null,
                'proyecto' => $item['proyecto'] ?? null,
            ])) {
                $matrizSistema++;
            }
        }

        $cumpSistema = 0;
        foreach ($plan['cumplimientos'] ?? [] as $item) {
            $pid = $personaIds[(string)($item['documento'] ?? '')] ?? null;
            $capId = $capIds[(string)($item['codigo'] ?? '')] ?? null;
            if ($pid === null || $capId === null) {
                continue;
            }
            $asig = $this->asignaciones->buscarPorPersonaYCapacitacion($pid, $capId);
            if ($asig !== null && $this->cumplimientos->buscarPorAsignacion((int)$asig['asignacion_id']) !== null) {
                $cumpSistema++;
            }
        }

        return [
            'trabajadores' => [
                'procesados' => count($plan['trabajadores'] ?? []),
                'importados' => $personasNuevas,
                'existentes' => $personasExistentes,
                'sistema' => $this->contarDocsEnSistema($docs),
            ],
            'capacitaciones' => [
                'procesados' => count($plan['capacitaciones'] ?? []),
                'importados' => $capsNuevas,
                'existentes' => $capsExistentes,
                'sistema' => $this->contarCodigosEnSistema($codigos),
            ],
            'matriz' => [
                'procesados' => count($plan['matriz'] ?? []),
                'importados' => $matrizNuevas,
                'existentes' => $matrizExistentes,
                'sistema' => $matrizSistema,
            ],
            'cumplimientos' => [
                'procesados' => count($plan['cumplimientos'] ?? []),
                'importados' => $cumpNuevos,
                'existentes' => $cumpExistentes,
                'sistema' => $cumpSistema,
            ],
            'asignaciones_pendientes' => [
                'importados' => $pendNuevas,
                'existentes' => $pendExistentes,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $resumen
     * @param array<string,mixed> $ejecutado
     * @return array<string,mixed>
     */
    private function consolidarConteos(array $resumen, array $ejecutado): array
    {
        $tipos = ['trabajadores', 'capacitaciones', 'matriz', 'cumplimientos'];
        $out = $ejecutado;
        foreach ($tipos as $tipo) {
            $bloque = is_array($resumen[$tipo] ?? null) ? $resumen[$tipo] : [];
            $excel = (int)($bloque['detectados'] ?? 0);
            $validos = (int)($bloque['validos'] ?? 0);
            $fila = is_array($ejecutado[$tipo] ?? null) ? $ejecutado[$tipo] : [];
            $importados = (int)($fila['importados'] ?? 0);
            $existentes = (int)($fila['existentes'] ?? ($bloque['existentes'] ?? 0));
            $sistema = (int)($fila['sistema'] ?? ($importados + $existentes));
            $rechazados = max(0, $excel - $validos);
            $out[$tipo] = [
                'excel' => $excel,
                'importados' => $importados,
                'existentes' => $existentes,
                'rechazados' => $rechazados,
                'sistema' => $sistema,
                'diferencia' => $excel - $sistema,
            ];
        }

        return $out;
    }

    /** @param array<string,mixed> $fila */
    private function presentar(array $fila): array
    {
        $resumen = $this->desdeJson($fila['resumen_json'] ?? '{}');
        $resumen = is_array($resumen) ? $resumen : [];
        unset($resumen['plan']);
        $incons = $this->desdeJson($fila['inconsistencias_json'] ?? '[]');
        $conteos = $this->desdeJson($fila['conteos_json'] ?? '{}');

        return [
            'migracion_id' => (int)$fila['migracion_id'],
            'nombre_archivo' => $fila['nombre_archivo'],
            'anio_programa' => (int)$fila['anio_programa'],
            'estado' => $fila['estado'],
            'usuario_nombre' => $fila['usuario_nombre'],
            'created_at' => $fila['created_at'],
            'confirmada_at' => $fila['confirmada_at'],
            'resumen' => $resumen,
            'inconsistencias_total' => is_array($incons) ? count($incons) : 0,
            'conteos' => is_array($conteos) ? $conteos : null,
        ];
    }

    private function exigir(int $id): array
    {
        $fila = $this->repo->buscarPorId($id);
        if ($fila === null) {
            throw new HttpException('La migración no existe.', 404);
        }

        return $fila;
    }

    /** @param array<string,mixed> $archivo */
    private function validarArchivo(array $archivo): array
    {
        $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || ($archivo['tmp_name'] ?? '') === '') {
            throw new HttpException('Debe seleccionar un archivo.', 422);
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException(self::MSG_ARCHIVO, 422);
        }
        $tamano = (int)($archivo['size'] ?? 0);
        $max = $this->tamanoMaximo();
        if ($tamano <= 0 || $tamano > $max) {
            throw new HttpException(self::MSG_ARCHIVO, 422);
        }
        $nombre = basename(str_replace('\\', '/', (string)($archivo['name'] ?? 'matriz.xlsx')));
        $ext = strtolower((string)pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'], true)) {
            throw new HttpException(self::MSG_ARCHIVO, 422);
        }
        $tmp = (string)$archivo['tmp_name'];
        if (!is_file($tmp)) {
            throw new HttpException(self::MSG_ARCHIVO, 422);
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $mimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/octet-stream',
            'application/zip',
            'application/x-zip-compressed',
            'application/x-zip',
        ];
        if (!in_array($mime, $mimes, true)) {
            throw new HttpException(self::MSG_ARCHIVO, 422);
        }

        return [
            'tmp' => $tmp,
            'nombre' => $nombre,
            'extension' => $ext,
            'mime' => $mime,
            'tamano' => $tamano,
        ];
    }

    /** @param array{tmp:string,nombre:string,extension:string,mime:string,tamano:int} $validado */
    private function copiarTemporal(array $validado): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mig');
        $origen = $validado['tmp'];
        $ok = is_uploaded_file($origen) ? copy($origen, $tmp) : copy($origen, $tmp);
        if (!$ok) {
            throw new HttpException(self::MSG_ARCHIVO, 500);
        }

        return $tmp;
    }

    private function directorioBase(): string
    {
        return rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/storage/uploads';
    }

    private function tamanoMaximo(): int
    {
        $valor = Env::get('UPLOAD_MAX_SIZE', 10485760);

        return is_numeric($valor) ? max(1, (int)$valor) : 10485760;
    }

    private function exigirAnio(int $anio): int
    {
        if ($anio < 2000 || $anio > 2100) {
            throw new HttpException('El año del programa no es válido.', 422);
        }

        return $anio;
    }

    /** @return array<string,int> */
    private function mapaProcesos(): array
    {
        $mapa = [];
        foreach ($this->repo->listarProcesos() as $fila) {
            $mapa[$this->clave((string)$fila['nombre'])] = (int)$fila['proceso_id'];
        }

        return $mapa;
    }

    /** @return array<string,int> */
    private function mapaModalidades(): array
    {
        $mapa = [];
        foreach ($this->repo->listarModalidades() as $fila) {
            $mapa[$this->clave((string)$fila['nombre'])] = (int)$fila['modalidad_id'];
        }

        return $mapa;
    }

    /** @param array<string,int> $mapa */
    private function resolverModalidad(string $texto, array $mapa): ?int
    {
        $clave = $this->clave($texto);
        if (isset($mapa[$clave])) {
            return $mapa[$clave];
        }
        if (str_contains($clave, 'virtual')) {
            foreach ($mapa as $nombre => $id) {
                if (str_contains($nombre, 'virtual')) {
                    return $id;
                }
            }
        }
        if (str_contains($clave, 'presencial')) {
            foreach ($mapa as $nombre => $id) {
                if (str_contains($nombre, 'presencial')) {
                    return $id;
                }
            }
        }

        return $mapa !== [] ? (int)reset($mapa) : null;
    }

    private function mapearAmbito(string $proyecto): ?string
    {
        $clave = $this->clave($proyecto);
        if (str_contains($clave, 'admin')) {
            return 'ADMINISTRACION';
        }
        if ($clave === 'proyecto' || str_contains($clave, 'proyecto')) {
            return 'PROYECTO';
        }

        return null;
    }

    private function mapearEstado(string $estado): string
    {
        $clave = $this->clave($estado);
        if ($clave === 'activo') {
            return 'Activo';
        }

        return 'Inactivo';
    }

    private function parsearHoras(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            $n = (float)$valor;

            return $n > 0 ? round($n, 2) : null;
        }
        $texto = trim((string)$valor);
        if (preg_match('/(\d+(?:[.,]\d+)?)/', $texto, $m) !== 1) {
            return null;
        }
        $n = (float)str_replace(',', '.', $m[1]);

        return $n > 0 ? round($n, 2) : null;
    }

    private function parsearNota(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (!is_numeric($valor)) {
            return null;
        }
        $n = round((float)$valor, 2);

        return ($n >= 0 && $n <= 5) ? $n : null;
    }

    private function fechaDesdeMes(mixed $valor, int $anio): ?string
    {
        $fecha = $this->personal->parsearFecha($valor);
        if ($fecha !== null) {
            return $fecha;
        }
        $texto = $this->clave((string)$valor);
        $meses = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10,
            'noviembre' => 11, 'diciembre' => 12, 'ene' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4,
            'may' => 5, 'jun' => 6, 'jul' => 7, 'ago' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12,
        ];
        if (isset($meses[$texto])) {
            return sprintf('%04d-%02d-01', $anio, $meses[$texto]);
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $planCaps
     */
    private function horasDeCap(array $planCaps, string $codigo): float
    {
        foreach ($planCaps as $c) {
            if (($c['codigo'] ?? '') === $codigo && isset($c['horas'])) {
                return (float)$c['horas'];
            }
        }
        $fila = $this->capacitaciones->buscarPorCodigo($codigo);

        return $fila !== null && $fila['duracion_estimada_horas'] !== null
            ? (float)$fila['duracion_estimada_horas']
            : 1.0;
    }

    /**
     * @return array{detectados:int,validos:int,inconsistencias:int,existentes:int}
     */
    private function bloqueConteo(int $detectados, int $validos, int $inc, int $existentes): array
    {
        return [
            'detectados' => $detectados,
            'validos' => $validos,
            'inconsistencias' => max(0, $inc),
            'existentes' => $existentes,
        ];
    }

    /** @param list<array<string,mixed>> $items */
    private function contarSeveridad(array $items, string $sev): int
    {
        $n = 0;
        foreach ($items as $item) {
            if (($item['severidad'] ?? '') === $sev) {
                $n++;
            }
        }

        return $n;
    }

    /** @param list<array<string,mixed>> $items */
    private function contarErrores(array $items, string $tipo): int
    {
        $n = 0;
        foreach ($items as $item) {
            if (($item['tipo'] ?? '') === $tipo && ($item['severidad'] ?? '') === 'Error') {
                $n++;
            }
        }

        return $n;
    }

    /** @param list<string> $docs */
    private function contarDocsEnSistema(array $docs): int
    {
        return count($this->personal->repositorio()->documentosExistentes($docs));
    }

    /** @param list<string> $codigos */
    private function contarCodigosEnSistema(array $codigos): int
    {
        $n = 0;
        foreach (array_unique($codigos) as $codigo) {
            if ($this->capacitaciones->buscarPorCodigo($codigo) !== null) {
                $n++;
            }
        }

        return $n;
    }

    private function clave(string $texto): string
    {
        return $this->personal->repositorio()->claveCargo($texto);
    }

    private function aJson(mixed $valor): string
    {
        $codificado = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $codificado === false ? '[]' : $codificado;
    }

    private function desdeJson(mixed $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $dec = json_decode((string)$raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $dec : $raw;
    }
}
