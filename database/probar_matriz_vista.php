<?php

declare(strict_types=1);

/**
 * Pruebas RF-MA-036 / RF-MA-037: grilla de aplicabilidad por contexto.
 * Uso: php database/probar_matriz_vista.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Services\CapacitacionService;
use App\Services\MatrizService;
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

function normalizarNombre(string $nombre): string
{
    $sinTildes = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
    $texto = is_string($sinTildes) && $sinTildes !== '' ? $sinTildes : $nombre;

    return strtoupper(trim((string)preg_replace('/\s+/', ' ', $texto)));
}

$db = Database::getInstance();
$matriz = new MatrizService();
$caps = new CapacitacionService();
$personal = new PersonalService();
$alertas = new AlertaRepository();
$stamp = date('YmdHis');
$capIds = [];

echo "== Opciones de matriz ==\n";
$opciones = $matriz->opciones();
ok($opciones['procesos'] !== [], 'Hay procesos Excel para la matriz');
ok(in_array('FRONTERA', $opciones['proyectos'], true), 'Catálogo de proyectos incluye FRONTERA');
ok(($opciones['cargos'] ?? []) !== [], 'Opciones incluyen el catálogo de cargos');
ok(isset($opciones['capacitaciones']) && is_array($opciones['capacitaciones']), 'Opciones incluyen capacitaciones');

$procesoGp = null;
$procesoOtro = null;
foreach ($opciones['procesos'] as $proceso) {
    if ($alertas->procesoEsGestionProyectos((int)$proceso['proceso_id'])) {
        $procesoGp = $proceso;
    } elseif ($procesoOtro === null) {
        $procesoOtro = $proceso;
    }
}
ok($procesoGp !== null, 'Existe el proceso Gestión de Proyectos');
ok($procesoOtro !== null, 'Existe un proceso distinto a Gestión de Proyectos');
$procesoGpId = (int)$procesoGp['proceso_id'];
$procesoOtroId = (int)$procesoOtro['proceso_id'];

$cargos = $personal->cargos();
ok($cargos !== [], 'Hay cargos en el catálogo de personal');
$supervisor = $cargos[0];
foreach ($cargos as $cargo) {
    if (str_contains(normalizarNombre((string)$cargo['nombre_cargo']), 'SUPERVISOR')) {
        $supervisor = $cargo;
        break;
    }
}
$cargoId = (int)$supervisor['cargo_id'];
echo 'Cargo de prueba: ' . $supervisor['nombre_cargo'] . " ({$cargoId})\n";
echo 'Proceso GP: ' . $procesoGp['nombre'] . "\n";

$tipo = $db->fetch('SELECT tipo_capacitacion_id FROM tipos_capacitacion WHERE activo = 1 ORDER BY tipo_capacitacion_id ASC LIMIT 1');
$modalidad = $db->fetch('SELECT modalidad_id FROM modalidades WHERE activo = 1 ORDER BY modalidad_id ASC LIMIT 1');
ok($tipo !== null && $modalidad !== null, 'Hay tipo y modalidad para capacitaciones de prueba');

echo "\n== Capacitaciones A / B / C ==\n";
$creadasCaps = [];
foreach (['A' => 'Matriz aplica A', 'B' => 'Matriz aplica B', 'C' => 'Matriz no aplica C'] as $letra => $nombre) {
    $creada = $caps->crear([
        'codigo' => 'CAP-MA-' . $letra . '-' . $stamp,
        'nombre' => $nombre,
        'objetivo' => 'Prueba de matriz de aplicabilidad ' . $letra,
        'duracion_estimada_horas' => 2,
        'tipo_capacitacion_id' => (int)$tipo['tipo_capacitacion_id'],
        'modalidad_default_id' => (int)$modalidad['modalidad_id'],
        'es_tarea_critica' => $letra === 'A' ? 1 : 0,
        'evaluacion' => 0,
        'estado' => 'ACTIVA',
    ], 1);
    $id = (int)$creada['capacitacion_id'];
    $capIds[] = $id;
    $creadasCaps[$letra] = $id;
    echo "Cap {$letra}: {$creada['codigo']} ({$id})\n";
}

try {
    echo "\n== Vista exige proceso ==\n";
    $matriz->vista(null, null);
    ok(false, 'Vista sin proceso debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Vista sin proceso = 422');
}

try {
    $matriz->vista($procesoGpId, null);
    ok(false, 'Vista GP sin proyecto debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Vista GP sin proyecto = 422');
}

$vista = $matriz->vista($procesoGpId, 'FRONTERA');
ok((int)$vista['proceso_id'] === $procesoGpId, 'Vista conserva proceso');
ok($vista['proyecto'] === 'FRONTERA', 'Vista conserva FRONTERA');
$idsFrontera = array_map(static fn (array $c): int => (int)$c['cargo_id'], $vista['cargos']);
$nombresFrontera = array_map(static fn (array $c): string => normalizarNombre((string)$c['nombre_cargo']), $vista['cargos']);
ok($vista['cargos'] !== [], 'Gestión de proyectos muestra cargos de la matriz por cargo');
ok(!in_array($cargoId, $idsFrontera, true), 'Un cargo de oficina no entra solo por nómina en Frontera');
$hayB1 = false;
$hayAsistenteD1 = false;
$hayCompanyManD1 = false;
foreach ($nombresFrontera as $nombreCargo) {
    if ($nombreCargo === 'COMPANY MAN B1') {
        $hayB1 = true;
    }
    if ($nombreCargo === 'ASISTENTE DE COMPANY MAN D1') {
        $hayAsistenteD1 = true;
    }
    if ($nombreCargo === 'COMPANY MAN D1' || str_contains($nombreCargo, 'ING COMPANY')) {
        $hayCompanyManD1 = true;
    }
}
ok($hayB1, 'Frontera incluye Company Man B1');
ok($hayAsistenteD1, 'Frontera incluye Asistente de Company Man D1');
ok(!$hayCompanyManD1, 'Frontera no lista Ing Company D1 ni Company Man D1');
$vistaOficina = $matriz->vista($procesoOtroId, null);
ok($vistaOficina['cargos'] !== [], 'Los procesos de oficina traen cargos');
$idsOficina = array_map(static fn (array $c): int => (int)$c['cargo_id'], $vistaOficina['cargos']);
ok(count($idsOficina) < count($vistaOficina['cargos_catalogo'] ?? $vistaOficina['cargos']), 'Oficina no lista todo el catálogo');
try {
    $matriz->vista($procesoGpId, 'NO-EXISTE');
    ok(false, 'Proyecto fuera de catálogo debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Proyecto inválido = 422');
}
ok(isset($vista['cargos_catalogo']) && $vista['cargos_catalogo'] !== [], 'La vista incluye el catálogo de cargos');
$idsCatalogo = array_map(static fn (array $c): int => (int)$c['cargo_id'], $vista['cargos_catalogo']);
ok(in_array($cargoId, $idsCatalogo, true), 'El cargo del catálogo está en cargos_catalogo');
ok(count($vista['cargos_catalogo']) >= count($vista['cargos']), 'El contexto no trae más cargos que el catálogo');
$idsCapVista = array_map(static fn (array $c): int => (int)$c['capacitacion_id'], $vista['capacitaciones']);
ok(in_array($creadasCaps['A'], $idsCapVista, true), 'Cap A ACTIVA aparece en columnas');
ok(in_array($creadasCaps['B'], $idsCapVista, true), 'Cap B ACTIVA aparece en columnas');
ok(in_array($creadasCaps['C'], $idsCapVista, true), 'Cap C ACTIVA aparece en columnas');
$criticaA = null;
foreach ($vista['capacitaciones'] as $cap) {
    if ((int)$cap['capacitacion_id'] === $creadasCaps['A']) {
        $criticaA = $cap['es_tarea_critica'];
    }
}
ok($criticaA === true, 'Cap A expone flag de tarea crítica');

$payloadAplica = [
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
    'aplica' => [
        ['cargo_id_ext' => $cargoId, 'capacitacion_id' => $creadasCaps['A']],
        ['cargo_id_ext' => $cargoId, 'capacitacion_id' => $creadasCaps['B']],
    ],
];
ok(!isset($payloadAplica['persona_id']) && !isset($payloadAplica['cedula']), 'El payload no incluye personas');

echo "\n== Sincronizar Supervisor: A y B aplican, C no ==\n";
$sync = $matriz->sincronizar($payloadAplica, 1);
ok($sync['creadas'] >= 2, 'Crea al menos A y B');

$filasAbc = $db->fetchAll(
    'SELECT capacitacion_id, activa, matriz_aplicabilidad_id
     FROM matriz_aplicabilidad
     WHERE proceso_id = ? AND cargo_id_ext = ?
       AND proyecto COLLATE utf8mb4_unicode_ci = ?
       AND capacitacion_id IN (?, ?, ?)',
    [$procesoGpId, $cargoId, 'FRONTERA', $creadasCaps['A'], $creadasCaps['B'], $creadasCaps['C']]
);

$porCap = [];
foreach ($filasAbc as $fila) {
    $porCap[(int)$fila['capacitacion_id']][] = $fila;
}
ok(isset($porCap[$creadasCaps['A']]), 'Existe fila de A');
ok(isset($porCap[$creadasCaps['B']]), 'Existe fila de B');
ok(!isset($porCap[$creadasCaps['C']]) || (int)$porCap[$creadasCaps['C']][0]['activa'] === 0, 'C ausente o inactiva');
ok((int)$porCap[$creadasCaps['A']][0]['activa'] === 1, 'A queda activa');
ok((int)$porCap[$creadasCaps['B']][0]['activa'] === 1, 'B queda activa');
$vistaTrasSync = $matriz->vista($procesoGpId, 'FRONTERA');
$idsContexto = array_map(static fn (array $c): int => (int)$c['cargo_id'], $vistaTrasSync['cargos']);
ok(in_array($cargoId, $idsContexto, true), 'Tras marcar, el cargo entra en el contexto filtrado');

$aplicables = $matriz->aplicables($cargoId, $procesoGpId, 'FRONTERA');
$idsAplicables = array_map(static fn (array $i): int => (int)$i['capacitacion_id'], $aplicables['items']);
ok(in_array($creadasCaps['A'], $idsAplicables, true), 'aplicables incluye A');
ok(in_array($creadasCaps['B'], $idsAplicables, true), 'aplicables incluye B');
ok(!in_array($creadasCaps['C'], $idsAplicables, true), 'aplicables no incluye C');

$idB = (int)$porCap[$creadasCaps['B']][0]['matriz_aplicabilidad_id'];

echo "\n== Re-sincronizar quitando B ==\n";
$syncQuitaB = $matriz->sincronizar([
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
    'aplica' => [
        ['cargo_id_ext' => $cargoId, 'capacitacion_id' => $creadasCaps['A']],
    ],
], 1);
ok($syncQuitaB['inactivadas'] >= 1, 'Inactiva B al desmarcar');

$filaB = $db->fetch(
    'SELECT matriz_aplicabilidad_id, activa FROM matriz_aplicabilidad WHERE matriz_aplicabilidad_id = ?',
    [$idB]
);
ok($filaB !== null, 'B no se borra (historial)');
ok((int)$filaB['activa'] === 0, 'B queda inactiva');

$aplicablesTras = $matriz->aplicables($cargoId, $procesoGpId, 'FRONTERA');
$idsTras = array_map(static fn (array $i): int => (int)$i['capacitacion_id'], $aplicablesTras['items']);
ok(in_array($creadasCaps['A'], $idsTras, true), 'A sigue aplicable');
ok(!in_array($creadasCaps['B'], $idsTras, true), 'B ya no es aplicable');

echo "\n== Duplicado no crea segunda fila activa ==\n";
$antes = $db->fetch(
    'SELECT COUNT(*) AS total FROM matriz_aplicabilidad
     WHERE proceso_id = ? AND cargo_id_ext = ? AND capacitacion_id = ?
       AND proyecto COLLATE utf8mb4_unicode_ci = ? AND activa = 1',
    [$procesoGpId, $cargoId, $creadasCaps['A'], 'FRONTERA']
);
$matriz->sincronizar([
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
    'aplica' => [
        ['cargo_id_ext' => $cargoId, 'capacitacion_id' => $creadasCaps['A']],
    ],
], 1);
$despues = $db->fetch(
    'SELECT COUNT(*) AS total FROM matriz_aplicabilidad
     WHERE proceso_id = ? AND cargo_id_ext = ? AND capacitacion_id = ?
       AND proyecto COLLATE utf8mb4_unicode_ci = ? AND activa = 1',
    [$procesoGpId, $cargoId, $creadasCaps['A'], 'FRONTERA']
);
ok((int)$antes['total'] === 1, 'Había una fila activa de A');
ok((int)$despues['total'] === 1, 'Sigue habiendo una sola fila activa de A');

echo "\n== Proyecto ignorado si el proceso no es Gestión de Proyectos ==\n";
$syncOtro = $matriz->sincronizar([
    'proceso_id' => $procesoOtroId,
    'proyecto' => 'FRONTERA',
    'aplica' => [
        ['cargo_id_ext' => $cargoId, 'capacitacion_id' => $creadasCaps['A']],
    ],
], 1);
ok($syncOtro['vista']['proyecto'] === null, 'Vista de proceso no GP sin proyecto');
$filaOtro = $db->fetch(
    'SELECT proyecto FROM matriz_aplicabilidad
     WHERE proceso_id = ? AND cargo_id_ext = ? AND capacitacion_id = ?
     ORDER BY matriz_aplicabilidad_id DESC LIMIT 1',
    [$procesoOtroId, $cargoId, $creadasCaps['A']]
);
ok($filaOtro !== null, 'Se creó fila en el otro proceso');
ok($filaOtro['proyecto'] === null || trim((string)$filaOtro['proyecto']) === '', 'Proyecto ignorado fuera de Gestión de Proyectos');

echo "\n== Limpieza (inactivar, no borrar historial) ==\n";
$db->query(
    'UPDATE matriz_aplicabilidad SET activa = 0
     WHERE capacitacion_id IN (?, ?, ?)',
    [$creadasCaps['A'], $creadasCaps['B'], $creadasCaps['C']]
);
$enObra = array_map(
    static fn (array $c): int => (int)$c['cargo_id'],
    $personal->cargosDeContexto('FRONTERA', ['FRONTERA'])
);
$vistaLimpia = $matriz->vista($procesoGpId, 'FRONTERA');
$idsLimpia = array_map(static fn (array $c): int => (int)$c['cargo_id'], $vistaLimpia['cargos']);
if (!in_array($cargoId, $enObra, true)) {
    ok(!in_array($cargoId, $idsLimpia, true), 'Fila inactiva no arrastra el cargo a FRONTERA');
} else {
    ok(in_array($cargoId, $idsLimpia, true), 'El cargo sigue en FRONTERA porque hay gente vigente en esa obra');
}
foreach ($capIds as $id) {
    try {
        $caps->eliminar($id);
    } catch (Throwable $e) {
        $db->query('UPDATE capacitaciones SET estado = ? WHERE capacitacion_id = ?', ['INACTIVA', $id]);
    }
}

echo "Checklist RF-MA-036/037: OK\n";
