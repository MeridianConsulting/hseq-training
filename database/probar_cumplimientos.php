<?php

declare(strict_types=1);

/**
 * Pruebas de registro de cumplimiento y vencimiento automático (matriz RF-007).
 * Uso: php database/probar_cumplimientos.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\DashboardRepository;
use App\Services\AsignacionService;
use App\Services\CumplimientoService;
use App\Services\DashboardService;
use App\Services\MatrizService;
use App\Services\PersonalService;
use App\Services\PlanAnualService;
use App\Services\SesionService;
use App\Services\VencimientoService;

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

function periodicidadId(Database $db, int $cantidad, string $unidad, string $nombre): int
{
    $fila = $db->fetch(
        'SELECT periodicidad_id FROM periodicidades WHERE cantidad = ? AND unidad = ? LIMIT 1',
        [$cantidad, $unidad]
    );
    if ($fila !== null) {
        return (int)$fila['periodicidad_id'];
    }

    return (int)$db->insert('periodicidades', [
        'nombre' => $nombre,
        'cantidad' => $cantidad,
        'unidad' => $unidad,
        'activo' => 1,
    ]);
}

function estadoDe(Database $db, int $asignacionId): string
{
    $fila = $db->fetch(
        'SELECT estado_calculado FROM vw_estado_asignaciones WHERE asignacion_id = ? LIMIT 1',
        [$asignacionId]
    );

    return (string)($fila['estado_calculado'] ?? '');
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$matriz = new MatrizService();
$planes = new PlanAnualService();
$sesiones = new SesionService();
$cumplimientos = new CumplimientoService();
$dashRepo = new DashboardRepository();
$periodos = new DashboardService();

$anioPrueba = 2031;
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');

$docs12 = [];
for ($i = 1; $i <= 12; $i++) {
    $docs12[] = '90008801' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
}
$docsExtra = ['9000880201', '9000880301', '9000880302', '9000880401', '9000880501'];
$docs = array_merge($docs12, $docsExtra);

echo "== Cálculo centralizado ==\n";
ok(
    VencimientoService::calcularFechaVencimiento('2026-09-15', 6, 'MESES') === '2027-03-15',
    '15/09/2026 + 6 meses = 15/03/2027'
);
ok(VencimientoService::calcularFechaVencimiento('2026-09-15', 12, 'MESES') === '2027-09-15', '12 meses');
ok(VencimientoService::calcularFechaVencimiento('2026-09-15', 0, 'MESES') === null, 'cantidad 0 → null');
ok(VencimientoService::calcularFechaVencimiento('2026-09-15', 1, '') === null, 'unidad vacía → null');

echo "\n== Limpieza previa año {$anioPrueba} ==\n";
borrarPlanYSesiones($db, $anioPrueba);
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);

$presencial = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'PRESENCIAL' AND activo = 1 LIMIT 1");
$ubicacion = $db->fetch('SELECT ubicacion_id FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($presencial !== null && $ubicacion !== null && $proveedor !== null, 'Catálogos de sesión disponibles');

$cargos = $personal->cargos();
ok(count($cargos) >= 1, 'Hay cargos corporativos');
$cargoId = (int)$cargos[0]['cargo_id'];

$per12 = periodicidadId($db, 12, 'MESES', 'CUMP-PRU-12M');
$per6 = periodicidadId($db, 6, 'MESES', 'CUMP-PRU-6M');
$vig = $db->fetch("SELECT vigencia_id FROM vigencias WHERE cantidad = 24 AND unidad = 'MESES' LIMIT 1");
if ($vig === null) {
    $vig = $db->fetch("SELECT vigencia_id FROM vigencias WHERE cantidad = 2 AND unidad = 'ANIOS' LIMIT 1");
}
$vigId = $vig !== null ? (int)$vig['vigencia_id'] : null;

$stamp = date('YmdHis');
$codigo12 = 'CUMP-12-' . $stamp;
$cap12 = (int)$db->insert('capacitaciones', [
    'codigo' => $codigo12,
    'nombre' => 'Cumplimiento anual (prueba)',
    'objetivo' => 'Prueba de vencimiento 12 meses',
    'duracion_estimada_horas' => 8,
    'criticidad' => 'ALTA',
    'estado' => 'ACTIVA',
    'periodicidad_default_id' => $per12,
    'vigencia_id' => $vigId,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
ok($cap12 > 0, 'Capacitación 12 meses creada');

$proyecto12 = 'HSEQ-CUMP-12';
$asignacionIds = [];
$personaIds = [];
for ($i = 0; $i < 12; $i++) {
    $creada = $personal->crear([
        'numero_documento' => $docs12[$i],
        'nombre_completo' => 'Prueba Cumplimiento ' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
        'correo' => 'cump' . ($i + 1) . '@hseq.test',
        'cargo_id' => $cargoId,
        'proyecto' => $proyecto12,
        'fecha_ingreso' => '2026-01-15',
    ]);
    $personaIds[] = (int)$creada['persona_id'];
}

$matriz->crear([
    'capacitacion_id' => $cap12,
    'cargo_id_ext' => $cargoId,
    'proyecto' => $proyecto12,
    'periodicidad_id' => $per12,
    'obligatoria' => 1,
], 1);

for ($i = 0; $i < 12; $i++) {
    $asig = $asignaciones->crear([
        'persona_id_ext' => $personaIds[$i],
        'capacitacion_id' => $cap12,
        'fecha_limite_cumplimiento' => '2031-12-31',
    ], 1);
    $asignacionIds[] = (int)$asig['asignacion_id'];
}
ok(count($asignacionIds) === 12, '12 asignaciones creadas');

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
    'fecha' => '2031-09-15',
    'hora' => '08:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 12,
];

$sesion = $sesiones->crear(array_merge($baseSesion, ['asignacion_ids' => $asignacionIds]), 1);
$sesionId = (int)$sesion['sesion_id'];
ok((int)$sesion['convocados'] === 12, '12 convocados');

$diez = array_slice($asignacionIds, 0, 10);
$individualId = $asignacionIds[10];
$ausenteId = $asignacionIds[11];

$itemsAsis = [];
foreach ($diez as $id) {
    $itemsAsis[] = ['asignacion_id' => $id, 'estado_asistencia' => 'ASISTIO'];
}
$itemsAsis[] = ['asignacion_id' => $individualId, 'estado_asistencia' => 'ASISTIO'];
$itemsAsis[] = [
    'asignacion_id' => $ausenteId,
    'estado_asistencia' => 'AUSENTE',
    'motivo_ausencia' => 'Incapacidad',
];

$periodo = $periodos->periodo(['tipo' => 'anual', 'anio' => $anioPrueba]);
$ejecutadoAntes = $dashRepo->ejecutado($periodo, 'general');

$sesiones->guardarAsistencia($sesionId, ['items' => $itemsAsis], 1);

$borrador = $db->fetch(
    'SELECT fecha_vencimiento, resultado FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$diez[0]]
);
ok($borrador !== null, 'Borrador de asistencia creado');
ok((string)$borrador['resultado'] === 'ASISTIO', 'Resultado provisional ASISTIO');
ok(
    (string)$borrador['fecha_vencimiento'] === '2032-09-15',
    'Borrador vence +12 meses de matriz (no vigencia) got=' . ($borrador['fecha_vencimiento'] ?? 'null')
);
ok($db->fetch(
    'SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$ausenteId]
) === null, 'Ausente no genera cumplimiento');

$ejecutadoTrasAsis = $dashRepo->ejecutado($periodo, 'general');
ok(
    $ejecutadoTrasAsis === $ejecutadoAntes + 11,
    "Dashboard +11 por asistencia ({$ejecutadoAntes} -> {$ejecutadoTrasAsis})"
);

echo "\n== Masivo 10 trabajadores ==\n";
$preview = $cumplimientos->previsualizar($sesionId, $diez, '2031-09-15');
ok(count($preview['items']) === 10, 'Preview 10 ítems');
ok($preview['items'][0]['fecha_vencimiento'] === '2032-09-15', 'Preview vencimiento +12 meses');
ok($preview['periodicidades_distintas'] === false, 'Una sola periodicidad en el lote');

$masivo = $cumplimientos->registrarMasivo([
    'sesion_id' => $sesionId,
    'asignacion_ids' => $diez,
    'fecha_realizacion' => '2031-09-15',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'fecha_vencimiento' => '2099-01-01',
], 1);
ok((int)$masivo['completados'] === 10, '10 cumplimientos completados');
ok((int)$masivo['errores'] === 0, 'Masivo sin errores');

foreach ($diez as $aid) {
    $fila = $db->fetch(
        'SELECT resultado, horas_efectivas, fecha_vencimiento FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
        [$aid]
    );
    ok($fila !== null && (string)$fila['resultado'] === 'APROBADO', "Asignación {$aid} APROBADO");
    ok((string)$fila['fecha_vencimiento'] === '2032-09-15', "Vence 2032-09-15 got=" . ($fila['fecha_vencimiento'] ?? 'null'));
    ok(estadoDe($db, $aid) === 'COMPLETADA', "Estado COMPLETADA {$aid}");
}

$unicos = $db->fetch(
    'SELECT COUNT(*) AS t FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$diez[0]]
);
ok((int)$unicos['t'] === 1, 'UNIQUE: una sola fila por asignación');

$ejecutadoTrasCump = $dashRepo->ejecutado($periodo, 'general');
ok(
    $ejecutadoTrasCump === $ejecutadoTrasAsis,
    "Completar no duplica Dashboard ({$ejecutadoTrasAsis} -> {$ejecutadoTrasCump})"
);

echo "\n== Individual ==\n";
$uno = $cumplimientos->registrar([
    'asignacion_id' => $individualId,
    'sesion_id' => $sesionId,
    'fecha_realizacion' => '2031-09-15',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 7.5,
], 1);
ok((string)$uno['resultado'] === 'APROBADO', 'Individual APROBADO');
ok((string)$uno['fecha_vencimiento'] === '2032-09-15', 'Individual vence +12 meses');

echo "\n== Rechazos ==\n";
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $ausenteId,
        'sesion_id' => $sesionId,
        'fecha_realizacion' => '2031-09-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
    ], 1),
    'Ausente rechazado',
    422
);

$msgDup = esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $diez[0],
        'sesion_id' => $sesionId,
        'fecha_realizacion' => '2031-09-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
    ], 1),
    'Duplicado APROBADO',
    409
);
ok($msgDup === CumplimientoService::MENSAJE_YA_REGISTRADO, 'Mensaje de duplicado');

esperaRechazo(
    static fn () => $cumplimientos->registrarMasivo([
        'sesion_id' => $sesionId,
        'asignacion_ids' => $diez,
        'fecha_realizacion' => '2031-09-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
    ], 1),
    'Masivo duplicado',
    409
);

esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $individualId,
        'sesion_id' => $sesionId,
        'fecha_realizacion' => '2031-09-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => -1,
    ], 1),
    'Horas negativas'
);
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $individualId,
        'sesion_id' => $sesionId,
        'fecha_realizacion' => '2031-09-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 'abc',
    ], 1),
    'Horas no numéricas'
);
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $individualId,
        'sesion_id' => $sesionId,
        'fecha_realizacion' => '2031-09-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => '',
    ], 1),
    'Horas vacías'
);
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $individualId,
        'sesion_id' => $sesionId,
        'fecha_realizacion' => '2031-09-15',
        'resultado' => 'NO_APROBADO',
        'horas_efectivas' => 8,
    ], 1),
    'NO_APROBADO no permitido'
);

echo "\n== PUT recálculo e ignore cliente ==\n";
$editado = $cumplimientos->actualizar((int)$uno['cumplimiento_id'], [
    'fecha_realizacion' => '2031-10-15',
    'horas_efectivas' => 6,
    'fecha_vencimiento' => '2099-12-31',
], 1);
ok((string)$editado['fecha_realizacion'] === '2031-10-15', 'PUT cambia fecha de realización');
ok((string)$editado['fecha_vencimiento'] === '2032-10-15', 'PUT recálculo +12 meses, ignora 2099');

$listado = $cumplimientos->listar(1, 50, ['persona_id' => $personaIds[0]]);
ok($listado['total'] >= 1, 'Historial GET por persona');

echo "\n== Casos extra (plan 2032) ==\n";
$anioExtra = 2032;
borrarPlanYSesiones($db, $anioExtra);

$proyecto6 = 'HSEQ-CUMP-6';
$codigo6 = 'CUMP-6-' . $stamp;
$cap6 = (int)$db->insert('capacitaciones', [
    'codigo' => $codigo6,
    'nombre' => 'Cumplimiento semestral (prueba)',
    'objetivo' => 'Prueba 6 meses',
    'duracion_estimada_horas' => 4,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
    'periodicidad_default_id' => $per6,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
$p6 = $personal->crear([
    'numero_documento' => '9000880201',
    'nombre_completo' => 'Prueba Cumplimiento 6m',
    'correo' => 'cump6m@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => $proyecto6,
    'fecha_ingreso' => '2026-01-15',
]);
$matriz->crear([
    'capacitacion_id' => $cap6,
    'cargo_id_ext' => $cargoId,
    'proyecto' => $proyecto6,
    'periodicidad_id' => $per6,
    'obligatoria' => 1,
], 1);
$asig6Id = (int)$asignaciones->crear([
    'persona_id_ext' => (int)$p6['persona_id'],
    'capacitacion_id' => $cap6,
    'fecha_limite_cumplimiento' => '2032-12-31',
], 1)['asignacion_id'];

$codigoMix = 'CUMP-MIX-' . $stamp;
$capMix = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoMix,
    'nombre' => 'Mixto 12 y 6',
    'objetivo' => 'Dos reglas',
    'duracion_estimada_horas' => 5,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
$pm12 = $personal->crear([
    'numero_documento' => '9000880301',
    'nombre_completo' => 'Mixto Doce',
    'correo' => 'mix12@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'HSEQ-CUMP-MIX12',
    'fecha_ingreso' => '2026-01-15',
]);
$pm6 = $personal->crear([
    'numero_documento' => '9000880302',
    'nombre_completo' => 'Mixto Seis',
    'correo' => 'mix6@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'HSEQ-CUMP-MIX6',
    'fecha_ingreso' => '2026-01-15',
]);
$matriz->crear([
    'capacitacion_id' => $capMix,
    'cargo_id_ext' => $cargoId,
    'proyecto' => 'HSEQ-CUMP-MIX12',
    'periodicidad_id' => $per12,
    'obligatoria' => 1,
], 1);
$matriz->crear([
    'capacitacion_id' => $capMix,
    'cargo_id_ext' => $cargoId,
    'proyecto' => 'HSEQ-CUMP-MIX6',
    'periodicidad_id' => $per6,
    'obligatoria' => 1,
], 1);
$am12Id = (int)$asignaciones->crear([
    'persona_id_ext' => (int)$pm12['persona_id'],
    'capacitacion_id' => $capMix,
    'fecha_limite_cumplimiento' => '2032-12-31',
], 1)['asignacion_id'];
$am6Id = (int)$asignaciones->crear([
    'persona_id_ext' => (int)$pm6['persona_id'],
    'capacitacion_id' => $capMix,
    'fecha_limite_cumplimiento' => '2032-12-31',
], 1)['asignacion_id'];

$codigoUna = 'CUMP-UNA-' . $stamp;
$capUna = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoUna,
    'nombre' => 'Una sola vez',
    'objetivo' => 'Sin periodicidad',
    'duracion_estimada_horas' => 2,
    'criticidad' => 'BAJA',
    'estado' => 'ACTIVA',
    'periodicidad_default_id' => null,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
$pUna = $personal->crear([
    'numero_documento' => '9000880401',
    'nombre_completo' => 'Una Vez',
    'correo' => 'unavez@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'HSEQ-CUMP-UNA',
    'fecha_ingreso' => '2026-01-15',
]);
$asigUnaId = (int)$asignaciones->crear([
    'persona_id_ext' => (int)$pUna['persona_id'],
    'capacitacion_id' => $capUna,
    'fecha_limite_cumplimiento' => '2032-12-31',
], 1)['asignacion_id'];

$planExtra = $planes->crear(['anio' => $anioExtra], 1);
$planExtraId = (int)$planExtra['plan_anual_id'];
$planes->incluirAsignaciones($planExtraId, [
    'asignacion_ids' => [$asig6Id],
    'mes_programado' => 9,
]);
$planes->incluirAsignaciones($planExtraId, [
    'asignacion_ids' => [$am12Id, $am6Id],
    'mes_programado' => 11,
]);
$planes->incluirAsignaciones($planExtraId, [
    'asignacion_ids' => [$asigUnaId],
    'mes_programado' => 12,
]);
$planes->enviarRevision($planExtraId);
$planes->aprobar($planExtraId, 1);

$planVista = $planes->ver($planExtraId);
$detallePorCap = [];
foreach ($planVista['detalles'] as $det) {
    $detallePorCap[(int)$det['capacitacion_id']] = (int)$det['plan_detalle_id'];
}
$detalle6 = $detallePorCap[$cap6];
$detalleMix = $detallePorCap[$capMix];
$detalleUna = $detallePorCap[$capUna];

$sesionBase = [
    'hora' => '09:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 5,
];

echo "\n== 6 meses 15/09/2026 → 15/03/2027 ==\n";
$sesion6 = $sesiones->crear(array_merge($sesionBase, [
    'plan_detalle_id' => $detalle6,
    'fecha' => '2032-09-01',
    'asignacion_ids' => [$asig6Id],
]), 1);
$sesiones->guardarAsistencia((int)$sesion6['sesion_id'], [
    'items' => [['asignacion_id' => $asig6Id, 'estado_asistencia' => 'ASISTIO']],
], 1);
$reg6 = $cumplimientos->registrar([
    'asignacion_id' => $asig6Id,
    'sesion_id' => (int)$sesion6['sesion_id'],
    'fecha_realizacion' => '2026-09-15',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 4,
], 1);
ok(
    (string)$reg6['fecha_vencimiento'] === '2027-03-15',
    '6 meses: 15/09/2026 → 15/03/2027 got=' . ($reg6['fecha_vencimiento'] ?? 'null')
);

echo "\n== Mixto 12 vs 6 en la misma sesión ==\n";
$sesionMix = $sesiones->crear(array_merge($sesionBase, [
    'plan_detalle_id' => $detalleMix,
    'fecha' => '2032-11-01',
    'asignacion_ids' => [$am12Id, $am6Id],
]), 1);
$sesiones->guardarAsistencia((int)$sesionMix['sesion_id'], [
    'items' => [
        ['asignacion_id' => $am12Id, 'estado_asistencia' => 'ASISTIO'],
        ['asignacion_id' => $am6Id, 'estado_asistencia' => 'ASISTIO'],
    ],
], 1);
$prevMix = $cumplimientos->previsualizar((int)$sesionMix['sesion_id'], [$am12Id, $am6Id], '2032-11-01');
ok($prevMix['periodicidades_distintas'] === true, 'Aviso de periodicidades distintas');
$masivoMix = $cumplimientos->registrarMasivo([
    'sesion_id' => (int)$sesionMix['sesion_id'],
    'asignacion_ids' => [$am12Id, $am6Id],
    'fecha_realizacion' => '2032-11-01',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 5,
], 1);
ok((int)$masivoMix['completados'] === 2, 'Mixto: 2 completados');
$v12 = $db->fetch('SELECT fecha_vencimiento FROM cumplimientos_capacitacion WHERE asignacion_id = ?', [$am12Id]);
$v6m = $db->fetch('SELECT fecha_vencimiento FROM cumplimientos_capacitacion WHERE asignacion_id = ?', [$am6Id]);
ok((string)$v12['fecha_vencimiento'] === '2033-11-01', 'Mixto 12 meses → 2033-11-01');
ok((string)$v6m['fecha_vencimiento'] === '2033-05-01', 'Mixto 6 meses → 2033-05-01');

echo "\n== Sin periodicidad → NULL ==\n";
$sesionUna = $sesiones->crear(array_merge($sesionBase, [
    'plan_detalle_id' => $detalleUna,
    'fecha' => '2032-12-01',
    'asignacion_ids' => [$asigUnaId],
]), 1);
$sesiones->guardarAsistencia((int)$sesionUna['sesion_id'], [
    'items' => [['asignacion_id' => $asigUnaId, 'estado_asistencia' => 'ASISTIO']],
], 1);
$regUna = $cumplimientos->registrar([
    'asignacion_id' => $asigUnaId,
    'sesion_id' => (int)$sesionUna['sesion_id'],
    'fecha_realizacion' => '2032-12-01',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 2,
], 1);
ok($regUna['fecha_vencimiento'] === null, 'Sin periodicidad: fecha_vencimiento NULL');
$prevUna = $cumplimientos->previsualizar((int)$sesionUna['sesion_id'], [$asigUnaId], '2032-12-01');
ok($prevUna['items'][0]['etiqueta_vencimiento'] === 'Sin vencimiento', 'Preview dice Sin vencimiento');

echo "\n== Limpieza de datos de prueba ==\n";
borrarPlanYSesiones($db, $anioPrueba);
borrarPlanYSesiones($db, $anioExtra);
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
foreach ([$cap12, $cap6, $capMix, $capUna] as $cid) {
    $db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$cid]);
    $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$cid]);
}
ok(true, 'Limpieza final');

echo "\nTodas las pruebas de cumplimiento OK.\n";
