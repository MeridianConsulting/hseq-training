<?php

declare(strict_types=1);

/**
 * Pruebas de notas de evaluación (escala 0-5) y eficacia por tema.
 * Uso: php database/probar_evaluaciones.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\DashboardRepository;
use App\Services\AsignacionService;
use App\Services\CumplimientoService;
use App\Services\PersonalService;
use App\Services\PlanAnualService;
use App\Services\SesionService;

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

function borrarPlanYSesiones(Database $db, int $anio): void
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
        $sesiones = $db->fetchAll('SELECT sesion_id FROM sesiones_capacitacion WHERE plan_detalle_id = ?', [$id]);
        foreach ($sesiones as $s) {
            $sid = (int)$s['sesion_id'];
            $cump = $db->fetchAll('SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE sesion_id = ?', [$sid]);
            foreach ($cump as $c) {
                $db->query('DELETE FROM soportes_cumplimiento WHERE cumplimiento_id = ?', [(int)$c['cumplimiento_id']]);
            }
            $db->query('DELETE FROM cumplimientos_capacitacion WHERE sesion_id = ?', [$sid]);
            $db->query('DELETE FROM sesion_participantes WHERE sesion_id = ?', [$sid]);
            $db->query('DELETE FROM sesiones_capacitacion WHERE sesion_id = ?', [$sid]);
        }
        $db->query('DELETE FROM plan_detalle_asignaciones WHERE plan_detalle_id = ?', [$id]);
        $db->query('DELETE FROM plan_anual_detalle WHERE plan_detalle_id = ?', [$id]);
    }
    $db->query('DELETE FROM planes_anuales WHERE plan_anual_id = ?', [$planId]);
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

function eficaciaDe(DashboardRepository $dash, int $capId, string $desde, string $hasta): ?array
{
    $temas = $dash->eficaciaPorTema(['desde' => $desde, 'hasta' => $hasta]);
    foreach ($temas as $tema) {
        if ((int)$tema['capacitacion_id'] === $capId) {
            return $tema;
        }
    }

    return null;
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$planes = new PlanAnualService();
$sesiones = new SesionService();
$cumplimientos = new CumplimientoService();
$dashRepo = new DashboardRepository();

$anio = 2033;
$fecha = '2033-03-15';
$desde = '2033-01-01';
$hasta = '2033-12-31';
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$stamp = date('YmdHis');
$docs = [];
for ($i = 1; $i <= 9; $i++) {
    $docs[] = '900077' . substr($stamp, -2) . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
}

echo "== Limpieza previa ==\n";
borrarPlanYSesiones($db, $anio);
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);

$presencial = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'PRESENCIAL' AND activo = 1 LIMIT 1");
$ubicacion = $db->fetch('SELECT ubicacion_id FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($presencial !== null && $ubicacion !== null && $proveedor !== null, 'Catálogos de sesión disponibles');

$cargos = $personal->cargos();
ok(count($cargos) >= 1, 'Hay cargos corporativos');
$cargoId = (int)$cargos[0]['cargo_id'];

$codigoEval = 'EVL-' . $stamp;
$codigoSin = 'EVS-' . $stamp;
$capEval = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoEval,
    'nombre' => 'Evaluación 0-5 (prueba)',
    'objetivo' => 'Prueba de notas y eficacia',
    'duracion_estimada_horas' => 8,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
    'evaluacion' => 1,
    'nota_minima' => 3.50,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
$capSin = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoSin,
    'nombre' => 'Sin evaluación (prueba)',
    'objetivo' => 'No exige nota',
    'duracion_estimada_horas' => 4,
    'criticidad' => 'BAJA',
    'estado' => 'ACTIVA',
    'evaluacion' => 0,
    'nota_minima' => 0,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
ok($capEval > 0 && $capSin > 0, 'Capacitaciones de prueba creadas');

$personaIds = [];
$asigEval = [];
for ($i = 0; $i < 8; $i++) {
    $creada = $personal->crear([
        'numero_documento' => $docs[$i],
        'nombre_completo' => 'Eval Prueba ' . ($i + 1),
        'correo' => 'eval' . $stamp . ($i + 1) . '@hseq.test',
        'cargo_id' => $cargoId,
        'proyecto' => 'HSEQ-EVL-' . $stamp,
        'fecha_ingreso' => '2026-01-15',
    ]);
    $personaIds[] = (int)$creada['persona_id'];
    $asig = $asignaciones->crear([
        'persona_id_ext' => (int)$creada['persona_id'],
        'capacitacion_id' => $capEval,
        'fecha_limite_cumplimiento' => '2033-12-31',
    ], 1);
    $asigEval[] = (int)$asig['asignacion_id'];
}

$sinPersona = $personal->crear([
    'numero_documento' => $docs[8],
    'nombre_completo' => 'Eval Sin Nota',
    'correo' => 'evalsin' . $stamp . '@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'HSEQ-EVL-' . $stamp,
    'fecha_ingreso' => '2026-01-15',
]);
$asigSin = (int)$asignaciones->crear([
    'persona_id_ext' => (int)$sinPersona['persona_id'],
    'capacitacion_id' => $capSin,
    'fecha_limite_cumplimiento' => '2033-12-31',
], 1)['asignacion_id'];

$plan = $planes->crear(['anio' => $anio], 1);
$planId = (int)$plan['plan_anual_id'];
$todas = array_merge($asigEval, [$asigSin]);
$incluir = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => $todas,
    'mes_programado' => 3,
]);
$detalleEval = null;
$detalleSin = null;
foreach ($incluir['items'] as $item) {
    if ((int)$item['capacitacion_id'] === $capEval) {
        $detalleEval = (int)$item['plan_detalle_id'];
    }
    if ((int)$item['capacitacion_id'] === $capSin) {
        $detalleSin = (int)$item['plan_detalle_id'];
    }
}
ok($detalleEval !== null && $detalleSin !== null, 'Detalles de plan creados');
$planes->enviarRevision($planId);
$planes->aprobar($planId, 1);

$base = [
    'fecha' => $fecha,
    'hora' => '08:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
];
$sesionEval = $sesiones->crear(array_merge($base, [
    'plan_detalle_id' => $detalleEval,
    'asignacion_ids' => $asigEval,
    'cupo_maximo' => 8,
]), 1);
$sesionSin = $sesiones->crear(array_merge($base, [
    'plan_detalle_id' => $detalleSin,
    'asignacion_ids' => [$asigSin],
    'cupo_maximo' => 2,
]), 1);
$sesionEvalId = (int)$sesionEval['sesion_id'];
$sesionSinId = (int)$sesionSin['sesion_id'];

$itemsEval = [];
foreach ($asigEval as $id) {
    $itemsEval[] = ['asignacion_id' => $id, 'estado_asistencia' => 'ASISTIO'];
}
$sesiones->guardarAsistencia($sesionEvalId, ['items' => $itemsEval], 1);
$sesiones->guardarAsistencia($sesionSinId, ['items' => [
    ['asignacion_id' => $asigSin, 'estado_asistencia' => 'ASISTIO'],
]], 1);

echo "\n== Sin evaluación ==\n";
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $asigSin,
        'sesion_id' => $sesionSinId,
        'fecha_realizacion' => $fecha,
        'resultado' => 'APROBADO',
        'horas_efectivas' => 4,
        'nota_evaluacion' => 4.2,
    ], 1),
    'Nota rechazada si la capacitación no requiere evaluación'
);
$sinNota = $cumplimientos->registrar([
    'asignacion_id' => $asigSin,
    'sesion_id' => $sesionSinId,
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 4,
], 1);
ok((string)$sinNota['resultado'] === 'APROBADO', 'Sin evaluación cierra sin nota');
ok($sinNota['nota_evaluacion'] === null, 'Sin evaluación no guarda nota');
ok($sinNota['requiere_evaluacion'] === false, 'requiere_evaluacion = false');

echo "\n== Con evaluación: individual ==\n";
$preview = $cumplimientos->previsualizar($sesionEvalId, [$asigEval[0]], $fecha);
ok(!empty($preview['items'][0]['requiere_evaluacion']), 'Preview marca requiere_evaluacion');
ok((float)$preview['items'][0]['nota_minima'] === 3.5, 'Preview nota mínima 3.50');

esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $asigEval[0],
        'sesion_id' => $sesionEvalId,
        'fecha_realizacion' => $fecha,
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
    ], 1),
    'Sin nota no cierra'
);
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $asigEval[0],
        'sesion_id' => $sesionEvalId,
        'fecha_realizacion' => $fecha,
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
        'nota_evaluacion' => 'ABC',
    ], 1),
    'Nota no numérica'
);
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $asigEval[0],
        'sesion_id' => $sesionEvalId,
        'fecha_realizacion' => $fecha,
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
        'nota_evaluacion' => 5.01,
    ], 1),
    'Nota > 5'
);
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $asigEval[0],
        'sesion_id' => $sesionEvalId,
        'fecha_realizacion' => $fecha,
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
        'nota_evaluacion' => -10,
    ], 1),
    'Nota negativa'
);

$okAlta = $cumplimientos->registrar([
    'asignacion_id' => $asigEval[0],
    'sesion_id' => $sesionEvalId,
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'nota_evaluacion' => 4.20,
], 1);
ok((string)$okAlta['resultado'] === 'APROBADO', '4.20 cierra APROBADO');
ok((float)$okAlta['nota_evaluacion'] === 4.2, 'Nota 4.20 persistida');
ok($okAlta['evaluacion_aprobada'] === true, '4.20 → Aprobado');

$igual = $cumplimientos->registrar([
    'asignacion_id' => $asigEval[1],
    'sesion_id' => $sesionEvalId,
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'nota_evaluacion' => 3.50,
], 1);
ok($igual['evaluacion_aprobada'] === true, '3.50 = mínima → Aprobado');

esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $asigEval[2],
        'sesion_id' => $sesionEvalId,
        'fecha_realizacion' => $fecha,
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
        'nota_evaluacion' => 3.00,
    ], 1),
    '3.00 no cierra (bajo la mínima)'
);

$reprobado = $cumplimientos->registrarEvaluaciones([
    'sesion_id' => $sesionEvalId,
    'items' => [['asignacion_id' => $asigEval[2], 'nota' => 3.00]],
], 1);
ok((int)$reprobado['procesados'] === 1, 'Evaluación reprobada registrada');
ok((float)$reprobado['items'][0]['nota_evaluacion'] === 3.0, 'Nota 3.00 guardada');
ok($reprobado['items'][0]['evaluacion_aprobada'] === false, '3.00 → No aprobado');
ok((string)$reprobado['items'][0]['resultado'] !== 'APROBADO', 'Reprobado no cierra cumplimiento');

$otraVez = $cumplimientos->registrarEvaluaciones([
    'sesion_id' => $sesionEvalId,
    'items' => [['asignacion_id' => $asigEval[2], 'nota' => 3.20]],
], 1);
ok((float)$otraVez['items'][0]['nota_evaluacion'] === 3.2, 'Segundo POST actualiza, no duplica');
$unicos = $db->fetch(
    'SELECT COUNT(*) AS t FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$asigEval[2]]
);
ok((int)$unicos['t'] === 1, 'Una sola fila por asignación');

echo "\n== Masivo con notas ==\n";
$lote = array_slice($asigEval, 3, 5);
$notasLote = [
    $lote[0] => 4.00,
    $lote[1] => 4.50,
    $lote[2] => 3.50,
    $lote[3] => 3.00,
    $lote[4] => 5.00,
];
$masivo = $cumplimientos->registrarMasivo([
    'sesion_id' => $sesionEvalId,
    'asignacion_ids' => $lote,
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'notas' => $notasLote,
], 1);
ok((int)$masivo['procesados'] === 5, 'Masivo procesó 5');
$cerrados = 0;
foreach ($masivo['items'] as $item) {
    if ((string)$item['resultado'] === 'APROBADO') {
        $cerrados++;
    }
}
ok($cerrados === 4, 'Masivo cierra 4 (el 3.00 queda borrador)');
$filaFallo = $db->fetch(
    'SELECT resultado, nota_evaluacion FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$lote[3]]
);
ok((float)$filaFallo['nota_evaluacion'] === 3.0, 'Masivo guarda nota reprobada');
ok((string)$filaFallo['resultado'] !== 'APROBADO', 'Masivo no cierra al reprobado');

echo "\n== Dashboard eficacia ==\n";
$eficacia = eficaciaDe($dashRepo, $capEval, $desde, $hasta);
ok($eficacia !== null, 'El tema aparece en eficacia por tema');
ok((int)$eficacia['evaluaciones'] === 8, '8 notas entran al promedio (sin nulos)');
ok(abs((float)$eficacia['promedio'] - 3.86) < 0.02, 'Promedio ≈ 3.86 got=' . ($eficacia['promedio'] ?? 'null'));

$editado = $cumplimientos->actualizar((int)$okAlta['cumplimiento_id'], [
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'nota_evaluacion' => 5.00,
], 1);
ok((float)$editado['nota_evaluacion'] === 5.0, 'PUT corrige la nota');
$eficacia2 = eficaciaDe($dashRepo, $capEval, $desde, $hasta);
ok((float)$eficacia2['promedio'] > (float)$eficacia['promedio'], 'Al cambiar la nota el AVG del Dashboard sube');

echo "\n== Limpieza final ==\n";
borrarPlanYSesiones($db, $anio);
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
$db->query('DELETE FROM capacitaciones WHERE capacitacion_id IN (?, ?)', [$capEval, $capSin]);
ok(true, 'Limpieza final');

echo "\nTodas las pruebas de evaluaciones OK.\n";
