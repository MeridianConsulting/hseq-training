<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\CatalogService;

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

function asegurarActivo(Database $db, string $tabla): void
{
    $col = $db->fetch("SHOW COLUMNS FROM `{$tabla}` LIKE 'activo'");
    if ($col !== null) {
        echo "Columna activo ya existe en {$tabla}\n";
        return;
    }
    try {
        $db->getConnection()->exec(
            "ALTER TABLE `{$tabla}` ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER nombre"
        );
        echo "Columna activo agregada en {$tabla}\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo "Columna activo ya existe en {$tabla}\n";
            return;
        }
        throw $e;
    }
}

echo "== DDL activo ==\n";
asegurarActivo($db, 'areas');
asegurarActivo($db, 'procesos');
asegurarActivo($db, 'roles');

$svc = new CatalogService();
$defCat = $svc->definicion('categorias');
$defAreas = $svc->definicion('areas');
$defRoles = $svc->definicion('roles');

echo "\n== Tipos del panel ==\n";
$tipos = array_column($svc->tiposDisponibles(), 'tipo');
foreach (['categorias', 'areas', 'procesos', 'roles', 'vigencias', 'fuentes-normativas'] as $tipo) {
    ok(in_array($tipo, $tipos, true), "Catalogo {$tipo} disponible");
}

echo "\n== Duplicado con espacios ==\n";
$nombreDup = 'HSEQ Catalogo Prueba ' . date('His');
$a = $svc->crear($defCat, ['nombre' => ' ' . $nombreDup . ' ']);
ok(isset($a['categoria_id']), 'Categoria creada');
ok((string)$a['nombre'] === $nombreDup, 'Nombre recortado al guardar');
try {
    $svc->crear($defCat, ['nombre' => $nombreDup]);
    ok(false, 'Debio rechazar duplicado');
} catch (HttpException $e) {
    ok($e->getMessage() === 'Ya existe un registro con este nombre.', $e->getMessage());
}

echo "\n== Inactivar conserva capacitaciones ==\n";
$cap = $db->fetch('SELECT capacitacion_id, categoria_id FROM capacitaciones WHERE categoria_id IS NOT NULL LIMIT 1');
if ($cap === null) {
    echo "Sin capacitaciones con categoria; se omite cruce historico.\n";
} else {
    $catId = (int)$cap['categoria_id'];
    $antes = $svc->ver($defCat, $catId);
    $estabaActivo = (int)($antes['activo'] ?? 1) === 1;
    $debeRestaurar = false;
    try {
        $deps = $svc->contarDependencias($defCat, $catId);
        ok($deps['total'] > 0, 'Categoria con capacitaciones asociadas');
        $msg = $svc->eliminar($defCat, $catId);
        $debeRestaurar = $estabaActivo;
        ok($msg === 'El registro fue inactivado correctamente.' || $msg === 'El registro ya está inactivo.', $msg);
        $despues = $svc->ver($defCat, $catId);
        ok((int)$despues['activo'] === 0, 'Categoria inactiva en BD');
        $sigue = $db->fetch(
            'SELECT capacitacion_id FROM capacitaciones WHERE capacitacion_id = ? AND categoria_id = ?',
            [(int)$cap['capacitacion_id'], $catId]
        );
        ok($sigue !== null, 'La capacitacion conserva categoria_id');
        $activos = $svc->listar($defCat, 'activos', null);
        $ids = array_map(static fn (array $f): int => (int)$f['categoria_id'], $activos);
        ok(!in_array($catId, $ids, true), 'Inactiva no aparece en listado activos');
        if ($estabaActivo) {
            $re = $svc->reactivar($defCat, $catId);
            $debeRestaurar = false;
            ok((int)$re['categoria_id'] === $catId, 'Reactivada el mismo registro');
            ok((int)$re['activo'] === 1, 'Queda activa');
            $mismoNombre = $db->fetch(
                'SELECT COUNT(*) AS total FROM categorias_capacitacion WHERE nombre = ?',
                [$antes['nombre']]
            );
            ok((int)$mismoNombre['total'] === 1, 'Reactivar no crea un segundo registro');
        }
        echo 'Nombre original conservado: ' . $antes['nombre'] . "\n";
    } finally {
        if ($debeRestaurar) {
            $svc->reactivar($defCat, $catId);
        }
    }
}

echo "\n== Area inactivar ==\n";
$area = $svc->crear($defAreas, ['nombre' => 'Area prueba inactivar ' . date('His')]);
$areaId = (int)$area['area_id'];
$msgArea = $svc->eliminar($defAreas, $areaId);
ok($msgArea === 'El registro fue inactivado correctamente.', $msgArea);
ok((int)$svc->ver($defAreas, $areaId)['activo'] === 0, 'Area inactiva');
$areasActivas = array_map(static fn (array $f): int => (int)$f['area_id'], $svc->listar($defAreas, 'activos', null));
ok(!in_array($areaId, $areasActivas, true), 'Area inactiva no sale en altas');
$areasInactivas = array_map(static fn (array $f): int => (int)$f['area_id'], $svc->listar($defAreas, 'inactivos', null));
ok(in_array($areaId, $areasInactivas, true), 'Area inactiva aparece en filtro inactivos');
$svc->reactivar($defAreas, $areaId);

echo "\n== Rol administrador unico ==\n";
$nAdmin = $db->fetch(
    "SELECT COUNT(*) AS total FROM roles
     WHERE activo = 1 AND LOWER(TRIM(nombre)) IN ('administrador hseq', 'admin')"
);
$admin = $db->fetch(
    "SELECT role_id FROM roles
     WHERE activo = 1 AND LOWER(TRIM(nombre)) IN ('administrador hseq', 'admin')
     LIMIT 1"
);
if ($admin !== null && (int)($nAdmin['total'] ?? 0) === 1) {
    try {
        $svc->eliminar($defRoles, (int)$admin['role_id']);
        ok(false, 'Debio bloquear inactivar el unico Administrador HSEQ');
    } catch (HttpException $e) {
        ok($e->getCode() === 409, $e->getMessage());
        ok((int)$svc->ver($defRoles, (int)$admin['role_id'])['activo'] === 1, 'El rol admin sigue activo');
    }
} else {
    echo "No hay un unico rol admin activo; se omite el bloqueo.\n";
}

echo "\n== Integridad de tablas ==\n";
$db->fetchAll('SELECT capacitacion_id FROM capacitaciones LIMIT 1');
$db->fetchAll('SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad LIMIT 1');
$db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion LIMIT 1');
ok(true, 'capacitaciones, matriz y asignaciones siguen consultables');

echo "\n== DELETE fisico no ocurre ==\n";
$conteo = $db->fetch('SELECT COUNT(*) AS total FROM categorias_capacitacion WHERE nombre = ?', [$nombreDup]);
ok((int)$conteo['total'] === 1, 'El duplicado de prueba sigue existiendo (no se borro)');
$svc->eliminar($defCat, (int)$a['categoria_id']);
$svc->eliminar($defAreas, $areaId);
$db->query("UPDATE categorias_capacitacion SET activo = 0 WHERE nombre LIKE 'HSEQ Catalogo Prueba %'");
$db->query("UPDATE areas SET activo = 0 WHERE nombre LIKE 'Area prueba inactivar %'");

echo "\nPruebas de catalogos OK.\n";
