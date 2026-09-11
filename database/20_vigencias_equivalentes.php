<?php

declare(strict_types=1);

/**
 * Un año = 12 meses y dos años = 24 meses: deja la vigencia en AÑOS.
 * Uso: php database/20_vigencias_equivalentes.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(BASE_PATH);

$db = Database::getInstance();

$filas = $db->fetchAll(
    'SELECT vigencia_id, nombre, cantidad, unidad, activo FROM vigencias WHERE activo = 1'
);

$grupos = [];
foreach ($filas as $fila) {
    $meses = meses_equivalentes($fila['cantidad'], $fila['unidad']);
    if ($meses === null) {
        continue;
    }
    $grupos[$meses][] = $fila;
}

foreach ($grupos as $meses => $items) {
    if (count($items) < 2) {
        continue;
    }
    usort($items, static function (array $a, array $b): int {
        $ua = strtoupper((string)$a['unidad']);
        $ub = strtoupper((string)$b['unidad']);
        if ($ua === 'ANIOS' && $ub !== 'ANIOS') {
            return -1;
        }
        if ($ub === 'ANIOS' && $ua !== 'ANIOS') {
            return 1;
        }

        return (int)$a['vigencia_id'] <=> (int)$b['vigencia_id'];
    });
    $keeper = $items[0];
    $keeperId = (int)$keeper['vigencia_id'];
    echo 'Canónica ' . $meses . ' meses: ' . $keeper['nombre'] . " (#{$keeperId})\n";
    for ($i = 1, $n = count($items); $i < $n; $i++) {
        $dup = $items[$i];
        $dupId = (int)$dup['vigencia_id'];
        $db->query(
            'UPDATE capacitaciones SET vigencia_id = ? WHERE vigencia_id = ?',
            [$keeperId, $dupId]
        );
        $db->update('vigencias', ['activo' => 0], 'vigencia_id = ?', [$dupId]);
        echo '  Inactiva ' . $dup['nombre'] . " (#{$dupId}) → {$keeper['nombre']}\n";
    }
}

echo "== activas ==\n";
foreach ($db->fetchAll(
    'SELECT nombre, cantidad, unidad FROM vigencias WHERE activo = 1 ORDER BY
     CASE unidad WHEN \'ANIOS\' THEN cantidad * 12 WHEN \'MESES\' THEN cantidad ELSE 9999 END, nombre'
) as $fila) {
    echo $fila['nombre'] . ' (' . $fila['cantidad'] . ' ' . $fila['unidad'] . ")\n";
}
