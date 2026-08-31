<?php

declare(strict_types=1);

/**
 * Pruebas de sesiones: cupo, plan aprobado, modalidad, duplicados, RF-001.
 * Uso: php database/probar_sesiones.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\DashboardRepository;
use App\Services\AsignacionService;
use App\Services\CronogramaService;
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
            $db->query('DELETE FROM sesion_participantes WHERE sesion_id = ?', [$sid]);
            $db->query('DELETE FROM sesiones_capacitacion WHERE sesion_id = ?', [$sid]);
        }
        $db->query('DELETE FROM plan_detalle_asignaciones WHERE plan_detalle_id = ?', [$id]);
        $db->query('DELETE FROM plan_anual_detalle WHERE plan_detalle_id = ?', [$id]);
    }
    $db->query('DELETE FROM planes_anuales WHERE plan_anual_id = ?', [$planId]);
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$planes = new PlanAnualService();
$sesiones = new SesionService();
$dashRepo = new DashboardRepository();
$periodos = new DashboardService();
$cronograma = new CronogramaService();

$anioPrueba = 2029;
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$docs = [];
for ($i = 1; $i <= 16; $i++) {
    $docs[] = '90008801' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
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
            $db->query('DELETE FROM sesion_participantes WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [$aid]);
        }
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
    }
}

echo "== Limpieza previa año {$anioPrueba} ==\n";
borrarPlanYSesiones($db, $anioPrueba);
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);

$presencial = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'PRESENCIAL' AND activo = 1 LIMIT 1");
$virtual = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'VIRTUAL' AND activo = 1 LIMIT 1");
$ubicacion = $db->fetch('SELECT ubicacion_id, nombre FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id, nombre FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($presencial !== null && $virtual !== null, 'Hay modalidades PRESENCIAL y VIRTUAL');
ok($ubicacion !== null, 'Hay ubicación activa');
ok($proveedor !== null, 'Hay proveedor activo');

$cargos = $personal->cargos();
ok(count($cargos) >= 1, 'Hay cargos corporativos');
$cargoId = (int)$cargos[0]['cargo_id'];

$codigoCap = 'SES-PRU-' . date('YmdHis');
$capId = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoCap,
    'nombre' => 'Trabajo Seguro en Alturas (prueba sesiones)',
    'objetivo' => 'Prueba de sesiones y cupo',
    'duracion_estimada_horas' => 8,
    'criticidad' => 'ALTA',
    'estado' => 'ACTIVA',
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
ok($capId > 0, 'Capacitación de prueba creada');

$otraCap = $db->fetch(
    'SELECT capacitacion_id FROM capacitaciones WHERE capacitacion_id <> ? LIMIT 1',
    [$capId]
);

$personaIds = [];
$asignacionIds = [];
for ($i = 0; $i < 16; $i++) {
    $creada = $personal->crear([
        'numero_documento' => $docs[$i],
        'nombre_completo' => 'Prueba Sesion ' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT),
        'correo' => 'sesion' . ($i + 1) . '@hseq.test',
        'cargo_id' => $cargoId,
        'proyecto' => 'HSEQ-SES-2029',
        'fecha_ingreso' => '2026-01-15',
    ]);
    $pid = (int)$creada['persona_id'];
    $personaIds[] = $pid;
    $asig = $asignaciones->crear([
        'persona_id_ext' => $pid,
        'capacitacion_id' => $capId,
        'fecha_limite_cumplimiento' => '2029-12-31',
    ], 0);
    $asignacionIds[] = (int)$asig['asignacion_id'];
}
ok(count($asignacionIds) === 16, '16 asignaciones creadas');

echo "\n== Plan no aprobado ==\n";
$plan = $planes->crear(['anio' => $anioPrueba], 1);
$planId = (int)$plan['plan_anual_id'];
$incluir = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => $asignacionIds,
    'mes_programado' => 3,
]);
ok($incluir['creadas'] === 16, '16 asignaciones en el plan borrador');
$detalleId = (int)$incluir['items'][0]['plan_detalle_id'];

$baseSesion = [
    'plan_detalle_id' => $detalleId,
    'fecha' => '2029-03-15',
    'hora' => '08:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 15,
];

esperaRechazo(
    static fn () => $sesiones->crear($baseSesion, 1),
    'Borrador no permite crear sesión'
);

$planes->enviarRevision($planId);
esperaRechazo(
    static fn () => $sesiones->crear($baseSesion, 1),
    'En revisión no permite crear sesión'
);

echo "\n== Aprobar y crear sesión con 15 ==\n";
$planes->aprobar($planId, 1);
$periodo = $periodos->periodo(['tipo' => 'anual', 'anio' => $anioPrueba]);
$programadoAntes = $dashRepo->programado($periodo, 'general');

$quince = array_slice($asignacionIds, 0, 15);
$sesion = $sesiones->crear(array_merge($baseSesion, [
    'asignacion_ids' => $quince,
]), 1);
ok((int)$sesion['convocados'] === 15, 'Convocados 15');
ok((int)$sesion['cupo_maximo'] === 15, 'Cupo 15');
ok((int)$sesion['disponibles'] === 0, 'Disponibles 0');
ok($sesion['cupo_completo'] === true, 'Cupo completo');
ok($sesion['fecha'] === '2029-03-15', 'Fecha 2029-03-15');
ok($sesion['hora'] === '08:00', 'Hora 08:00');
$sesionId = (int)$sesion['sesion_id'];

echo "\n== Cupo: trabajador 16, masivo, duplicado ==\n";
$msg16 = esperaRechazo(
    static fn () => $sesiones->convocar($sesionId, ['asignacion_ids' => [$asignacionIds[15]]], 1),
    'Rechaza el trabajador 16',
    422
);
ok(str_contains($msg16, '15') || str_contains(mb_strtolower($msg16), 'cupo'), 'Mensaje de cupo alcanzado');

esperaRechazo(
    static fn () => $sesiones->convocar($sesionId, ['asignacion_ids' => [$quince[0]]], 1),
    'Duplicado rechazado',
    409
);

$despuesDup = $sesiones->ver($sesionId);
ok((int)$despuesDup['convocados'] === 15, 'Sigue en 15/15 tras duplicado');

echo "\n== Retiro y reducción de cupo ==\n";
$retirada = $sesiones->retirar($sesionId, $quince[0]);
ok((int)$retirada['convocados'] === 14, 'Tras retiro 14/15');
ok((int)$retirada['disponibles'] === 1, '1 disponible');

esperaRechazo(
    static fn () => $sesiones->actualizar($sesionId, array_merge($baseSesion, ['cupo_maximo' => 10])),
    'No reduce cupo por debajo de convocados'
);

$ampliada = $sesiones->actualizar($sesionId, array_merge($baseSesion, ['cupo_maximo' => 20]));
ok((int)$ampliada['cupo_maximo'] === 20 && (int)$ampliada['disponibles'] === 6, 'Aumenta cupo a 20');

$sesiones->actualizar($sesionId, $baseSesion);
$sesiones->convocar($sesionId, ['asignacion_ids' => [$quince[0]]], 1);
ok((int)$sesiones->ver($sesionId)['convocados'] === 15, 'Vuelve a 15/15');

echo "\n== Validaciones de formulario ==\n";
esperaRechazo(
    static fn () => $sesiones->crear(array_merge($baseSesion, ['fecha' => '', 'asignacion_ids' => []]), 1),
    'Sin fecha'
);
esperaRechazo(
    static fn () => $sesiones->crear(array_merge($baseSesion, ['hora' => '', 'asignacion_ids' => []]), 1),
    'Sin hora'
);
esperaRechazo(
    static fn () => $sesiones->crear(array_merge($baseSesion, ['cupo_maximo' => 0, 'asignacion_ids' => []]), 1),
    'Cupo cero'
);
esperaRechazo(
    static fn () => $sesiones->crear(array_merge($baseSesion, ['cupo_maximo' => -1, 'asignacion_ids' => []]), 1),
    'Cupo negativo'
);
esperaRechazo(
    static fn () => $sesiones->crear(array_merge($baseSesion, [
        'fecha' => '2028-03-15',
        'asignacion_ids' => [],
    ]), 1),
    'Fecha de otro año'
);
esperaRechazo(
    static fn () => $sesiones->crear(array_merge($baseSesion, [
        'ubicacion_id' => null,
        'asignacion_ids' => [],
    ]), 1),
    'Presencial sin ubicación'
);
esperaRechazo(
    static fn () => $sesiones->crear(array_merge($baseSesion, [
        'modalidad_id' => (int)$virtual['modalidad_id'],
        'ubicacion_id' => null,
        'enlace_virtual' => null,
        'asignacion_ids' => [],
    ]), 1),
    'Virtual sin enlace'
);
if ($otraCap !== null) {
    esperaRechazo(
        static fn () => $sesiones->crear(array_merge($baseSesion, [
            'capacitacion_id' => (int)$otraCap['capacitacion_id'],
            'asignacion_ids' => [],
        ]), 1),
        'Capacitación de otro plan'
    );
}

$virtualOk = $sesiones->crear(array_merge($baseSesion, [
    'fecha' => '2029-03-22',
    'hora' => '09:00',
    'modalidad_id' => (int)$virtual['modalidad_id'],
    'ubicacion_id' => null,
    'enlace_virtual' => 'https://teams.microsoft.com/l/meetup-join/prueba-sesion',
    'cupo_maximo' => 1,
    'asignacion_ids' => [$asignacionIds[15]],
]), 1);
ok($virtualOk['enlace_virtual'] !== null && str_contains((string)$virtualOk['enlace_virtual'], 'https://'), 'Sesión virtual con enlace');
ok((int)$virtualOk['convocados'] === 1, 'Cupo 1 convoca 1');

$exceso = $sesiones->crear(array_merge($baseSesion, [
    'fecha' => '2029-03-25',
    'cupo_maximo' => 15,
    'asignacion_ids' => [],
]), 1);
esperaRechazo(
    static fn () => $sesiones->convocar((int)$exceso['sesion_id'], ['asignacion_ids' => $asignacionIds], 1),
    'Exceso masivo atómico (20 sobre 15)'
);
ok((int)$sesiones->ver((int)$exceso['sesion_id'])['convocados'] === 0, 'No insertó parcialmente el exceso masivo');

echo "\n== Cronograma y RF-001 ==\n";
$tablero = $cronograma->tablero(['tipo' => 'mensual', 'anio' => $anioPrueba, 'mes' => 3]);
ok($tablero['total'] >= 1, 'Cronograma muestra el detalle');
$encontrada = false;
foreach ($tablero['meses'] as $bloque) {
    foreach ($bloque['items'] as $item) {
        foreach ($item['sesiones'] ?? [] as $s) {
            if ((int)$s['sesion_id'] === $sesionId) {
                $encontrada = true;
                ok($s['fecha'] === '2029-03-15', 'Sesión visible con fecha');
                ok(($s['modalidad_nombre'] ?? '') !== '', 'Sesión visible con modalidad');
                ok((int)$s['convocados'] === 15 && (int)$s['cupo_maximo'] === 15, '15/15 en cronograma');
            }
        }
    }
}
ok($encontrada, 'La sesión aparece en /cronograma');

$programadoDespues = $dashRepo->programado($periodo, 'general');
ok($programadoDespues === $programadoAntes, "RF-001 programado no cambia ({$programadoAntes} -> {$programadoDespues})");

echo "\n== Limpieza ==\n";
borrarPlanYSesiones($db, $anioPrueba);
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
$ref = $db->fetch('SELECT plan_detalle_id FROM plan_anual_detalle WHERE capacitacion_id = ? LIMIT 1', [$capId]);
$asig = $db->fetch('SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id = ? LIMIT 1', [$capId]);
$ses = $db->fetch('SELECT sesion_id FROM sesiones_capacitacion WHERE capacitacion_id = ? LIMIT 1', [$capId]);
if ($ref === null && $asig === null && $ses === null) {
    $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capId]);
}

echo "\nPruebas de sesiones OK.\n";
