<?php

declare(strict_types=1);

/**
 * Pruebas plan anual: borrador/revisión no cuentan, APROBADO sí, duplicados e histórico.
 * Uso: php database/probar_plan_anual.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AsignacionRepository;
use App\Repositories\DashboardRepository;
use App\Services\AsignacionService;
use App\Services\CronogramaService;
use App\Services\DashboardService;
use App\Services\PersonalService;
use App\Services\PlanAnualService;

Env::load(BASE_PATH);

function ok(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
    echo "OK: {$mensaje}\n";
}

function borrarPlanAnio(Database $db, int $anio): void
{
    $plan = $db->fetch('SELECT plan_anual_id FROM planes_anuales WHERE anio = ?', [$anio]);
    if ($plan === null) {
        return;
    }
    $planId = (int)$plan['plan_anual_id'];
    $detalles = $db->fetchAll(
        'SELECT plan_detalle_id FROM plan_anual_detalle WHERE plan_anual_id = ?',
        [$planId]
    );
    foreach ($detalles as $d) {
        $id = (int)$d['plan_detalle_id'];
        $db->query('DELETE FROM plan_detalle_asignaciones WHERE plan_detalle_id = ?', [$id]);
        $db->query('UPDATE sesiones_capacitacion SET plan_detalle_id = NULL WHERE plan_detalle_id = ?', [$id]);
        $db->query('DELETE FROM plan_anual_detalle WHERE plan_detalle_id = ?', [$id]);
    }
    $db->query('DELETE FROM planes_anuales WHERE plan_anual_id = ?', [$planId]);
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$asigRepo = new AsignacionRepository();
$planes = new PlanAnualService();
$dashRepo = new DashboardRepository();
$periodos = new DashboardService();
$cronograma = new CronogramaService();

$anioPrueba = 2027;
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$docs = ['9000770201', '9000770202'];

echo "== Limpieza previa año {$anioPrueba} ==\n";
borrarPlanAnio($db, $anioPrueba);
foreach ($docs as $doc) {
    $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
    if ($prev !== null) {
        $pid = (int)$prev['persona_id'];
        $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
        foreach ($asigs as $a) {
            $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [(int)$a['asignacion_id']]);
            $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [(int)$a['asignacion_id']]);
        }
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
    }
}

$cargos = $personal->cargos();
ok(count($cargos) >= 1, 'Hay cargos corporativos');
$cargoId = (int)$cargos[0]['cargo_id'];

$capsCreadas = [];
for ($i = 1; $i <= 3; $i++) {
    $codigo = 'PLAN-R' . $i . '-' . date('YmdHis');
    $capsCreadas[] = [
        'capacitacion_id' => (int)$db->insert('capacitaciones', [
            'codigo' => $codigo,
            'nombre' => "Cap plan anual prueba {$i}",
            'objetivo' => 'Prueba plan anual',
            'duracion_estimada_horas' => 1,
            'criticidad' => 'BAJA',
            'estado' => 'ACTIVA',
        ]),
        'codigo' => $codigo,
    ];
}
$caps = $capsCreadas;

$p1 = $personal->crear([
    'numero_documento' => $docs[0],
    'nombre_completo' => 'Prueba Plan Anual Uno',
    'correo' => 'plananual1@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'HSEQ-PLAN-2027',
    'fecha_ingreso' => '2026-01-15',
]);
$p2 = $personal->crear([
    'numero_documento' => $docs[1],
    'nombre_completo' => 'Prueba Plan Anual Dos',
    'correo' => 'plananual2@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'HSEQ-PLAN-2027',
    'fecha_ingreso' => '2026-01-15',
]);
$persona1 = (int)$p1['persona_id'];
$persona2 = (int)$p2['persona_id'];

$man1 = $asignaciones->crear([
    'persona_id_ext' => $persona1,
    'capacitacion_id' => (int)$caps[0]['capacitacion_id'],
    'fecha_limite_cumplimiento' => '2027-12-31',
], 0);
$man2 = $asignaciones->crear([
    'persona_id_ext' => $persona1,
    'capacitacion_id' => (int)$caps[1]['capacitacion_id'],
    'fecha_limite_cumplimiento' => '2027-12-31',
], 0);
$autoId = $asigRepo->crear([
    'persona_id_ext' => $persona2,
    'contrato_id_ext' => $p2['contrato_id'],
    'capacitacion_id' => (int)$caps[2]['capacitacion_id'],
    'matriz_aplicabilidad_id' => null,
    'fecha_asignacion' => date('Y-m-d'),
    'fecha_limite_cumplimiento' => '2027-12-31',
    'origen' => 'AUTOMATICA',
    'cargo_id_ext' => $cargoId,
    'area_id' => null,
    'proceso_id' => null,
    'ambito' => null,
    'proyecto' => 'HSEQ-PLAN-2027',
    'creada_por_usuario_id_ext' => null,
]);
ok($man1['origen'] === 'MANUAL', 'Asignacion manual 1');
ok($man2['origen'] === 'MANUAL', 'Asignacion manual 2');
ok($autoId > 0, 'Asignacion automatica creada');

echo "\n== Crear plan 2027 borrador ==\n";
$plan = $planes->crear(['anio' => $anioPrueba], 1);
ok($plan['estado'] === 'BORRADOR', 'Estado BORRADOR');
ok((int)$plan['anio'] === $anioPrueba, 'Año 2027');
$planId = (int)$plan['plan_anual_id'];

$disp = $planes->disponibles($planId, null);
$idsDisp = array_map(static fn (array $a): int => (int)$a['asignacion_id'], $disp['items']);
ok(in_array((int)$man1['asignacion_id'], $idsDisp, true), 'Manual disponible');
ok(in_array($autoId, $idsDisp, true), 'Automatica disponible');

echo "\n== Incluir 3 asignaciones en ene/feb/mar ==\n";
$r1 = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => [(int)$man1['asignacion_id']],
    'mes_programado' => 1,
]);
$r2 = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => [(int)$man2['asignacion_id']],
    'mes_programado' => 2,
]);
$r3 = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => [$autoId],
    'mes_programado' => 3,
]);
ok($r1['creadas'] === 1 && $r2['creadas'] === 1 && $r3['creadas'] === 1, 'Tres incluidas');

$dup = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => [(int)$man1['asignacion_id']],
    'mes_programado' => 4,
]);
ok($dup['creadas'] === 0 && $dup['omitidas'] === 1, 'Duplicado omitido');

$periodo2027 = $periodos->periodo(['tipo' => 'anual', 'anio' => $anioPrueba]);
ok($dashRepo->programado($periodo2027, 'general') === 0, 'Borrador no cuenta en RF-001');

$tableroBorrador = $cronograma->tablero(['tipo' => 'anual', 'anio' => $anioPrueba]);
ok($tableroBorrador['total'] === 0, 'Cronograma no muestra borrador total=' . $tableroBorrador['total']);

echo "\n== En revision ==\n";
$enRev = $planes->enviarRevision($planId);
ok($enRev['estado'] === 'EN_REVISION', 'Estado EN_REVISION');
ok($dashRepo->programado($periodo2027, 'general') === 0, 'En revision no cuenta en RF-001');

try {
    $planes->incluirAsignaciones($planId, [
        'asignacion_ids' => [(int)$man1['asignacion_id']],
        'mes_programado' => 5,
    ]);
    ok(false, 'No debe editar en revision');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Edicion en revision rechazada');
}

echo "\n== Aprobar ==\n";
$aprobado = $planes->aprobar($planId, 1);
ok($aprobado['estado'] === 'APROBADO', 'Estado APROBADO');
ok($aprobado['aprobado_por_usuario_id_ext'] === 1, 'Usuario aprobador');
ok($aprobado['fecha_aprobacion'] !== null && $aprobado['fecha_aprobacion'] !== '', 'Fecha de aprobacion');

$programado = $dashRepo->programado($periodo2027, 'general');
ok($programado === 3, "RF-001 programado 2027={$programado}");

$tablero = $cronograma->tablero(['tipo' => 'anual', 'anio' => $anioPrueba]);
ok($tablero['total'] === 3, 'Cronograma 2027 muestra 3 total=' . $tablero['total']);
$mesesVistos = [];
foreach ($tablero['meses'] as $bloque) {
    if ($bloque['total'] > 0) {
        $mesesVistos[] = (int)$bloque['mes'];
    }
}
ok(in_array(1, $mesesVistos, true) && in_array(2, $mesesVistos, true) && in_array(3, $mesesVistos, true), 'Meses ene/feb/mar');

try {
    $planes->aprobar($planId, 1);
    ok(false, 'Re-aprobar no debe pasar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Aprobacion idempotente rechazada');
}

try {
    $planes->enviarRevision($planId);
    ok(false, 'No enviar a revision un aprobado');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Transicion invalida desde APROBADO');
}

echo "\n== Un plan por año e historico ==\n";
try {
    $planes->crear(['anio' => $anioPrueba], 1);
    ok(false, 'No duplicar año');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Año duplicado 409');
}

$plan2026 = $db->fetch("SELECT plan_anual_id, estado FROM planes_anuales WHERE anio = 2026");
ok($plan2026 !== null, 'Plan 2026 historico se conserva');
ok($plan2026['estado'] === 'APROBADO', 'Plan 2026 sigue APROBADO');

$lista = $planes->listar(1, 20, null, null);
$anios = array_map(static fn (array $p): int => (int)$p['anio'], $lista['items']);
ok(in_array(2026, $anios, true) && in_array(2027, $anios, true), 'Listado muestra 2026 y 2027');

try {
    $nuevo = $planes->crear(['anio' => 2028], 1);
    $planes->aprobar((int)$nuevo['plan_anual_id'], 1);
    ok(false, 'BORRADOR no salta a APROBADO');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'BORRADOR a APROBADO rechazado');
    borrarPlanAnio($db, 2028);
}

echo "\n== Limpieza ==\n";
borrarPlanAnio($db, $anioPrueba);
foreach ([$persona1, $persona2] as $pid) {
    $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
    foreach ($asigs as $a) {
        $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [(int)$a['asignacion_id']]);
        $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [(int)$a['asignacion_id']]);
    }
    $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
    $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
}
foreach ($capsCreadas as $cap) {
    $ref = $db->fetch(
        'SELECT plan_detalle_id FROM plan_anual_detalle WHERE capacitacion_id = ? LIMIT 1',
        [(int)$cap['capacitacion_id']]
    );
    $asig = $db->fetch(
        'SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id = ? LIMIT 1',
        [(int)$cap['capacitacion_id']]
    );
    if ($ref === null && $asig === null) {
        $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [(int)$cap['capacitacion_id']]);
    }
}

echo "\nPruebas de plan anual OK.\n";
