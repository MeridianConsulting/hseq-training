<?php

declare(strict_types=1);

/**
 * Asignaciones alineadas a Personal y Matriz: aplicable, bloqueo, multi-cap, masiva y cargo.
 * Uso: php database/probar_asignaciones_consulta.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Services\AsignacionService;
use App\Services\PersonalService;

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

function esperaRechazo(callable $fn, string $mensaje, int $status = 422): void
{
    try {
        $fn();
        fwrite(STDERR, "FALLO: se esperaba rechazo — {$mensaje}\n");
        exit(1);
    } catch (HttpException $e) {
        ok($e->getStatusCode() === $status, $mensaje . ' [' . $e->getStatusCode() . '] ' . $e->getMessage());
    }
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$alertasRepo = new AlertaRepository();
$actor = ['usuario_id' => 1, 'nombre' => 'admin.hseq', 'ip' => '127.0.0.1'];

$stamp = date('YmdHis');
$docA = '90013' . substr($stamp, -5) . '1';
$docB = '90013' . substr($stamp, -5) . '2';
$prefijo = 'HSEQ-AS-' . substr($stamp, -6);
$capIds = [];
$matrizIds = [];
$personaA = 0;
$personaB = 0;
$asigId = 0;
$cumpId = 0;

$limpiar = static function () use ($db, $personalDb, $personasT, $contratosT, $docA, $docB, &$capIds, &$matrizIds): void {
    foreach ([$docA, $docB] as $doc) {
        $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
        if ($prev === null) {
            continue;
        }
        $pid = (int)$prev['persona_id'];
        $db->query('DELETE FROM historial_contexto_trabajador WHERE persona_id_ext = ?', [$pid]);
        $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
        foreach ($asigs as $a) {
            $aid = (int)$a['asignacion_id'];
            $cump = $db->fetchAll('SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ?', [$aid]);
            foreach ($cump as $c) {
                $cid = (int)$c['cumplimiento_id'];
                $db->query('DELETE FROM soportes_cumplimiento WHERE cumplimiento_id = ?', [$cid]);
                $db->query('DELETE FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?', [$cid]);
            }
            $db->query('DELETE FROM sesion_participantes WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [$aid]);
        }
        $db->query("DELETE FROM auditoria WHERE entidad IN ('personal', 'asignaciones_capacitacion') AND entidad_id = ?", [$pid]);
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
    }
    foreach ($matrizIds as $mid) {
        $db->query('DELETE FROM matriz_aplicabilidad WHERE matriz_aplicabilidad_id = ?', [$mid]);
    }
    foreach ($capIds as $cid) {
        $db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$cid]);
        $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$cid]);
    }
};

$limpiar();
register_shutdown_function($limpiar);

$cargos = $personal->cargos();
ok(count($cargos) >= 2, 'Hay al menos dos cargos corporativos');
$cargoA = (int)$cargos[0]['cargo_id'];
$cargoB = (int)$cargos[1]['cargo_id'];

$proceso = $db->fetch(
    "SELECT proceso_id, nombre FROM procesos
     WHERE activo = 1
     ORDER BY (nombre LIKE '%Gestión de Proyectos%' OR nombre LIKE '%Gestion de Proyectos%') DESC,
              proceso_id ASC
     LIMIT 1"
);
ok($proceso !== null, 'Hay un proceso activo');
$procesoId = (int)$proceso['proceso_id'];
$esGP = $alertasRepo->procesoEsGestionProyectos($procesoId);
$ambito = $esGP ? 'PROYECTO' : 'ADMINISTRACION';
$proyectoMatriz = $esGP ? 'FRONTERA' : null;

$creadoA = $personal->crear([
    'numero_documento' => $docA,
    'nombre_completo' => 'Asignacion Prueba Supervisor',
    'correo' => 'asig.a.' . substr($stamp, -6) . '@hseq.test',
    'cargo_id' => $cargoA,
    'proyecto' => 'FRONTERA',
    'fecha_ingreso' => '2025-04-01',
], false);
$personaA = (int)$creadoA['persona_id'];

$creadoB = $personal->crear([
    'numero_documento' => $docB,
    'nombre_completo' => 'Asignacion Prueba Auxiliar',
    'correo' => 'asig.b.' . substr($stamp, -6) . '@hseq.test',
    'cargo_id' => $cargoB,
    'proyecto' => 'FRONTERA',
    'fecha_ingreso' => '2025-04-01',
], false);
$personaB = (int)$creadoB['persona_id'];
ok($personaA > 0 && $personaB > 0, 'Trabajadores de prueba en corporativa');

foreach (['ALT' => 'Trabajo en alturas prueba', 'PAX' => 'Primeros auxilios prueba', 'NOA' => 'No aplicable prueba'] as $suf => $nombre) {
    $capIds[] = (int)$db->insert('capacitaciones', [
        'codigo' => $prefijo . '-' . $suf,
        'nombre' => $nombre,
        'objetivo' => 'Probar asignaciones',
        'duracion_estimada_horas' => 8,
        'criticidad' => 'MEDIA',
        'estado' => 'ACTIVA',
    ]);
}
ok(count($capIds) === 3, 'Tres capacitaciones de prueba');
[$capAplica1, $capAplica2, $capNoAplica] = $capIds;

foreach ([$capAplica1, $capAplica2] as $capId) {
    $matrizIds[] = (int)$db->insert('matriz_aplicabilidad', [
        'capacitacion_id' => $capId,
        'cargo_id_ext' => $cargoA,
        'area_id' => null,
        'proceso_id' => $procesoId,
        'ambito' => $ambito,
        'proyecto' => $proyectoMatriz,
        'periodicidad_id' => null,
        'obligatoria' => 1,
        'activa' => 1,
    ]);
}
ok(count($matrizIds) === 2, 'Matriz marca dos capacitaciones para el cargo A');

$limite = date('Y-m-d', strtotime('+30 days'));
$hoy = date('Y-m-d');

echo "== Asignación aplicable y duplicado ==\n";
$asig = $asignaciones->crear([
    'persona_id_ext' => $personaA,
    'capacitacion_id' => $capAplica1,
    'fecha_asignacion' => $hoy,
    'fecha_limite_cumplimiento' => $limite,
], 1, $actor);
$asigId = (int)$asig['asignacion_id'];
ok($asigId > 0, 'Asignación manual de capacitación aplicable');
ok(($asig['origen'] ?? '') === 'MANUAL', 'Origen Manual');
ok((int)($asig['persona_id_ext'] ?? 0) === $personaA, 'Trabajador correcto');
ok((int)($asig['capacitacion_id'] ?? 0) === $capAplica1, 'Capacitación correcta');

esperaRechazo(
    static fn () => $asignaciones->crear([
        'persona_id_ext' => $personaA,
        'capacitacion_id' => $capAplica1,
        'fecha_asignacion' => $hoy,
        'fecha_limite_cumplimiento' => $limite,
    ], 1, $actor),
    'Duplicado pendiente',
    409
);

echo "== Bloqueo si no aplica ==\n";
$matrizAntes = (int)$db->fetch('SELECT COUNT(*) AS n FROM matriz_aplicabilidad')['n'];
esperaRechazo(
    static fn () => $asignaciones->crear([
        'persona_id_ext' => $personaA,
        'capacitacion_id' => $capNoAplica,
        'fecha_asignacion' => $hoy,
        'fecha_limite_cumplimiento' => $limite,
    ], 1, $actor),
    AsignacionService::MENSAJE_NO_APLICABLE,
    422
);
$matrizDespues = (int)$db->fetch('SELECT COUNT(*) AS n FROM matriz_aplicabilidad')['n'];
ok($matrizAntes === $matrizDespues, 'La matriz no cambia al intentar asignar lo no aplicable');

echo "== Varias capacitaciones ==\n";
$varias = $asignaciones->crearVarias([
    'persona_id_ext' => $personaA,
    'capacitacion_ids' => [$capAplica1, $capAplica2, $capNoAplica],
    'fecha_asignacion' => $hoy,
    'fecha_limite_cumplimiento' => $limite,
], 1, $actor);
ok((int)$varias['creadas'] === 1, 'Se crea solo la aplicable pendiente');
ok((int)$varias['omitidas'] === 2, 'Se omiten duplicada y no aplicable');
$motivos = array_column($varias['omitidas_detalle'], 'motivo');
ok(in_array('duplicado', $motivos, true), 'Omite duplicado');
ok(in_array('no_aplicable', $motivos, true), 'Omite no aplicable');
ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM asignaciones_capacitacion WHERE persona_id_ext = ? AND capacitacion_id = ?',
    [$personaA, $capNoAplica]
)['n'] === 0, 'No se crea la capacitación no aplicable');

echo "== Listado ==\n";
$porCargo = $asignaciones->listar(1, 20, $personaA, null, null, null, null, null, null, null, null, null, $cargoA);
ok(count($porCargo['items']) >= 1, 'Filtro por cargo del snapshot');
$porBuscar = $asignaciones->listar(1, 20, null, null, null, null, $prefijo . '-ALT');
ok(count($porBuscar['items']) >= 1, 'Búsqueda por código de capacitación');
$detalle = $asignaciones->ver($asigId);
ok(($detalle['cargo'] ?? '') !== '', 'El detalle incluye el nombre del cargo');

echo "== Masiva ==\n";
$masivo = $asignaciones->crearMasivo([
    'capacitacion_id' => $capAplica2,
    'persona_ids_ext' => [$personaA, $personaB],
    'fecha_limite_cumplimiento' => $limite,
], 1, $actor);
ok((int)$masivo['seleccionados'] === 2, 'Masiva selecciona dos personas');
ok((int)$masivo['creadas'] === 0, 'A ya tenía la segunda cap; B no aplica');
ok((int)$masivo['omitidas'] >= 1, 'Hay omitidas con motivo');
$motivosMasivo = array_column($masivo['omitidas_detalle'], 'motivo');
ok(in_array('duplicado', $motivosMasivo, true) || in_array('no_aplicable', $motivosMasivo, true), 'Detalle de omisión presente');

$masivoB = $asignaciones->crearMasivo([
    'capacitacion_id' => $capAplica1,
    'persona_ids_ext' => [$personaB],
    'fecha_limite_cumplimiento' => $limite,
], 1, $actor);
ok((int)$masivoB['creadas'] === 0, 'B no recibe cap que no aplica a su cargo');
ok(($masivoB['omitidas_detalle'][0]['motivo'] ?? '') === 'no_aplicable', 'Motivo no_aplicable en masiva');

echo "== Cambio de cargo ==\n";
$cumpId = (int)$db->insert('cumplimientos_capacitacion', [
    'asignacion_id' => $asigId,
    'sesion_id' => null,
    'fecha_realizacion' => $hoy,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'nota_evaluacion' => 4.0,
    'fecha_vencimiento' => date('Y-m-d', strtotime('+365 days')),
]);
ok($cumpId > 0, 'Cumplimiento histórico como cargo A');

$asigsAntes = (int)$db->fetch(
    'SELECT COUNT(*) AS n FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaA]
)['n'];

$editado = $personal->editar($personaA, [
    'cargo_id' => $cargoB,
    'correo_corporativo' => 'asig.a.' . substr($stamp, -6) . '@hseq.test',
    'proyecto' => 'FRONTERA',
]);
ok((int)($editado['cargo_id'] ?? 0) === $cargoB, 'La ficha refleja el cargo nuevo');

ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaA]
)['n'] >= $asigsAntes, 'Las asignaciones previas siguen');
ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$asigId]
)['n'] === 1, 'El cumplimiento previo permanece');

$hist = $asignaciones->ver($asigId);
ok((int)($hist['cargo_id_ext'] ?? 0) === $cargoA, 'El snapshot histórico conserva el cargo anterior');

echo "\nListo: asignaciones alineadas a personal y matriz.\n";
