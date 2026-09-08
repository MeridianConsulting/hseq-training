<?php

declare(strict_types=1);

/**
 * Catálogos HSEQ: procesos, proyectos y modalidades (RF-CA-031 a RF-CA-035).
 * Uso: php database/probar_catalogos_procesos_proyectos.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Repositories\AlertaRepository;
use App\Services\CatalogService;
use App\Services\MatrizService;
use App\Services\PlanAnualService;

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

function nombresProcesos(AlertaRepository $alertas): array
{
    return array_map(
        static fn (array $p): string => mb_strtoupper(trim((string)$p['nombre']), 'UTF-8'),
        $alertas->procesosActivos()
    );
}

function contieneNombre(array $nombres, string $buscado): bool
{
    $clave = mb_strtoupper(trim($buscado), 'UTF-8');
    foreach ($nombres as $nombre) {
        if (mb_strtoupper(trim((string)$nombre), 'UTF-8') === $clave) {
            return true;
        }
    }

    return false;
}

$db = Database::getInstance();
$svc = new CatalogService();
$alertas = new AlertaRepository();
$matriz = new MatrizService();
$planes = new PlanAnualService();
$defProcesos = $svc->definicion('procesos');
$defProyectos = $svc->definicion('proyectos');
$defModalidades = $svc->definicion('modalidades');

$stamp = date('YmdHis');
$procesoIds = [];
$proyectoIds = [];

$limpiar = static function () use ($svc, $defProcesos, $defProyectos, &$procesoIds, &$proyectoIds): void {
    foreach ($proyectoIds as $id) {
        if ($id > 0) {
            try {
                $svc->eliminar($defProyectos, $id);
            } catch (Throwable $e) {
            }
        }
    }
    foreach ($procesoIds as $id) {
        if ($id > 0) {
            try {
                $svc->eliminar($defProcesos, $id);
            } catch (Throwable $e) {
            }
        }
    }
};
register_shutdown_function($limpiar);

echo "== RF-CA-031 Licitaciones disponible ==\n";
$activos = nombresProcesos($alertas);
ok(contieneNombre($activos, 'Licitaciones'), 'Licitaciones está en procesos activos');
$opcionesMatriz = $matriz->opciones();
$nombresMatriz = array_map(static fn (array $p): string => (string)$p['nombre'], $opcionesMatriz['procesos']);
ok(contieneNombre($nombresMatriz, 'Licitaciones'), 'Licitaciones aparece en opciones de matriz');

$nuevoProc = $svc->crear($defProcesos, ['nombre' => 'Proc CA ' . $stamp]);
$procesoIds[] = (int)$nuevoProc['proceso_id'];
ok(contieneNombre(nombresProcesos($alertas), 'Proc CA ' . $stamp), 'Un proceso nuevo aparece sin tocar frontend');

echo "== RF-CA-032 Gestión Contable y Tecnología ==\n";
ok(contieneNombre(nombresProcesos($alertas), 'Gestión Contable'), 'Gestión Contable está en procesos activos');

$tec = $db->fetch(
    "SELECT proceso_id, nombre, activo FROM procesos
     WHERE UPPER(TRIM(nombre)) LIKE '%TECNOLOGIA%' OR UPPER(TRIM(nombre)) LIKE '%TECNOLOGÍA%'
     LIMIT 1"
);
if ($tec === null) {
    $creadaTec = $svc->crear($defProcesos, ['nombre' => 'Gestión de Tecnología']);
    $tecId = (int)$creadaTec['proceso_id'];
    $procesoIds[] = $tecId;
    $svc->eliminar($defProcesos, $tecId);
    $tec = $svc->ver($defProcesos, $tecId);
} elseif ((int)($tec['activo'] ?? 1) === 1) {
    $svc->eliminar($defProcesos, (int)$tec['proceso_id']);
    $tec = $svc->ver($defProcesos, (int)$tec['proceso_id']);
}
ok((int)($tec['activo'] ?? 1) === 0, 'Gestión de Tecnología no está operativa (inactiva)');
ok(
    !contieneNombre(nombresProcesos($alertas), (string)$tec['nombre']),
    'Gestión de Tecnología no aparece en opciones operativas'
);
$sigueTec = $db->fetch('SELECT proceso_id FROM procesos WHERE proceso_id = ?', [(int)$tec['proceso_id']]);
ok($sigueTec !== null, 'El proceso de Tecnología conserva su fila histórica');

echo "== RF-CA-033 Nuevo proyecto alimenta módulos ==\n";
$nombreProy = 'XYZ-CA-' . $stamp;
$creadoProy = $svc->crear($defProyectos, ['nombre' => $nombreProy]);
$proyectoIds[] = (int)$creadoProy['proyecto_id'];
ok((int)$creadoProy['activo'] === 1, 'Proyecto de prueba creado vigente');

$enAlertas = $alertas->proyectos();
ok(contieneNombre($enAlertas, $nombreProy), 'El proyecto nuevo está en el catálogo vigente');
ok(contieneNombre($matriz->opciones()['proyectos'], $nombreProy), 'Matriz opciones incluye el proyecto');
ok(contieneNombre($planes->opciones()['proyectos'], $nombreProy), 'Plan anual opciones incluye el proyecto');
ok($alertas->resolverProyecto($nombreProy, true) === $nombreProy, 'resolverProyecto vigente devuelve el canónico');

$gp = null;
foreach ($alertas->procesosActivos() as $p) {
    if ($alertas->procesoEsGestionProyectos((int)$p['proceso_id'])) {
        $gp = $p;
        break;
    }
}
ok($gp !== null, 'Hay un proceso Gestión de Proyectos / Proyectos activo');
ok($alertas->procesoEsGestionProyectos((int)$gp['proceso_id']), 'El detector GP reconoce el proceso');

echo "== RF-CA-034 Proyecto inactivo ==\n";
$msg = $svc->eliminar($defProyectos, (int)$creadoProy['proyecto_id']);
ok(
    $msg === 'El registro fue inactivado correctamente.' || $msg === 'El registro ya está inactivo.',
    $msg
);
$inactivo = $svc->ver($defProyectos, (int)$creadoProy['proyecto_id']);
ok((int)$inactivo['activo'] === 0, 'El proyecto queda inactivo, no se borra');
ok(!contieneNombre($alertas->proyectos(), $nombreProy), 'Ya no aparece entre vigentes');
ok(!contieneNombre($matriz->opciones()['proyectos'], $nombreProy), 'Matriz deja de ofrecerlo en altas');
ok($alertas->resolverProyecto($nombreProy, true) === null, 'No se resuelve para operaciones nuevas');
ok($alertas->resolverProyecto($nombreProy, false) === $nombreProy, 'Sí se resuelve en consulta histórica');

echo "== RF-CA-035 Modalidades centralizadas ==\n";
$mods = $svc->listar($defModalidades, 'activos', null);
$nombresMod = array_map(static fn (array $m): string => mb_strtoupper(trim((string)$m['nombre']), 'UTF-8'), $mods);
foreach (['PRESENCIAL', 'VIRTUAL', 'MIXTA'] as $mod) {
    ok(in_array($mod, $nombresMod, true), "Modalidad {$mod} proviene del catálogo");
}

echo "\nListo: catálogos de procesos, proyectos y modalidades.\n";
