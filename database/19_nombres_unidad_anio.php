<?php

declare(strict_types=1);

/**
 * Renombra ANIO/ANIOS a AÑO/AÑOS en vigencias y periodicidades.
 * Uso: php database/19_nombres_unidad_anio.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(BASE_PATH);

$db = Database::getInstance();

/** @var array<string,string> $tablas */
$tablas = [
    'vigencias' => 'vigencia_id',
    'periodicidades' => 'periodicidad_id',
];

foreach ($tablas as $tabla => $pk) {
    $filas = $db->fetchAll("SELECT {$pk} AS id, nombre FROM {$tabla}");
    foreach ($filas as $fila) {
        $antes = (string)$fila['nombre'];
        $nuevo = humanizar_nombre_unidad($antes);
        if ($nuevo === null || $nuevo === $antes) {
            continue;
        }
        $existe = $db->fetch(
            "SELECT {$pk} AS id FROM {$tabla} WHERE nombre = ? AND {$pk} <> ?",
            [$nuevo, (int)$fila['id']]
        );
        if ($existe !== null) {
            echo "Omitido {$tabla} #{$fila['id']}: ya existe '{$nuevo}'\n";
            continue;
        }
        $db->update($tabla, ['nombre' => $nuevo], "{$pk} = ?", [(int)$fila['id']]);
        echo "{$tabla}: {$antes} -> {$nuevo}\n";
    }
}

echo "== vigencias ==\n";
foreach ($db->fetchAll('SELECT nombre, unidad FROM vigencias WHERE activo = 1 ORDER BY nombre') as $fila) {
    echo $fila['nombre'] . ' (' . $fila['unidad'] . ")\n";
}
echo "== periodicidades ==\n";
foreach ($db->fetchAll('SELECT nombre, unidad FROM periodicidades WHERE activo = 1 ORDER BY nombre') as $fila) {
    echo $fila['nombre'] . ' (' . $fila['unidad'] . ")\n";
}
