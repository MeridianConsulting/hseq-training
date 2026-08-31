<?php

declare(strict_types=1);

/**
 * Pruebas de asistencia por sesión y reprogramación.
 * Uso: php database/probar_asistencia.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\DashboardRepository;
use App\Services\AsignacionService;
use App\Services\DashboardService;
use App\Services\PersonalService;
use App\Services\PlanAnualService;
use App\Services\SesionService;

Env::load(BASE_PATH);

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

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$planes = new PlanAnualService();
$sesiones = new SesionService();
$dashRepo = new DashboardRepository();
$periodos = new DashboardService();

$anioPrueba = 2030;
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$docs = [];
for ($i = 1; $i <= 16; $i++) {
    $docs[] = '90009901' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
}

echo "== Limpieza previa año {$anioPrueba} ==\n";
borrarPlanYSesiones($db, $anioPrueba);
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);

$presencial = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'PRESENCIAL' AND activo = 1 LIMIT 1");
$ubicacion = $db->fetch('SELECT ubicacion_id FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($presencial !== null && $ubicacion !== null && $proveedor !== null, 'Catálogos de sesión disponibles');

$cargos = $personal->cargos();
ok(count($cargos) >= 1, 'Hay cargos corporativos');
$cargoId = (int)$cargos[0]['cargo_id'];

$codigoCap = 'ASIS-PRU-' . date('YmdHis');
$capId = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoCap,
    'nombre' => 'Trabajo Seguro en Alturas (prueba asistencia)',
    'objetivo' => 'Prueba de control de asistencia',
    'duracion_estimada_horas' => 8,
    'criticidad' => 'ALTA',
    'estado' => 'ACTIVA',
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
ok($capId > 0, 'Capacitación de prueba creada');

$asignacionIds = [];
for ($i = 0; $i < 16; $i++) {
    $creada = $personal->crear([
        'numero_documento' => $docs[$i],
        'nombre_completo' => 'Prueba Asistencia ' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
        'correo' => 'asistencia' . ($i + 1) . '@hseq.test',
        'cargo_id' => $cargoId,
        'proyecto' => 'HSEQ-ASIS-2030',
        'fecha_ingreso' => '2026-01-15',
    ]);
    $asig = $asignaciones->crear([
        'persona_id_ext' => (int)$creada['persona_id'],
        'capacitacion_id' => $capId,
        'fecha_limite_cumplimiento' => '2030-12-31',
    ], 0);
    $asignacionIds[] = (int)$asig['asignacion_id'];
}
ok(count($asignacionIds) === 16, '16 asignaciones creadas');

$plan = $planes->crear(['anio' => $anioPrueba], 1);
$planId = (int)$plan['plan_anual_id'];
$incluir = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => $asignacionIds,
    'mes_programado' => 9,
]);
$detalleId = (int)$incluir['items'][0]['plan_detalle_id'];
$planes->enviarRevision($planId);
$planes->aprobar($planId, 1);

$baseSesion = [
    'plan_detalle_id' => $detalleId,
    'fecha' => '2030-09-15',
    'hora' => '08:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 15,
];

$quince = array_slice($asignacionIds, 0, 15);
$sesion = $sesiones->crear(array_merge($baseSesion, ['asignacion_ids' => $quince]), 1);
$sesionId = (int)$sesion['sesion_id'];
ok((int)$sesion['convocados'] === 15, '15 convocados');
ok(isset($sesion['resumen']), 'Detalle incluye resumen');
ok((int)$sesion['resumen']['pendientes'] === 15, '15 pendientes al convocar');

echo "\n== 1. Sesión sin convocados ==\n";
$vacia = $sesiones->crear(array_merge($baseSesion, [
    'fecha' => '2030-09-16',
    'cupo_maximo' => 5,
    'asignacion_ids' => [],
]), 1);
esperaRechazo(
    static fn () => $sesiones->guardarAsistencia((int)$vacia['sesion_id'], [
        'items' => [['asignacion_id' => $asignacionIds[15], 'estado_asistencia' => 'ASISTIO']],
    ], 1),
    'Sin convocados no registra asistencia'
);

echo "\n== 2. Ausencia sin razón ==\n";
$msgAusencia = esperaRechazo(
    static fn () => $sesiones->guardarAsistencia($sesionId, [
        'items' => [
            ['asignacion_id' => $quince[0], 'estado_asistencia' => 'AUSENTE', 'motivo_ausencia' => ''],
        ],
    ], 1),
    'Ausente sin razón'
);
ok($msgAusencia === 'Debe registrar la razón de ausencia.', $msgAusencia);

echo "\n== 3. Trabajador no convocado ==\n";
esperaRechazo(
    static fn () => $sesiones->guardarAsistencia($sesionId, [
        'items' => [
            ['asignacion_id' => $asignacionIds[15], 'estado_asistencia' => 'ASISTIO'],
        ],
    ], 1),
    'No convocado rechazado'
);

echo "\n== 4. Lote de 15: 12 asistió, 1 tarde, 2 ausentes ==\n";
$items = [];
for ($i = 0; $i < 12; $i++) {
    $items[] = ['asignacion_id' => $quince[$i], 'estado_asistencia' => 'ASISTIO'];
}
$items[] = ['asignacion_id' => $quince[12], 'estado_asistencia' => 'TARDE', 'observacion' => 'Ingreso 20 minutos tarde'];
$items[] = [
    'asignacion_id' => $quince[13],
    'estado_asistencia' => 'AUSENTE',
    'motivo_ausencia' => 'Incapacidad',
];
$items[] = [
    'asignacion_id' => $quince[14],
    'estado_asistencia' => 'AUSENTE',
    'motivo_ausencia' => 'Calamidad doméstica',
    'observacion' => 'Reportó al líder HSEQ',
];

$periodo = $periodos->periodo(['tipo' => 'anual', 'anio' => $anioPrueba]);
$ejecutadoAntes = $dashRepo->ejecutado($periodo, 'general');

$guardado = $sesiones->guardarAsistencia($sesionId, ['items' => $items], 1);
$r = $guardado['resumen'];
ok((int)$r['convocados'] === 15, 'Convocados 15');
ok((int)$r['asistieron'] === 12, 'Asistieron 12 got=' . $r['asistieron']);
ok((int)$r['tarde'] === 1, 'Tarde 1');
ok((int)$r['ausentes'] === 2, 'Ausentes 2');
ok((int)$r['pendientes'] === 0, 'Pendientes 0');

$tarde = null;
$ausentes = [];
foreach ($guardado['participantes'] as $p) {
    if ((int)$p['asignacion_id'] === $quince[12]) {
        $tarde = $p;
    }
    if ($p['estado_asistencia'] === 'AUSENTE') {
        $ausentes[] = $p;
    }
}
ok($tarde !== null && $tarde['estado_asistencia'] === 'TARDE', 'TARDE diferenciado');
ok(count($ausentes) === 2, '2 ausentes identificados');
ok($ausentes[0]['motivo_ausencia'] !== null && $ausentes[0]['motivo_ausencia'] !== '', 'Razón de ausencia guardada');

$cumpAsistio = $db->fetch(
    'SELECT COUNT(*) AS t FROM cumplimientos_capacitacion WHERE sesion_id = ?',
    [$sesionId]
);
ok((int)($cumpAsistio['t'] ?? 0) === 13, '13 cumplimientos (12+1 tarde), ausentes no cuentan got=' . ($cumpAsistio['t'] ?? 0));

$cumpAusente = $db->fetch(
    'SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ? LIMIT 1',
    [$quince[13]]
);
ok($cumpAusente === null, 'Ausente no genera cumplimiento');

$ejecutadoDespues = $dashRepo->ejecutado($periodo, 'general');
ok(
    $ejecutadoDespues === $ejecutadoAntes + 13,
    "Dashboard ejecutado +13 sin tocar consultas ({$ejecutadoAntes} -> {$ejecutadoDespues})"
);

echo "\n== 5. Idempotencia ==\n";
$otraVez = $sesiones->guardarAsistencia($sesionId, ['items' => $items], 1);
ok((int)$otraVez['resumen']['asistieron'] === 12, 'Segundo guardado no duplica asistencia');
$partCount = $db->fetch(
    'SELECT COUNT(*) AS t FROM sesion_participantes WHERE sesion_id = ?',
    [$sesionId]
);
ok((int)$partCount['t'] === 15, 'Sigue habiendo 15 filas de participante');
$cumpCount = $db->fetch(
    'SELECT COUNT(*) AS t FROM cumplimientos_capacitacion WHERE sesion_id = ?',
    [$sesionId]
);
ok((int)$cumpCount['t'] === 13, 'Sigue habiendo 13 cumplimientos');

echo "\n== 6. Corrección Ausente -> Asistió ==\n";
$corregido = $sesiones->guardarAsistencia($sesionId, [
    'items' => [[
        'asignacion_id' => $quince[14],
        'estado_asistencia' => 'ASISTIO',
    ]],
], 1);
ok((int)$corregido['resumen']['ausentes'] === 1, 'Queda 1 ausente tras corrección');
ok((int)$corregido['resumen']['asistieron'] === 13, 'Asistieron 13');
$cumpCorr = $db->fetch(
    'SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ? AND sesion_id = ?',
    [$quince[14], $sesionId]
);
ok($cumpCorr !== null, 'Corrección a ASISTIO crea cumplimiento');

$sesiones->guardarAsistencia($sesionId, [
    'items' => [[
        'asignacion_id' => $quince[14],
        'estado_asistencia' => 'AUSENTE',
        'motivo_ausencia' => 'Calamidad doméstica',
    ]],
], 1);
$cumpVolvio = $db->fetch(
    'SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ? AND sesion_id = ?',
    [$quince[14], $sesionId]
);
ok($cumpVolvio === null, 'Volver a ausente borra cumplimiento de esta sesión');

echo "\n== 7. Reprogramación ==\n";
$destino = $sesiones->crear(array_merge($baseSesion, [
    'fecha' => '2030-09-22',
    'cupo_maximo' => 10,
    'asignacion_ids' => [],
]), 1);
$destinoId = (int)$destino['sesion_id'];

esperaRechazo(
    static fn () => $sesiones->reprogramar($destinoId, [
        'origen_sesion_id' => $sesionId,
        'asignacion_ids' => [$quince[0]],
    ], 1),
    'No reprograma a quien asistió'
);

$idsAusentes = [$quince[13], $quince[14]];
$reprog = $sesiones->reprogramar($destinoId, [
    'origen_sesion_id' => $sesionId,
    'asignacion_ids' => $idsAusentes,
], 1);
ok((int)$reprog['reprogramacion']['seleccionados'] === 2, 'Seleccionados 2');
ok((int)$reprog['reprogramacion']['reprogramados'] === 2, 'Reprogramados 2');
ok((int)$reprog['reprogramacion']['errores'] === 0, 'Errores 0');
ok((int)$reprog['convocados'] === 2, 'Destino tiene 2 CONVOCADO');

foreach ($reprog['participantes'] as $p) {
    ok($p['estado_asistencia'] === 'CONVOCADO', 'Nueva sesión queda pendiente');
}

$origen = $sesiones->ver($sesionId);
$origenAusentes = 0;
foreach ($origen['participantes'] as $p) {
    if (in_array((int)$p['asignacion_id'], $idsAusentes, true)) {
        ok($p['estado_asistencia'] === 'AUSENTE', 'Origen conserva Ausente');
        $origenAusentes++;
    }
}
ok($origenAusentes === 2, 'Las 2 ausencias originales siguen');

$mismaAsig = $db->fetchAll(
    'SELECT sesion_id FROM sesion_participantes WHERE asignacion_id = ?',
    [$quince[13]]
);
ok(count($mismaAsig) === 2, 'Misma asignación en dos sesiones, sin duplicar asignación');

$hist = $sesiones->historialPersona(
    (int)$db->fetch(
        'SELECT persona_id_ext FROM asignaciones_capacitacion WHERE asignacion_id = ?',
        [$quince[13]]
    )['persona_id_ext']
);
$estadosHist = array_map(static fn (array $i): string => (string)$i['estado_asistencia'], $hist);
ok(in_array('AUSENTE', $estadosHist, true), 'Historial conserva Ausente');
ok(in_array('CONVOCADO', $estadosHist, true), 'Historial muestra nueva sesión pendiente');

echo "\n== 8. Cupo insuficiente ==\n";
$chica = $sesiones->crear(array_merge($baseSesion, [
    'fecha' => '2030-09-29',
    'cupo_maximo' => 1,
    'asignacion_ids' => [],
]), 1);
$msgCupo = esperaRechazo(
    static fn () => $sesiones->reprogramar((int)$chica['sesion_id'], [
        'origen_sesion_id' => $sesionId,
        'asignacion_ids' => $idsAusentes,
    ], 1),
    'Cupo insuficiente en reprogramación'
);
ok(
    $msgCupo === 'La sesión seleccionada no tiene cupos suficientes para los trabajadores seleccionados.',
    $msgCupo
);
ok((int)$sesiones->ver((int)$chica['sesion_id'])['convocados'] === 0, 'Destino chica sigue vacía');
ok((int)$sesiones->ver($sesionId)['resumen']['ausentes'] === 2, 'Ausencias originales intactas');

echo "\n== 9. Sesión cancelada ==\n";
$db->query("UPDATE sesiones_capacitacion SET estado = 'CANCELADA' WHERE sesion_id = ?", [(int)$vacia['sesion_id']]);
esperaRechazo(
    static fn () => $sesiones->guardarAsistencia((int)$vacia['sesion_id'], [
        'items' => [['asignacion_id' => $asignacionIds[15], 'estado_asistencia' => 'ASISTIO']],
    ], 1),
    'Cancelada no admite asistencia',
    409
);
esperaRechazo(
    static fn () => $sesiones->reprogramar((int)$vacia['sesion_id'], [
        'origen_sesion_id' => $sesionId,
        'asignacion_ids' => [$quince[13]],
    ], 1),
    'No reprograma hacia cancelada',
    409
);

echo "\n== Limpieza ==\n";
borrarPlanYSesiones($db, $anioPrueba);
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
$refCap = $db->fetch('SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id = ? LIMIT 1', [$capId]);
$refSes = $db->fetch('SELECT sesion_id FROM sesiones_capacitacion WHERE capacitacion_id = ? LIMIT 1', [$capId]);
if ($refCap === null && $refSes === null) {
    $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capId]);
}

echo "\nPruebas de asistencia y reprogramación OK.\n";
