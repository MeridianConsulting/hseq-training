<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Core\Logger;
use App\Repositories\AsignacionRepository;
use App\Repositories\CapacitacionRepository;
use App\Repositories\CumplimientoRepository;
use App\Repositories\MigracionRepository;
use PDOException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

/**
 * Carga inicial de historial: Excel → ejecuciones reales → vigencia de catálogo → Alertas.
 * No crea personas, capacitaciones, matriz ni asignaciones pendientes.
 * Cada cumplimiento requiere un contenedor de asignación (FK); fecha_limite = fecha_realizacion.
 */
class MigracionService
{
    public const MSG_ARCHIVO = 'No fue posible procesar el archivo. Verifique que corresponde a la matriz HSEQ requerida.';
    public const MSG_PERSONAL = 'No fue posible consultar el maestro de personal corporativo. No se importará el archivo.';
    public const ACCION_AUDITORIA = 'migracion_inicial';
    public const ORIGEN_OBSERVACION = 'Carga inicial';

    /** @var bool Solo pruebas: el siguiente confirmar() lanza tras insertar. */
    public static bool $fallarImportacion = false;

    private MigracionRepository $repo;
    private MigracionPrg10Parser $parser;
    private PersonalService $personal;
    private CapacitacionRepository $capacitaciones;
    private AsignacionRepository $asignaciones;
    private CumplimientoRepository $cumplimientos;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->repo = new MigracionRepository();
        $this->parser = new MigracionPrg10Parser();
        $this->personal = new PersonalService();
        $this->capacitaciones = new CapacitacionRepository();
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

        try {
            $dry = $this->dryRun($leido, $anioPrograma);
        } catch (HttpException $e) {
            @unlink($tmp);
            throw $e;
        } catch (Throwable $e) {
            @unlink($tmp);
            Logger::error(self::MSG_PERSONAL . ': ' . $e->getMessage());
            throw new HttpException(self::MSG_PERSONAL, $e instanceof PDOException ? 503 : 500);
        }
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
        if (empty($resumen['estructura_valida'])) {
            throw new HttpException(
                'La estructura del archivo no es válida. Corrija el archivo antes de confirmar.',
                422
            );
        }
        $plan = is_array($resumen['plan'] ?? null) ? $resumen['plan'] : [];

        try {
            $conteos = $this->repo->transaccion(function () use ($id, $plan, $actor, $fila, $resumen) {
                $conteos = $this->consolidarConteos($resumen, $this->ejecutarPlan($plan, $id, $actor));
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
                        'modo' => 'historial',
                        'archivo' => $fila['nombre_archivo'] ?? null,
                        'anio_programa' => (int)($fila['anio_programa'] ?? 0),
                        'conteos' => $conteos,
                        'estado' => 'CONFIRMADA',
                        'nota' => 'Registra el pasado. No programa Fecha Desde/Hasta ni asignaciones futuras.',
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

        $docsEnArchivo = [];
        $planE = [];
        $docsValidos = [];
        $personaIds = [];

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
        $planCaps = [];
        $capsOk = 0;
        $capsExistentes = 0;
        foreach ($caps as $cap) {
            $codigo = trim((string)($cap['codigo'] ?? ''));
            $nombre = trim((string)($cap['nombre'] ?? ''));
            $fila = (int)($cap['fila'] ?? 0);
            if ($codigo === '' || $nombre === '') {
                $agregar('CRONOGRAMA', $fila, 'capacitacion', $codigo, 'nombre', $nombre, 'Capacitación no encontrada.');
                continue;
            }
            $existente = $this->capacitaciones->buscarPorCodigo($codigo);
            if ($existente === null) {
                $agregar(
                    'CRONOGRAMA',
                    $fila,
                    'capacitacion',
                    $codigo,
                    'codigo',
                    $codigo,
                    'Capacitación no encontrada. Créela en el catálogo antes de importar el historial.'
                );
                continue;
            }
            $capsExistentes++;
            $capsOk++;
            $planCaps[$codigo] = [
                'codigo' => $codigo,
                'accion' => 'existente',
                'capacitacion_id' => (int)$existente['capacitacion_id'],
                'horas' => $existente['duracion_estimada_horas'] !== null
                    ? (float)$existente['duracion_estimada_horas']
                    : null,
                'evaluacion' => (int)($existente['evaluacion'] ?? 0) === 1,
                'nota_minima' => round((float)($existente['nota_minima'] ?? 0), 2),
                'vigencia_cantidad' => $existente['vigencia_cantidad'] !== null
                    ? (int)$existente['vigencia_cantidad']
                    : null,
                'vigencia_unidad' => $existente['vigencia_unidad'] !== null && $existente['vigencia_unidad'] !== ''
                    ? (string)$existente['vigencia_unidad']
                    : null,
                'estado' => (string)($existente['estado'] ?? ''),
            ];
        }

        $estructuraOk = (bool)($leido['estructura_ok'] ?? false);
        $trabajadores = is_array($leido['trabajadores'] ?? null) ? $leido['trabajadores'] : [];
        $personasOk = 0;
        $personasExistentes = 0;

        $docsNormalizados = [];
        foreach ($trabajadores as $t) {
            $docsNormalizados[] = $this->personal->normalizarDocumento($t['documento'] ?? '');
        }

        try {
            $idsPorDoc = $this->personal->repositorio()->idsPorDocumentos(array_values(array_filter($docsNormalizados)));
        } catch (Throwable $e) {
            Logger::error(self::MSG_PERSONAL . ': ' . $e->getMessage());
            throw new HttpException(self::MSG_PERSONAL, $e instanceof PDOException ? 503 : 500);
        }

        foreach ($trabajadores as $t) {
            $fila = (int)($t['fila'] ?? 0);
            $doc = $this->personal->normalizarDocumento($t['documento'] ?? '');
            if ($doc === '') {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'trabajador',
                    (string)($t['nombre'] ?? ''),
                    'documento',
                    '',
                    'Campo obligatorio vacío.'
                );
                continue;
            }
            if (isset($docsEnArchivo[$doc])) {
                $agregar('SEGUIMIENTO_PERSONAL', $fila, 'trabajador', $doc, 'documento', $doc, 'Documento duplicado.');
                continue;
            }
            $docsEnArchivo[$doc] = $fila;
            if (!isset($idsPorDoc[$doc])) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'trabajador',
                    $doc,
                    'documento',
                    $doc,
                    'Trabajador no encontrado.'
                );
                continue;
            }
            $personasExistentes++;
            $personasOk++;
            $docsValidos[$doc] = true;
            $personaIds[$doc] = $idsPorDoc[$doc];
        }

        $matrizFilas = is_array($leido['matriz'] ?? null) ? $leido['matriz'] : [];
        if ($matrizFilas !== [] && $estructuraOk) {
            $agregar(
                'MATRIZ POR CARGO',
                0,
                'archivo',
                '',
                'matriz',
                '',
                'La hoja MATRIZ POR CARGO no se importa. La carga inicial solo registra historial de ejecuciones.',
                'Advertencia'
            );
        }

        $segs = is_array($leido['seguimientos'] ?? null) ? $leido['seguimientos'] : [];
        $eDetectados = 0;
        $eOk = 0;
        $eDuplicados = 0;
        $pOmitidos = 0;
        $clavesArchivo = [];
        $mapaExistentes = $this->cumplimientos->mapaEjecuciones(array_values($personaIds));

        foreach ($segs as $s) {
            $estado = strtoupper(trim((string)($s['estado'] ?? '')));
            $fila = (int)($s['fila'] ?? 0);
            $doc = $this->personal->normalizarDocumento($s['documento'] ?? '');
            $codigo = trim((string)($s['codigo'] ?? ''));
            if ($estado === 'N/A' || $estado === 'NA' || $estado === '') {
                continue;
            }
            if ($estado === 'P') {
                $pOmitidos++;
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'asignacion',
                    $doc !== '' ? $doc : $codigo,
                    'estado',
                    $estado,
                    'No importable: la carga inicial no programa asignaciones futuras. HSEQ debe asignar manualmente Fecha Desde/Hasta.'
                );
                continue;
            }
            if ($estado !== 'E') {
                continue;
            }
            $eDetectados++;
            if ($doc === '' || !isset($docsValidos[$doc])) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'cumplimiento',
                    $doc !== '' ? $doc : $codigo,
                    'documento',
                    $doc,
                    'Trabajador no encontrado.'
                );
                continue;
            }
            if (!isset($planCaps[$codigo])) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'cumplimiento',
                    $doc,
                    'capacitacion',
                    $codigo,
                    'Capacitación no encontrada.'
                );
                continue;
            }
            $fecha = $this->fechaDesdeMes($s['mes'] ?? null, $anio);
            if ($fecha === null) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'cumplimiento',
                    $doc,
                    'mes',
                    (string)($s['mes'] ?? ''),
                    'Fecha de realización inválida.'
                );
                continue;
            }
            $cap = $planCaps[$codigo];
            $nota = $this->parsearNota($s['nota'] ?? null);
            if (!empty($cap['evaluacion']) && $nota !== null && $nota < (float)$cap['nota_minima']) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'cumplimiento',
                    $doc,
                    'nota',
                    (string)$nota,
                    'La nota histórica es inferior a la nota mínima del catálogo.'
                );
                continue;
            }
            $pid = (int)$personaIds[$doc];
            $capId = (int)$cap['capacitacion_id'];
            $clave = $pid . '|' . $capId . '|' . $fecha;
            if (isset($clavesArchivo[$clave])) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'cumplimiento',
                    $doc,
                    'fecha_realizacion',
                    $fecha,
                    'Registro duplicado en el archivo.',
                    'Advertencia'
                );
                $eDuplicados++;
                continue;
            }
            $clavesArchivo[$clave] = true;
            if (isset($mapaExistentes[$clave])) {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'cumplimiento',
                    $doc,
                    'fecha_realizacion',
                    $fecha,
                    'Registro duplicado. Ya existe una ejecución equivalente.',
                    'Advertencia'
                );
                $eDuplicados++;
                continue;
            }
            $cert = strtoupper(trim((string)($s['certificado'] ?? '')));
            if ($cert === 'SI' || $cert === 'SÍ') {
                $agregar(
                    'SEGUIMIENTO_PERSONAL',
                    $fila,
                    'cumplimiento',
                    $doc,
                    'certificado',
                    $cert,
                    'Certificado marcado sin archivo adjunto. El soporte puede asociarse después en Cumplimientos.',
                    'Advertencia'
                );
            }
            $eOk++;
            $planE[] = [
                'documento' => $doc,
                'persona_id' => $pid,
                'codigo' => $codigo,
                'capacitacion_id' => $capId,
                'fecha_realizacion' => $fecha,
                'nota' => $nota,
                'horas' => $cap['horas'],
                'vigencia_cantidad' => $cap['vigencia_cantidad'],
                'vigencia_unidad' => $cap['vigencia_unidad'],
            ];
        }

        $resumen = [
            'archivo' => null,
            'hojas_detectadas' => $leido['hojas'] ?? [],
            'hojas_faltantes' => $leido['faltantes'] ?? [],
            'estructura_valida' => $estructuraOk,
            'anio_programa' => $anio,
            'trabajadores' => $this->bloqueConteo(
                count($trabajadores),
                $personasOk,
                count($trabajadores) - $personasOk,
                $personasExistentes
            ),
            'capacitaciones' => $this->bloqueConteo(count($caps), $capsOk, count($caps) - $capsOk, $capsExistentes),
            'matriz' => $this->bloqueConteo(count($matrizFilas), 0, 0, 0),
            'cumplimientos' => $this->bloqueConteo($eDetectados, $eOk, $eDetectados - $eOk - $eDuplicados, $eDuplicados),
            'omitidos_pendientes' => $pOmitidos,
            'omitidos_duplicado' => $eDuplicados,
            'inconsistencias_total' => count($inconsistencias),
            'errores' => $this->contarSeveridad($inconsistencias, 'Error'),
            'advertencias' => $this->contarSeveridad($inconsistencias, 'Advertencia'),
            'plan' => [
                'cumplimientos' => $planE,
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
     * @param array{usuario_id:?int,nombre:?string,ip:?string} $actor
     * @return array<string,mixed>
     */
    private function ejecutarPlan(array $plan, int $migracionId, array $actor): array
    {
        $usuarioId = isset($actor['usuario_id']) ? (int)$actor['usuario_id'] : null;
        $cumpNuevos = 0;
        $cumpExistentes = 0;
        $docs = [];
        $codigos = [];

        foreach ($plan['cumplimientos'] ?? [] as $item) {
            $doc = (string)($item['documento'] ?? '');
            $codigo = (string)($item['codigo'] ?? '');
            $fecha = (string)($item['fecha_realizacion'] ?? '');
            $pid = (int)($item['persona_id'] ?? 0);
            $capId = (int)($item['capacitacion_id'] ?? 0);
            if ($doc !== '') {
                $docs[$doc] = true;
            }
            if ($codigo !== '') {
                $codigos[$codigo] = true;
            }
            if ($pid < 1) {
                $pid = (int)($this->personal->repositorio()->buscarIdPorDocumento($doc) ?? 0);
            }
            if ($capId < 1 && $codigo !== '') {
                $cap = $this->capacitaciones->buscarPorCodigo($codigo);
                $capId = $cap !== null ? (int)$cap['capacitacion_id'] : 0;
            }
            if ($pid < 1 || $capId < 1 || $fecha === '') {
                continue;
            }
            if ($this->cumplimientos->existeEjecucion($pid, $capId, $fecha)) {
                $cumpExistentes++;
                continue;
            }

            try {
                $persona = $this->personal->ver($pid);
            } catch (Throwable $e) {
                continue;
            }

            $horas = $item['horas'] ?? null;
            if ($horas === null || (float)$horas <= 0) {
                $cap = $this->capacitaciones->buscarPorCodigo($codigo);
                $horas = $cap !== null && $cap['duracion_estimada_horas'] !== null
                    ? (float)$cap['duracion_estimada_horas']
                    : 0;
            }

            $vence = VencimientoService::calcularFechaVencimiento(
                $fecha,
                isset($item['vigencia_cantidad']) ? (int)$item['vigencia_cantidad'] : null,
                isset($item['vigencia_unidad']) ? (string)$item['vigencia_unidad'] : null
            );

            $asigId = $this->asignaciones->crear([
                'persona_id_ext' => $pid,
                'contrato_id_ext' => $persona['contrato_id'] ?? null,
                'capacitacion_id' => $capId,
                'matriz_aplicabilidad_id' => null,
                'fecha_asignacion' => $fecha,
                'fecha_limite_cumplimiento' => $fecha,
                'origen' => 'MANUAL',
                'cargo_id_ext' => $persona['cargo_id'] ?? null,
                'area_id' => null,
                'proceso_id' => null,
                'ambito' => null,
                'proyecto' => $persona['proyecto'] ?? null,
                'creada_por_usuario_id_ext' => $usuarioId,
            ]);

            $this->cumplimientos->crear([
                'asignacion_id' => $asigId,
                'sesion_id' => null,
                'fecha_realizacion' => $fecha,
                'resultado' => CumplimientoService::RESULTADO_APROBADO,
                'horas_efectivas' => (float)$horas,
                'nota_evaluacion' => $item['nota'] ?? null,
                'observaciones' => self::ORIGEN_OBSERVACION . ' #' . $migracionId
                    . '. Historial real; no programa Fecha Desde/Hasta. HSEQ asigna el futuro manualmente.',
                'fecha_vencimiento' => $vence,
                'registrado_por_usuario_id_ext' => $usuarioId,
            ]);
            $cumpNuevos++;
        }

        $docsLista = array_keys($docs);
        $codigosLista = array_keys($codigos);

        return [
            'trabajadores' => [
                'procesados' => count($docsLista),
                'importados' => 0,
                'existentes' => $this->contarDocsEnSistema($docsLista),
                'sistema' => $this->contarDocsEnSistema($docsLista),
            ],
            'capacitaciones' => [
                'procesados' => count($codigosLista),
                'importados' => 0,
                'existentes' => $this->contarCodigosEnSistema($codigosLista),
                'sistema' => $this->contarCodigosEnSistema($codigosLista),
            ],
            'matriz' => [
                'procesados' => 0,
                'importados' => 0,
                'existentes' => 0,
                'sistema' => 0,
            ],
            'cumplimientos' => [
                'procesados' => count($plan['cumplimientos'] ?? []),
                'importados' => $cumpNuevos,
                'existentes' => $cumpExistentes,
                'sistema' => $cumpNuevos + $cumpExistentes,
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

    /** @param list<string> $docs */
    private function contarDocsEnSistema(array $docs): int
    {
        return count($this->personal->repositorio()->idsPorDocumentos($docs));
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
