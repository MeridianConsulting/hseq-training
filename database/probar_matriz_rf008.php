<?php

declare(strict_types=1);

/**
 * Pruebas de matriz masiva, inactivacion y motor RF-008.
 * Uso: php database/probar_matriz_rf008.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\MatrizService;
use App\Services\MotorAsignacionService;
use App\Services\PersonalService;

Env::load(BASE_PATH);

function ok(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
    echo "OK: {$mensaje}\n";
}

$db = Database::getInstance();
$personalDb = Database::personal();
$matriz = new MatrizService();
$motor = new MotorAsignacionService();
$personal = new PersonalService();

echo "== Duplicados historicos en matriz (informativo) ==\n";
$dups = $db->fetchAll(
    'SELECT capacitacion_id, cargo_id_ext, area_id, proceso_id, ambito, proyecto, COUNT(*) AS cantidad
     FROM matriz_aplicabilidad
     GROUP BY capacitacion_id, cargo_id_ext, area_id, proceso_id, ambito, proyecto
     HAVING COUNT(*) > 1'
);
echo $dups === []
    ? "Sin combinaciones duplicadas.\n"
    : ('Encontrados ' . count($dups) . " grupos duplicados (no se eliminan).\n");

$cargos = $personal->cargos();
ok(count($cargos) >= 3, 'Hay al menos 3 cargos en meridian_personal');
$tresCargos = array_slice($cargos, 0, 3);
$cargoIds = array_map(static fn (array $c): int => (int)$c['cargo_id'], $tresCargos);
echo 'Cargos de prueba: ' . implode(', ', array_map(static fn (array $c): string => $c['nombre_cargo'], $tresCargos)) . "\n";

$cap = $db->fetch("SELECT capacitacion_id, codigo, nombre, estado FROM capacitaciones WHERE estado = 'ACTIVA' ORDER BY capacitacion_id ASC LIMIT 1");
ok($cap !== null, 'Hay una capacitacion ACTIVA');
$capId = (int)$cap['capacitacion_id'];

$proceso = $db->fetch("SELECT proceso_id, nombre FROM procesos WHERE activo = 1 ORDER BY (nombre = 'Operaciones') DESC, proceso_id ASC LIMIT 1");
ok($proceso !== null, 'Hay un proceso activo');
$procesoId = (int)$proceso['proceso_id'];

$periodos = $db->fetchAll('SELECT periodicidad_id, nombre, cantidad, unidad FROM periodicidades WHERE activo = 1 ORDER BY periodicidad_id ASC LIMIT 2');
ok(count($periodos) >= 1, 'Hay periodicidad activa');
$periodoId = (int)$periodos[0]['periodicidad_id'];
$periodoAltId = isset($periodos[1]) ? (int)$periodos[1]['periodicidad_id'] : $periodoId;

$proyecto = 'HSEQ-RF008-' . date('YmdHis');
echo "Proyecto de prueba: {$proyecto}\n";
echo "Capacitacion: {$cap['codigo']} {$cap['nombre']}\n";
echo "Proceso: {$proceso['nombre']}\n";
echo "Periodicidad: {$periodos[0]['nombre']}\n";

$idsCreados = [];

echo "\n== Asociacion masiva a 3 cargos ==\n";
$lote = $matriz->asociarMasivo([
    'capacitacion_id' => $capId,
    'cargo_ids_ext' => $cargoIds,
    'proceso_id' => $procesoId,
    'proyecto' => $proyecto,
    'periodicidad_id' => $periodoId,
    'obligatoria' => 1,
], 0);
ok($lote['creadas'] === 3, 'Creadas=' . $lote['creadas']);
ok($lote['omitidas'] === 0, 'Omitidas=' . $lote['omitidas']);
foreach ($lote['items'] as $item) {
    $idsCreados[] = (int)$item['matriz_aplicabilidad_id'];
    ok((int)$item['capacitacion_id'] === $capId, 'Item conserva capacitacion');
    ok((int)$item['periodicidad_id'] === $periodoId, 'Item conserva periodicidad');
    ok($item['obligatoria'] === true, 'Item obligatoria=si');
    ok($item['activa'] === true, 'Item activa');
}

echo "\n== Consulta por cada cargo ==\n";
foreach ($tresCargos as $cargo) {
    $consulta = $matriz->listar(1, 20, [
        'capacitacion_id' => $capId,
        'cargo_id_ext' => (int)$cargo['cargo_id'],
        'proceso_id' => $procesoId,
        'proyecto' => $proyecto,
        'activa' => 1,
    ]);
    ok($consulta['total'] >= 1, 'Cargo ' . $cargo['nombre_cargo'] . ' tiene la capacitacion');
    $fila = $consulta['items'][0];
    ok((int)$fila['periodicidad_id'] === $periodoId, 'Periodicidad en ' . $cargo['nombre_cargo']);
    ok($fila['obligatoria'] === true, 'Obligatoria en ' . $cargo['nombre_cargo']);
}

echo "\n== Duplicados en lote ==\n";
$otra = $matriz->asociarMasivo([
    'capacitacion_id' => $capId,
    'cargo_ids_ext' => $cargoIds,
    'proceso_id' => $procesoId,
    'proyecto' => $proyecto,
    'periodicidad_id' => $periodoId,
    'obligatoria' => 1,
], 0);
ok($otra['creadas'] === 0, 'Duplicado no crea filas');
ok($otra['omitidas'] === 3, 'Omitidas=' . $otra['omitidas']);
ok($matriz->mensajeMasivo($otra) === 'La capacitación ya está asociada a este cargo, proceso y proyecto.', $matriz->mensajeMasivo($otra));

echo "\n== Cambio de periodicidad ==\n";
$primera = $idsCreados[0];
$actualizada = $matriz->actualizar($primera, ['periodicidad_id' => $periodoAltId]);
ok((int)$actualizada['periodicidad_id'] === $periodoAltId, 'Periodicidad actualizada en la misma fila');
$aplicables = $matriz->aplicables((int)$tresCargos[0]['cargo_id'], $procesoId, $proyecto);
$enAplicables = null;
foreach ($aplicables['items'] as $item) {
    if ((int)$item['matriz_aplicabilidad_id'] === $primera) {
        $enAplicables = $item;
        break;
    }
}
ok($enAplicables !== null, 'GET aplicables incluye la regla');
ok((int)$enAplicables['periodicidad_id'] === $periodoAltId, 'RF-008 consulta ve la periodicidad nueva');

echo "\n== Inactivacion ==\n";
$msgInact = $matriz->eliminar($primera);
ok($msgInact === 'El registro fue inactivado correctamente.', $msgInact);
$verInact = $matriz->ver($primera);
ok($verInact['activa'] === false, 'Regla inactiva en BD');
$aplicablesTras = $matriz->aplicables((int)$tresCargos[0]['cargo_id'], $procesoId, $proyecto);
$sigueActiva = false;
foreach ($aplicablesTras['items'] as $item) {
    if ((int)$item['matriz_aplicabilidad_id'] === $primera) {
        $sigueActiva = true;
    }
}
ok(!$sigueActiva, 'Inactiva no sale en aplicables');
$sigue = $db->fetch('SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad WHERE matriz_aplicabilidad_id = ?', [$primera]);
ok($sigue !== null, 'La fila historica se conserva');

echo "\n== Motor RF-008 ==\n";
$doc = '9000880088';
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
if ($prev !== null) {
    $pidPrev = (int)$prev['persona_id'];
    $db->query('DELETE FROM asignaciones_capacitacion WHERE persona_id_ext = ? AND origen = ?', [$pidPrev, 'AUTOMATICA']);
    $otras = $db->fetch('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ? LIMIT 1', [$pidPrev]);
    if ($otras === null) {
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pidPrev]);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pidPrev]);
    }
}

$creado = $personal->crear([
    'numero_documento' => $doc,
    'nombre_completo' => 'Prueba Motor RF Ocho',
    'correo' => 'rf008@hseq.test',
    'cargo_id' => $cargoIds[1],
    'proyecto' => $proyecto,
    'fecha_ingreso' => '2026-08-01',
]);
$personaId = (int)$creado['persona_id'];
ok($personaId > 0, 'Trabajador de prueba listo persona_id=' . $personaId);

$gen = $motor->generar(null, ['capacitacion_id' => $capId, 'proyecto' => $proyecto]);
ok($gen['creadas'] >= 1, 'Motor creo asignaciones creadas=' . $gen['creadas']);

$asig = $db->fetch(
    'SELECT asignacion_id, origen, matriz_aplicabilidad_id, capacitacion_id
     FROM asignaciones_capacitacion
     WHERE persona_id_ext = ? AND capacitacion_id = ? AND origen = ?',
    [$personaId, $capId, 'AUTOMATICA']
);
ok($asig !== null, 'Asignacion AUTOMATICA con matriz_aplicabilidad_id');
ok($asig['matriz_aplicabilidad_id'] !== null, 'Snapshot de regla presente');

$otraGen = $motor->generar(null, ['capacitacion_id' => $capId, 'proyecto' => $proyecto]);
ok($otraGen['creadas'] === 0, 'Segunda corrida no duplica pendientes');

echo "\n== Integracion ==\n";
$db->fetchAll('SELECT capacitacion_id FROM capacitaciones LIMIT 1');
$db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion LIMIT 1');
$personalDb->fetchAll("SELECT persona_id FROM {$personasT} LIMIT 1");
ok(true, 'capacitaciones, asignaciones y personal siguen consultables');

echo "\n== Limpieza ==\n";
$db->query(
    'DELETE FROM asignaciones_capacitacion WHERE persona_id_ext = ? AND origen = ? AND capacitacion_id = ?',
    [$personaId, 'AUTOMATICA', $capId]
);
foreach ($idsCreados as $idMatriz) {
    $db->query('UPDATE matriz_aplicabilidad SET activa = 0 WHERE matriz_aplicabilidad_id = ?', [$idMatriz]);
}
$asigsRest = $db->fetchAll(
    'SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaId]
);
if ($asigsRest === []) {
    $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$personaId]);
    $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$personaId]);
    echo "Trabajador de prueba eliminado.\n";
} else {
    echo "Trabajador de prueba conservado porque tiene otras asignaciones.\n";
}

echo "\nPruebas de matriz y RF-008 OK.\n";
