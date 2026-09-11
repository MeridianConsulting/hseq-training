<?php

declare(strict_types=1);

/**
 * Tipos del Excel (inducción, reinducción, obligatoria, técnica, bienestar)
 * más capacitación general. Tarea crítica queda en el Sí/No de la capacitación.
 * Uso: php database/21_tipos_excel.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(BASE_PATH);

$db = Database::getInstance();

$oficiales = [
    'INDUCCION' => 'Inducción al cargo / ingreso',
    'REINDUCCION' => 'Reinducción periódica',
    'OBLIGATORIA' => 'Capacitación obligatoria del programa HSEQ',
    'TECNICA' => 'Capacitación técnica',
    'BIENESTAR' => 'Capacitación de bienestar',
    'CAPACITACION GENERAL' => 'Capacitación general del programa HSEQ',
];

foreach ($oficiales as $nombre => $descripcion) {
    $fila = $db->fetch(
        'SELECT tipo_capacitacion_id FROM tipos_capacitacion WHERE LOWER(TRIM(nombre)) = LOWER(?) LIMIT 1',
        [$nombre]
    );
    if ($fila === null) {
        $db->insert('tipos_capacitacion', [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'activo' => 1,
        ]);
        echo "Creado {$nombre}\n";
        continue;
    }
    $db->update(
        'tipos_capacitacion',
        ['nombre' => $nombre, 'descripcion' => $descripcion, 'activo' => 1],
        'tipo_capacitacion_id = ?',
        [(int)$fila['tipo_capacitacion_id']]
    );
}

$obligatoria = $db->fetch(
    "SELECT tipo_capacitacion_id FROM tipos_capacitacion
     WHERE activo = 1 AND LOWER(TRIM(nombre)) = 'obligatoria' LIMIT 1"
);
if ($obligatoria === null) {
    fwrite(STDERR, "No hay tipo OBLIGATORIA activo.\n");
    exit(1);
}
$obligatoriaId = (int)$obligatoria['tipo_capacitacion_id'];

$criticos = $db->fetchAll(
    "SELECT tipo_capacitacion_id, nombre FROM tipos_capacitacion
     WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            UPPER(TRIM(nombre)), 'Á','A'), 'É','E'), 'Í','I'), 'Ó','O'), 'Ú','U')
           = 'TAREA CRITICA'"
);

foreach ($criticos as $tipo) {
    $id = (int)$tipo['tipo_capacitacion_id'];
    $db->query(
        'UPDATE capacitaciones SET tipo_capacitacion_id = ?, es_tarea_critica = 1 WHERE tipo_capacitacion_id = ?',
        [$obligatoriaId, $id]
    );
    $db->update('tipos_capacitacion', ['activo' => 0], 'tipo_capacitacion_id = ?', [$id]);
    echo "Inactivado {$tipo['nombre']} (#{$id}); cursos → OBLIGATORIA + tarea crítica Sí\n";
}

echo "== tipos activos ==\n";
foreach ($db->fetchAll(
    'SELECT nombre FROM tipos_capacitacion WHERE activo = 1 ORDER BY nombre'
) as $fila) {
    echo $fila['nombre'] . "\n";
}
