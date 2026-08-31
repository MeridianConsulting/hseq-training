<?php

declare(strict_types=1);

/**
 * Pruebas RF-008: disparo al alta, historial, idempotencia, cambio cargo/proyecto, masiva MANUAL.
 * Uso: php database/probar_asignaciones_rf008.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\AsignacionService;
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

function borrarPersonaSiLibre(Database $db, $personalDb, string $personasT, string $contratosT, int $personaId): void
{
    $db->query('DELETE FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$personaId]);
    $asigsRest = $db->fetch(
        'SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ? LIMIT 1',
        [$personaId]
    );
    if ($asigsRest === null) {
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$personaId]);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$personaId]);
    }
}

$db = Database::getInstance();
$personalDb = Database::personal();
$matriz = new MatrizService();
$motor = new MotorAsignacionService();
$personal = new PersonalService();
$asignaciones = new AsignacionService();

$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');

$db->query("UPDATE matriz_aplicabilidad SET activa = 0 WHERE proyecto LIKE 'HSEQ-RF008%'");

$cargos = $personal->cargos();
ok(count($cargos) >= 2, 'Hay al menos 2 cargos en meridian_personal');

$cargo = $cargos[0];
foreach ($cargos as $c) {
    if (strcasecmp(trim((string)$c['nombre_cargo']), 'Supervisor HSE') === 0) {
        $cargo = $c;
        break;
    }
}
$cargoId = (int)$cargo['cargo_id'];
$cargoAlt = null;
foreach ($cargos as $c) {
    if ((int)$c['cargo_id'] !== $cargoId) {
        $cargoAlt = $c;
        break;
    }
}
ok($cargoAlt !== null, 'Hay un segundo cargo para el cambio');
$cargoAltId = (int)$cargoAlt['cargo_id'];
echo 'Cargo de prueba: ' . $cargo['nombre_cargo'] . " (id={$cargoId})\n";
echo 'Cargo alterno: ' . $cargoAlt['nombre_cargo'] . " (id={$cargoAltId})\n";

$periodo = $db->fetch('SELECT periodicidad_id, nombre FROM periodicidades WHERE activo = 1 ORDER BY periodicidad_id ASC LIMIT 1');
ok($periodo !== null, 'Hay periodicidad activa');
$periodoId = (int)$periodo['periodicidad_id'];

$capsCreadas = [];
$tresCaps = [];
for ($i = 1; $i <= 3; $i++) {
    $codigo = 'RF008-R' . $i . '-' . date('YmdHis');
    $capId = (int)$db->insert('capacitaciones', [
        'codigo' => $codigo,
        'nombre' => "Capacitacion automatica RF-008 {$i}",
        'objetivo' => 'Prueba de disparo automatico RF-008',
        'duracion_estimada_horas' => 1,
        'criticidad' => 'BAJA',
        'estado' => 'ACTIVA',
        'periodicidad_default_id' => $periodoId,
    ]);
    ok($capId > 0, "Capacitacion de regla {$i} creada id={$capId}");
    $capsCreadas[] = $capId;
    $tresCaps[] = [
        'capacitacion_id' => $capId,
        'codigo' => $codigo,
        'nombre' => "Capacitacion automatica RF-008 {$i}",
    ];
}
$capIds = $capsCreadas;

$codigoMasiva = 'RF008-MAS-' . date('YmdHis');
$capMasivaId = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoMasiva,
    'nombre' => 'Capacitacion masiva RF-008',
    'objetivo' => 'Prueba de asignacion masiva MANUAL',
    'duracion_estimada_horas' => 1,
    'criticidad' => 'BAJA',
    'estado' => 'ACTIVA',
    'periodicidad_default_id' => $periodoId,
]);
ok($capMasivaId > 0, 'Capacitacion de masiva creada id=' . $capMasivaId);
$capIndivInact = 0;

$proyecto = 'HSEQ-RF008A-' . date('YmdHis');
$proyectoAlt = 'HSEQ-RF008B-' . date('YmdHis');
echo "Proyecto: {$proyecto}\n";

$idsMatriz = [];
echo "\n== Matriz: 3 reglas activas cargo+proyecto ==\n";
foreach ($tresCaps as $cap) {
    $fila = $matriz->crear([
        'capacitacion_id' => (int)$cap['capacitacion_id'],
        'cargo_id_ext' => $cargoId,
        'proyecto' => $proyecto,
        'periodicidad_id' => $periodoId,
        'obligatoria' => 1,
    ], 0);
    $idsMatriz[] = (int)$fila['matriz_aplicabilidad_id'];
    echo 'Regla ' . $cap['codigo'] . ' -> matriz_id=' . $fila['matriz_aplicabilidad_id'] . "\n";
}
ok(count($idsMatriz) === 3, 'Tres reglas creadas');

$docs = ['9000880101', '9000880102', '9000880103', '9000880104'];
foreach ($docs as $doc) {
    $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
    if ($prev !== null) {
        borrarPersonaSiLibre($db, $personalDb, $personasT, $contratosT, (int)$prev['persona_id']);
    }
}

echo "\n== 1. Alta dispara el motor (sin generar-automaticas) ==\n";
$creado = $personal->crear([
    'numero_documento' => $docs[0],
    'nombre_completo' => 'Prueba Asignaciones RF Ocho',
    'correo' => 'rf008asig@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => $proyecto,
    'fecha_ingreso' => '2026-08-01',
]);
$personaId = (int)$creado['persona_id'];
ok($personaId > 0, 'Trabajador creado persona_id=' . $personaId);
ok(isset($creado['sincronizacion']), 'Respuesta incluye sincronizacion');
ok($creado['sincronizacion']['error'] === null, 'Motor no reporto error');
ok((int)$creado['sincronizacion']['creadas'] >= 3, 'Creadas>=3 got=' . (int)$creado['sincronizacion']['creadas']);

$automaticas = $db->fetchAll(
    'SELECT asignacion_id, capacitacion_id, origen, matriz_aplicabilidad_id
     FROM asignaciones_capacitacion
     WHERE persona_id_ext = ? AND origen = ?',
    [$personaId, 'AUTOMATICA']
);
$capsCreadas = array_map(static fn (array $a): int => (int)$a['capacitacion_id'], $automaticas);
foreach ($capIds as $cid) {
    ok(in_array($cid, $capsCreadas, true), "AUTOMATICA para capacitacion {$cid}");
}
foreach ($automaticas as $fila) {
    if (in_array((int)$fila['capacitacion_id'], $capIds, true)) {
        ok($fila['matriz_aplicabilidad_id'] !== null, 'Snapshot de matriz en automatica');
    }
}

echo "\n== 2. Historial por persona_id ==\n";
$historial = $asignaciones->listar(1, 50, $personaId, null, null, null, null);
ok($historial['total'] >= 3, 'Historial total=' . $historial['total']);
$enHistorial = 0;
foreach ($historial['items'] as $item) {
    if ((int)$item['persona_id_ext'] === $personaId && in_array((int)$item['capacitacion_id'], $capIds, true)) {
        $enHistorial++;
        ok($item['origen'] === 'AUTOMATICA', 'Origen AUTOMATICA en listado');
        ok($item['periodicidad_nombre'] !== null && $item['periodicidad_nombre'] !== '', 'Periodicidad en JOIN');
        ok($item['obligatoria'] === true, 'Obligatoria de la regla');
    }
}
ok($enHistorial === 3, "Historial muestra las 3 reglas got={$enHistorial}");

echo "\n== 3. Re-sync idempotente ==\n";
$resync = $motor->sincronizarPersona($creado, null);
ok((int)$resync['creadas'] === 0, 'Re-sync creadas=0 got=' . $resync['creadas']);

echo "\n== 4. Inactivar regla no recrea esa cap ==\n";
$reglaInact = $idsMatriz[0];
$capInact = $capIds[0];
$msgInact = $matriz->eliminar($reglaInact);
ok($msgInact === 'El registro fue inactivado correctamente.', $msgInact);

$asigInact = $db->fetch(
    'SELECT asignacion_id FROM asignaciones_capacitacion
     WHERE persona_id_ext = ? AND capacitacion_id = ? AND origen = ? LIMIT 1',
    [$personaId, $capInact, 'AUTOMATICA']
);
ok($asigInact !== null, 'Asignacion previa de la regla inactivada sigue');
$db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [(int)$asigInact['asignacion_id']]);

$persona = $personal->ver($personaId);
$motor->sincronizarPersona($persona, null);
$recreada = $db->fetch(
    'SELECT asignacion_id FROM asignaciones_capacitacion
     WHERE persona_id_ext = ? AND capacitacion_id = ? LIMIT 1',
    [$personaId, $capInact]
);
ok($recreada === null, 'Sync no recrea la capacitacion de la regla inactiva');

$siguen = $db->fetchAll(
    'SELECT capacitacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ? AND origen = ?',
    [$personaId, 'AUTOMATICA']
);
$siguenIds = array_map(static fn (array $a): int => (int)$a['capacitacion_id'], $siguen);
ok(in_array($capIds[1], $siguenIds, true), 'Asignacion previa 2 intacta');
ok(in_array($capIds[2], $siguenIds, true), 'Asignacion previa 3 intacta');

echo "\n== 5. Cambio cargo/proyecto anade aplicables y no borra historial ==\n";
$reglaNueva = $matriz->crear([
    'capacitacion_id' => $capInact,
    'cargo_id_ext' => $cargoAltId,
    'proyecto' => $proyectoAlt,
    'periodicidad_id' => $periodoId,
    'obligatoria' => 1,
], 0);
$idsMatriz[] = (int)$reglaNueva['matriz_aplicabilidad_id'];

$antesCambio = $db->fetchAll(
    'SELECT asignacion_id, capacitacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaId]
);
$idsAntes = array_map(static fn (array $a): int => (int)$a['asignacion_id'], $antesCambio);

$editado = $personal->editar($personaId, [
    'correo' => 'rf008asig@hseq.test',
    'cargo_id' => $cargoAltId,
    'proyecto' => $proyectoAlt,
]);
ok(isset($editado['sincronizacion']), 'Edicion sincronizo');
ok((int)$editado['sincronizacion']['creadas'] >= 1, 'Cambio cargo/proyecto creo nuevas got=' . (int)$editado['sincronizacion']['creadas']);

$nuevaCap = $db->fetch(
    'SELECT asignacion_id, origen FROM asignaciones_capacitacion
     WHERE persona_id_ext = ? AND capacitacion_id = ? AND origen = ?',
    [$personaId, $capInact, 'AUTOMATICA']
);
ok($nuevaCap !== null, 'Nueva aplicable (cap de cargo nuevo) creada');

$despuesCambio = $db->fetchAll(
    'SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaId]
);
$idsDespues = array_map(static fn (array $a): int => (int)$a['asignacion_id'], $despuesCambio);
foreach ($idsAntes as $idViejo) {
    ok(in_array($idViejo, $idsDespues, true), "Historial viejo asignacion {$idViejo} intacto");
}

echo "\n== 6. Masiva MANUAL, duplicados y matriz intacta ==\n";
$extras = [];
for ($i = 1; $i <= 3; $i++) {
    $extra = $personal->crear([
        'numero_documento' => $docs[$i],
        'nombre_completo' => "Prueba Masiva RF Ocho {$i}",
        'correo' => "rf008mas{$i}@hseq.test",
        'cargo_id' => $cargoAltId,
        'proyecto' => $proyectoAlt,
        'fecha_ingreso' => '2026-08-01',
    ]);
    $extras[] = (int)$extra['persona_id'];
}
$loteIds = array_merge([$personaId], $extras);
$n = count($loteIds);
ok($n >= 2 && $n <= 20, "Lote de prueba N={$n}");

$matrizAntes = (int)($db->fetch('SELECT COUNT(*) AS t FROM matriz_aplicabilidad')['t'] ?? 0);

$masivo = $asignaciones->crearMasivo([
    'persona_ids_ext' => $loteIds,
    'capacitacion_id' => $capMasivaId,
], 0);
ok((int)$masivo['creadas'] === $n, 'Masiva creo N=' . $masivo['creadas']);
ok((int)$masivo['omitidas'] === 0, 'Primera masiva sin omitidas');
foreach ($masivo['items'] as $item) {
    ok($item['origen'] === 'MANUAL', 'Origen MANUAL');
    ok($item['obligatoria'] === null, 'Manual sin obligatoriedad de matriz');
}

$manuales = $db->fetchAll(
    'SELECT matriz_aplicabilidad_id, origen FROM asignaciones_capacitacion
     WHERE capacitacion_id = ? AND persona_id_ext IN (' . implode(',', array_fill(0, $n, '?')) . ')',
    array_merge([$capMasivaId], $loteIds)
);
foreach ($manuales as $m) {
    ok($m['origen'] === 'MANUAL', 'Fila masiva es MANUAL');
    ok($m['matriz_aplicabilidad_id'] === null, 'Masiva no enlaza matriz');
}

$otraMasiva = $asignaciones->crearMasivo([
    'persona_ids_ext' => $loteIds,
    'capacitacion_id' => $capMasivaId,
], 0);
ok((int)$otraMasiva['creadas'] === 0, 'Duplicados no crean');
ok((int)$otraMasiva['omitidas'] === $n, 'Omitidas=' . $otraMasiva['omitidas']);
ok(
    str_contains($asignaciones->mensajeMasivo($otraMasiva), 'ya tenían esta capacitación'),
    $asignaciones->mensajeMasivo($otraMasiva)
);

$matrizDespues = (int)($db->fetch('SELECT COUNT(*) AS t FROM matriz_aplicabilidad')['t'] ?? 0);
ok($matrizAntes === $matrizDespues, 'Masiva no altero filas de matriz');

$filtroManual = $asignaciones->listar(1, 50, $personaId, $capMasivaId, null, null, null, 'MANUAL');
ok($filtroManual['total'] >= 1, 'Filtro origen MANUAL funciona');

echo "\n== 7. Alta individual rechaza capacitacion inactiva ==\n";
$codigoInact = 'RF008-INACT-' . date('YmdHis');
$capIndivInact = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoInact,
    'nombre' => 'Capacitacion inactiva RF-008',
    'objetivo' => 'Prueba de rechazo ACTIVA en alta individual',
    'duracion_estimada_horas' => 1,
    'criticidad' => 'BAJA',
    'estado' => 'INACTIVA',
    'periodicidad_default_id' => $periodoId,
]);
ok($capIndivInact > 0, 'Capacitacion inactiva de prueba creada');

$msgRechazo = null;
try {
    $asignaciones->crear([
        'persona_id_ext' => $personaId,
        'capacitacion_id' => $capIndivInact,
        'fecha_limite_cumplimiento' => '2026-12-31',
    ], 0);
} catch (HttpException $e) {
    $msgRechazo = $e->getMessage();
    ok($e->getStatusCode() === 422, 'HTTP 422 en cap inactiva');
}
ok(
    $msgRechazo === 'Solo se puede asignar una capacitación activa.',
    'Rechazo individual: ' . ($msgRechazo ?? 'no lanzo')
);
$asigInactIndiv = $db->fetch(
    'SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ? AND capacitacion_id = ? LIMIT 1',
    [$personaId, $capIndivInact]
);
ok($asigInactIndiv === null, 'No se inserto asignacion de cap inactiva');

$lockPrueba = $db->fetch('SELECT GET_LOCK(?, 1) AS tomado', ['hseq-asig-' . $personaId]);
ok((int)($lockPrueba['tomado'] ?? -1) === 1, 'GET_LOCK nominado por persona disponible');
$db->fetch('SELECT RELEASE_LOCK(?) AS liberado', ['hseq-asig-' . $personaId]);

echo "\n== 8. Integracion ==\n";
$db->fetchAll('SELECT capacitacion_id FROM capacitaciones LIMIT 1');
$db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion LIMIT 1');
$db->fetchAll('SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad LIMIT 1');
$personalDb->fetchAll("SELECT persona_id FROM {$personasT} LIMIT 1");
ok(true, 'personal, matriz y asignaciones siguen consultables');

echo "\n== Limpieza ==\n";
$todosIds = $loteIds;
foreach ($todosIds as $pid) {
    $db->query('DELETE FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
}
foreach ($idsMatriz as $idMatriz) {
    $db->query('UPDATE matriz_aplicabilidad SET activa = 0 WHERE matriz_aplicabilidad_id = ?', [$idMatriz]);
}
$db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capMasivaId]);
if ($capIndivInact > 0) {
    $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capIndivInact]);
}
foreach ($capsCreadas as $cid) {
    $ref = $db->fetch(
        'SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad WHERE capacitacion_id = ? LIMIT 1',
        [$cid]
    );
    $asig = $db->fetch(
        'SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id = ? LIMIT 1',
        [$cid]
    );
    if ($ref === null && $asig === null) {
        $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$cid]);
    }
}
foreach ($todosIds as $pid) {
    borrarPersonaSiLibre($db, $personalDb, $personasT, $contratosT, $pid);
}

echo "\nPruebas RF-008 asignaciones OK.\n";
