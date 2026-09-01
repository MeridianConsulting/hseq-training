<?php

declare(strict_types=1);

/**
 * Pruebas de reportes HSEQ: mismos filtros y totales en pantalla y Excel.
 * Uso: php database/probar_reportes.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\AlertaService;
use App\Services\PersonalService;
use App\Services\ReporteService;
use PhpOffice\PhpSpreadsheet\IOFactory;

Env::load(BASE_PATH);
date_default_timezone_set('America/Bogota');

function ok(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
    echo "OK: {$mensaje}\n";
}

function esperaRechazo(callable $fn, string $mensaje, int $status = 422): string
{
    try {
        $fn();
        fwrite(STDERR, "FALLO: se esperaba rechazo — {$mensaje}\n");
        exit(1);
    } catch (HttpException $e) {
        ok($e->getStatusCode() === $status, $mensaje . ' [' . $e->getStatusCode() . '] ' . $e->getMessage());
        return $e->getMessage();
    }
}

function excelValor($hoja, string $etiqueta): mixed
{
    foreach ($hoja->getRowIterator() as $fila) {
        $n = $fila->getRowIndex();
        $a = (string)$hoja->getCell('A' . $n)->getValue();
        if (strcasecmp($a, $etiqueta) === 0) {
            return $hoja->getCell('B' . $n)->getValue();
        }
    }

    return null;
}

function limpiarPersonas(Database $db, $personalDb, string $personasT, string $contratosT, array $docs): void
{
    foreach ($docs as $doc) {
        $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
        if ($prev === null) {
            continue;
        }
        $pid = (int)$prev['persona_id'];
        $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
        foreach ($asigs as $a) {
            $aid = (int)$a['asignacion_id'];
            $cump = $db->fetchAll('SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ?', [$aid]);
            foreach ($cump as $c) {
                $db->query('DELETE FROM soportes_cumplimiento WHERE cumplimiento_id = ?', [(int)$c['cumplimiento_id']]);
                $db->query('DELETE FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?', [(int)$c['cumplimiento_id']]);
            }
            $db->query('DELETE FROM sesion_participantes WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [$aid]);
        }
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
    }
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$reportes = new ReporteService();
$alertas = new AlertaService();

$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$stamp = date('YmdHis');
$prefijoDoc = '900033' . substr($stamp, -4);
$docs = [];
for ($i = 1; $i <= 25; $i++) {
    $docs[] = $prefijoDoc . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
}

$proyecto = 'HSEQ-REP-' . $stamp;
$nombreProcOp = 'HSEQ-REP-OP-' . $stamp;
$nombreProcAd = 'HSEQ-REP-AD-' . $stamp;
$capIds = [];
$sesionIds = [];
$procesoOpId = 0;
$procesoAdId = 0;

echo "== Limpieza previa ==\n";
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);

try {
    $cargos = $personal->cargos();
    ok(count($cargos) >= 1, 'Hay cargos corporativos');
    $cargoId = (int)$cargos[0]['cargo_id'];

    $procesoOpId = (int)$db->insert('procesos', [
        'nombre' => $nombreProcOp,
        'activo' => 1,
    ]);
    $procesoAdId = (int)$db->insert('procesos', [
        'nombre' => $nombreProcAd,
        'activo' => 1,
    ]);
    ok($procesoOpId > 0 && $procesoAdId > 0, 'Procesos de prueba creados');

    $periodo = $db->fetch(
        'SELECT periodicidad_id FROM periodicidades WHERE activo = 1 ORDER BY periodicidad_id ASC LIMIT 1'
    );
    $periodoId = $periodo !== null ? (int)$periodo['periodicidad_id'] : null;

    $capBase = (int)$db->insert('capacitaciones', [
        'codigo' => 'RPB' . substr($stamp, -8),
        'nombre' => 'Cap reporte base ' . $stamp,
        'objetivo' => 'Prueba de reportes HSEQ',
        'duracion_estimada_horas' => 4,
        'criticidad' => 'MEDIA',
        'estado' => 'ACTIVA',
        'periodicidad_default_id' => $periodoId,
        'certificado' => 0,
        'es_tarea_critica' => 0,
    ]);
    $capIds[] = $capBase;
    $capCrit = (int)$db->insert('capacitaciones', [
        'codigo' => 'RPC' . substr($stamp, -8),
        'nombre' => 'Cap critica reporte ' . $stamp,
        'objetivo' => 'Tarea critica de prueba',
        'duracion_estimada_horas' => 2,
        'criticidad' => 'ALTA',
        'estado' => 'ACTIVA',
        'es_tarea_critica' => 1,
        'certificado' => 0,
        'periodicidad_default_id' => $periodoId,
    ]);
    $capIds[] = $capCrit;
    $capEv = (int)$db->insert('capacitaciones', [
        'codigo' => 'RPE' . substr($stamp, -8),
        'nombre' => 'Cap evidencia reporte ' . $stamp,
        'objetivo' => 'Requiere certificado',
        'duracion_estimada_horas' => 3,
        'criticidad' => 'MEDIA',
        'estado' => 'ACTIVA',
        'certificado' => 1,
        'es_tarea_critica' => 0,
        'periodicidad_default_id' => $periodoId,
    ]);
    $capIds[] = $capEv;

    $tipoInd = $db->fetch(
        "SELECT tipo_capacitacion_id FROM tipos_capacitacion WHERE UPPER(nombre) = 'INDUCCION' LIMIT 1"
    );
    $capInd = (int)$db->insert('capacitaciones', [
        'codigo' => 'RPI' . substr($stamp, -8),
        'nombre' => 'Induccion reporte ' . $stamp,
        'objetivo' => 'Induccion de prueba',
        'duracion_estimada_horas' => 2,
        'criticidad' => 'MEDIA',
        'estado' => 'ACTIVA',
        'tipo_capacitacion_id' => $tipoInd !== null ? (int)$tipoInd['tipo_capacitacion_id'] : null,
        'certificado' => 0,
        'periodicidad_default_id' => $periodoId,
    ]);
    $capIds[] = $capInd;

    $modalidad = $db->fetch('SELECT modalidad_id FROM modalidades LIMIT 1');
    ok($modalidad !== null, 'Hay modalidad para sesión');

    $personasIds = [];
    for ($i = 1; $i <= 22; $i++) {
        $prep = $personal->prepararEntrada([
            'numero_documento' => $docs[$i - 1],
            'nombre_completo' => "Reporte Persona {$i}",
            'correo' => "rep{$i}.{$stamp}@hseq.test",
            'cargo_id' => $cargoId,
            'proyecto' => $proyecto,
            'fecha_ingreso' => '2026-02-01',
        ], null);
        ok($prep['ok'] === true, "Trabajador {$i} válido");
        $personasIds[] = $personal->persistirAlta($prep['datos']);
    }
    ok(count($personasIds) === 22, '22 trabajadores sin motor de matriz');

    $crearAsig = static function (
        Database $db,
        int $personaId,
        int $capId,
        int $procesoId,
        string $proyecto,
        int $cargoId,
        string $fechaAsig,
        string $limite,
        string $origen = 'MANUAL'
    ): int {
        return (int)$db->insert('asignaciones_capacitacion', [
            'persona_id_ext' => $personaId,
            'capacitacion_id' => $capId,
            'fecha_asignacion' => $fechaAsig,
            'fecha_limite_cumplimiento' => $limite,
            'origen' => $origen,
            'proceso_id' => $procesoId,
            'proyecto' => $proyecto,
            'cargo_id_ext' => $cargoId,
        ]);
    };

    $asigIds = [];
    for ($i = 0; $i < 20; $i++) {
        $asigIds[] = $crearAsig(
            $db,
            $personasIds[$i],
            $capBase,
            $procesoOpId,
            $proyecto,
            $cargoId,
            '2026-03-15',
            '2026-12-31'
        );
    }
    $asigCrit = $crearAsig($db, $personasIds[20], $capCrit, $procesoOpId, $proyecto, $cargoId, '2026-04-01', '2026-12-31');
    $asigEv = $crearAsig($db, $personasIds[21], $capEv, $procesoOpId, $proyecto, $cargoId, '2026-04-02', '2026-12-31');
    $asigInd = $crearAsig(
        $db,
        $personasIds[0],
        $capInd,
        $procesoOpId,
        $proyecto,
        $cargoId,
        '2026-03-20',
        '2026-12-31',
        'INDUCCION'
    );
    $asigAd = $crearAsig($db, $personasIds[1], $capBase, $procesoAdId, $proyecto, $cargoId, '2026-03-16', '2026-12-31');

    $db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigIds[0],
        'fecha_realizacion' => '2026-05-10',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 4.5,
        'fecha_vencimiento' => '2027-05-10',
    ]);
    $db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigIds[1],
        'fecha_realizacion' => '2026-05-11',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 3.0,
        'fecha_vencimiento' => '2026-08-01',
    ]);
    $venceAlerta = (new DateTimeImmutable('today'))->modify('+5 days')->format('Y-m-d');
    $db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigIds[2],
        'fecha_realizacion' => '2026-05-12',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 2.0,
        'fecha_vencimiento' => $venceAlerta,
    ]);
    $cumpEv = (int)$db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigEv,
        'fecha_realizacion' => '2026-06-01',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 3.0,
        'fecha_vencimiento' => '2027-06-01',
    ]);

    $sesionId = (int)$db->insert('sesiones_capacitacion', [
        'capacitacion_id' => $capBase,
        'fecha_hora' => '2026-05-10 08:00:00',
        'modalidad_id' => (int)$modalidad['modalidad_id'],
    ]);
    $sesionIds[] = $sesionId;
    $db->insert('sesion_participantes', [
        'sesion_id' => $sesionId,
        'asignacion_id' => $asigIds[0],
        'estado_asistencia' => 'ASISTIO',
    ]);
    $db->insert('sesion_participantes', [
        'sesion_id' => $sesionId,
        'asignacion_id' => $asigIds[3],
        'estado_asistencia' => 'AUSENTE',
        'motivo_ausencia' => 'Incapacidad',
    ]);

    $filtros = [
        'desde' => '2026-01-01',
        'hasta' => '2026-08-31',
        'proceso_id' => $procesoOpId,
        'proyecto' => $proyecto,
    ];

    echo "\n== Cumplimiento general: pantalla = Excel ==\n";
    $json = $reportes->consultar('cumplimiento_general', $filtros, 1, 20);
    ok($json['total'] > 20, 'Hay más de una página got=' . $json['total']);
    ok(count($json['items']) === 20, 'Página 1 trae 20 filas got=' . count($json['items']));
    $tot = $json['totales'];
    ok((int)$tot['asignadas'] === (int)$json['total'], 'Totales.asignadas = pagination.total');
    ok((int)$tot['completadas'] >= 1, 'Hay completadas');
    ok((int)$tot['pendientes'] >= 1, 'Hay pendientes');
    ok((int)$tot['vencidas'] >= 1, 'Hay vencidas');

    $xlsx = $reportes->excel('cumplimiento_general', $filtros, 'prueba');
    $tmp = tempnam(sys_get_temp_dir(), 'rep');
    file_put_contents($tmp, $xlsx['contenido']);
    $libro = IOFactory::load($tmp);
    $hoja = $libro->getActiveSheet();
    ok((int)excelValor($hoja, 'TOTAL REGISTROS') === (int)$tot['asignadas'], 'Excel TOTAL REGISTROS = pantalla');
    ok((int)excelValor($hoja, 'COMPLETADAS') === (int)$tot['completadas'], 'Excel COMPLETADAS = pantalla');
    ok((int)excelValor($hoja, 'PENDIENTES') === (int)$tot['pendientes'], 'Excel PENDIENTES = pantalla');
    ok((int)excelValor($hoja, 'VENCIDAS') === (int)$tot['vencidas'], 'Excel VENCIDAS = pantalla');
    $libro->disconnectWorksheets();
    unlink($tmp);

    echo "\n== Cambio de proceso ==\n";
    $jsonAd = $reportes->consultar('cumplimiento_general', [
        'desde' => '2026-01-01',
        'hasta' => '2026-08-31',
        'proceso_id' => $procesoAdId,
        'proyecto' => $proyecto,
    ], 1, 20);
    ok((int)$jsonAd['total'] === 1, 'Proceso administrativo = 1 asignación got=' . $jsonAd['total']);
    $xlsxAd = $reportes->excel('cumplimiento_general', [
        'desde' => '2026-01-01',
        'hasta' => '2026-08-31',
        'proceso_id' => $procesoAdId,
        'proyecto' => $proyecto,
    ], 'prueba');
    $tmp = tempnam(sys_get_temp_dir(), 'rep');
    file_put_contents($tmp, $xlsxAd['contenido']);
    $libro = IOFactory::load($tmp);
    ok((int)excelValor($libro->getActiveSheet(), 'TOTAL REGISTROS') === 1, 'Excel del proceso administrativo = 1');
    $libro->disconnectWorksheets();
    unlink($tmp);

    echo "\n== Sin resultados ==\n";
    $vacio = $reportes->consultar('cumplimiento_general', [
        'desde' => '2026-01-01',
        'hasta' => '2026-08-31',
        'proyecto' => 'NO-EXISTE-' . $stamp,
    ], 1, 20);
    ok((int)$vacio['total'] === 0, 'Filtro sin datos total=0');
    esperaRechazo(function () use ($reportes, $stamp): void {
        $reportes->excel('cumplimiento_general', ['proyecto' => 'NO-EXISTE-' . $stamp], 'prueba');
    }, 'Exportar vacío responde 422');

    echo "\n== Horas ==\n";
    $horas = $reportes->consultar('horas', $filtros, 1, 20);
    ok((float)$horas['totales']['horas'] === 12.5, 'Total horas 4.5+3+2+3=12.5 got=' . $horas['totales']['horas']);
    $xlsxH = $reportes->excel('horas', $filtros, 'prueba');
    $tmp = tempnam(sys_get_temp_dir(), 'rep');
    file_put_contents($tmp, $xlsxH['contenido']);
    $libro = IOFactory::load($tmp);
    ok((float)excelValor($libro->getActiveSheet(), 'TOTAL HORAS') === 12.5, 'Excel TOTAL HORAS = pantalla');
    $libro->disconnectWorksheets();
    unlink($tmp);

    echo "\n== Vencidas / pendientes / críticas / inducción ==\n";
    $venc = $reportes->consultar('vencidas', $filtros, 1, 20);
    ok((int)$venc['total'] >= 1, 'Reporte vencidas tiene filas');
    $pend = $reportes->consultar('pendientes', $filtros, 1, 50);
    ok((int)$pend['total'] >= 1, 'Reporte pendientes tiene filas');
    $crit = $reportes->consultar('tareas_criticas', $filtros, 1, 20);
    ok((int)$crit['total'] === 1, 'Tareas críticas = 1 got=' . $crit['total']);
    $ind = $reportes->consultar('inducciones', $filtros, 1, 20);
    $hayInd = false;
    foreach ($ind['items'] as $fila) {
        if (($fila['origen'] ?? '') === 'INDUCCION' && str_contains((string)($fila['capacitacion'] ?? ''), 'Induccion reporte')) {
            $hayInd = true;
        }
    }
    ok($hayInd, 'Inducción de prueba aparece con origen INDUCCION');

    echo "\n== Próximas vs alertas ==\n";
    $prox = $reportes->consultar('proximas', ['proceso_id' => $procesoOpId, 'proyecto' => $proyecto], 1, 50);
    $alr = $alertas->listar(1, 50, ['proceso_id' => $procesoOpId, 'proyecto' => $proyecto]);
    ok((int)$prox['total'] === (int)$alr['total'], 'Próximas = AlertaService total got=' . $prox['total'] . '/' . $alr['total']);
    ok((int)$prox['total'] >= 1, 'Hay al menos una próxima a vencer de prueba');

    echo "\n== Asistencia ==\n";
    $asist = $reportes->consultar('asistencia', $filtros, 1, 20);
    ok((int)$asist['totales']['asistieron'] === 1, 'Asistieron=1');
    ok((int)$asist['totales']['ausentes'] === 1, 'Ausentes=1');
    $xlsxA = $reportes->excel('asistencia', $filtros, 'prueba');
    $tmp = tempnam(sys_get_temp_dir(), 'rep');
    file_put_contents($tmp, $xlsxA['contenido']);
    $libro = IOFactory::load($tmp);
    ok((int)excelValor($libro->getActiveSheet(), 'ASISTIERON') === 1, 'Excel ASISTIERON=1');
    $libro->disconnectWorksheets();
    unlink($tmp);

    echo "\n== Evidencias faltantes ==\n";
    $ev = $reportes->consultar('evidencias_faltantes', $filtros, 1, 20);
    ok((int)$ev['total'] === 1, 'Evidencia faltante aparece got=' . $ev['total']);
    $db->insert('soportes_cumplimiento', [
        'cumplimiento_id' => $cumpEv,
        'tipo_soporte' => 'CERTIFICADO',
        'nombre_archivo' => 'cert.pdf',
        'ruta_archivo' => 'tmp/cert.pdf',
        'mime_type' => 'application/pdf',
    ]);
    $ev2 = $reportes->consultar('evidencias_faltantes', $filtros, 1, 20);
    ok((int)$ev2['total'] === 0, 'Desaparece al adjuntar evidencia');

    echo "\n== Agrupado por proceso ==\n";
    $porProc = $reportes->consultar('cumplimiento_proceso', [
        'desde' => '2026-01-01',
        'hasta' => '2026-08-31',
        'proyecto' => $proyecto,
    ], 1, 20);
    ok((int)$porProc['total'] >= 2, 'Hay al menos 2 grupos de proceso');
    $xlsxP = $reportes->excel('cumplimiento_proceso', [
        'desde' => '2026-01-01',
        'hasta' => '2026-08-31',
        'proyecto' => $proyecto,
    ], 'prueba');
    $tmp = tempnam(sys_get_temp_dir(), 'rep');
    file_put_contents($tmp, $xlsxP['contenido']);
    $libro = IOFactory::load($tmp);
    ok(
        (int)excelValor($libro->getActiveSheet(), 'TOTAL REGISTROS') === (int)$porProc['totales']['asignadas'],
        'Excel agrupado TOTAL = suma asignadas'
    );
    $libro->disconnectWorksheets();
    unlink($tmp);

    echo "\n== Limpieza final ==\n";
} finally {
    foreach ($sesionIds as $sid) {
        $db->query('DELETE FROM sesion_participantes WHERE sesion_id = ?', [$sid]);
        $db->query('DELETE FROM sesiones_capacitacion WHERE sesion_id = ?', [$sid]);
    }
    limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
    foreach ($capIds as $cid) {
        $db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$cid]);
        $asig = $db->fetch('SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id = ? LIMIT 1', [$cid]);
        if ($asig === null) {
            $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$cid]);
        }
    }
    foreach ([$procesoOpId, $procesoAdId] as $pid) {
        if ($pid > 0) {
            $db->query('DELETE FROM procesos WHERE proceso_id = ?', [$pid]);
        }
    }
    echo "OK: Limpieza final\n";
}

echo "\nTodas las pruebas de reportes OK.\n";
