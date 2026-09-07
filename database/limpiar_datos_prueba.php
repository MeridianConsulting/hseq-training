<?php

/**
 * Limpia datos de prueba y deja catálogos + capacitaciones del programa.
 * Uso: php database/limpiar_datos_prueba.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(BASE_PATH);

$db = Database::getInstance();
$personal = Database::personal();

$quedan = [
    'HSEQ-IND-001',
    'HSEQ-REI-001',
    'HSEQ-ALT-001',
    'HSEQ-ESP-001',
    'HSEQ-EME-001',
    'HSEQ-PAX-001',
    'HSEQ-RUC-001',
    'HSEQ-ISO-001',
];

echo "Antes:\n";
echo '  capacitaciones=' . ($db->fetch('SELECT COUNT(*) AS c FROM capacitaciones')['c'] ?? 0) . "\n";
echo '  matriz=' . ($db->fetch('SELECT COUNT(*) AS c FROM matriz_aplicabilidad')['c'] ?? 0) . "\n";
echo '  asignaciones=' . ($db->fetch('SELECT COUNT(*) AS c FROM asignaciones_capacitacion')['c'] ?? 0) . "\n";

$rutas = $db->fetchAll('SELECT ruta_archivo FROM soportes_cumplimiento');
$upload = rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/storage/uploads';
foreach ($rutas as $fila) {
    $rel = str_replace('\\', '/', (string)$fila['ruta_archivo']);
    $abs = $rel;
    if (!preg_match('/^[A-Za-z]:\//', $rel) && !str_starts_with($rel, '/')) {
        $abs = $upload . '/' . ltrim($rel, '/');
    }
    if (is_file($abs)) {
        @unlink($abs);
    }
}

$db->query('SET FOREIGN_KEY_CHECKS=0');
foreach (
    [
        'soportes_cumplimiento',
        'cumplimientos_capacitacion',
        'sesion_participantes',
        'plan_detalle_asignaciones',
        'asignaciones_capacitacion',
        'sesiones_capacitacion',
        'plan_anual_detalle',
        'planes_anuales',
        'matriz_aplicabilidad',
        'auditoria',
    ] as $tabla
) {
    $db->query("DELETE FROM {$tabla}");
}

$placeholders = implode(',', array_fill(0, count($quedan), '?'));
$db->query(
    "DELETE FROM capacitaciones WHERE codigo NOT IN ({$placeholders})",
    $quedan
);
$db->query('SET FOREIGN_KEY_CHECKS=1');

$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$docsPrueba = ['9000880088'];
foreach ($docsPrueba as $doc) {
    $prev = $personal->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
    if ($prev === null) {
        continue;
    }
    $pid = (int)$prev['persona_id'];
    $db->query('DELETE FROM historial_contexto_trabajador WHERE persona_id_ext = ?', [$pid]);
    $personal->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
    $personal->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
    echo "Persona de prueba eliminada: {$doc}\n";
}

echo "Después:\n";
echo '  capacitaciones=' . ($db->fetch('SELECT COUNT(*) AS c FROM capacitaciones')['c'] ?? 0) . "\n";
foreach ($db->fetchAll('SELECT codigo, nombre, estado FROM capacitaciones ORDER BY codigo') as $r) {
    echo '    ' . $r['codigo'] . ' — ' . $r['nombre'] . "\n";
}
echo '  matriz=' . ($db->fetch('SELECT COUNT(*) AS c FROM matriz_aplicabilidad')['c'] ?? 0) . "\n";
echo '  asignaciones=' . ($db->fetch('SELECT COUNT(*) AS c FROM asignaciones_capacitacion')['c'] ?? 0) . "\n";
echo '  sesiones=' . ($db->fetch('SELECT COUNT(*) AS c FROM sesiones_capacitacion')['c'] ?? 0) . "\n";
echo '  planes=' . ($db->fetch('SELECT COUNT(*) AS c FROM planes_anuales')['c'] ?? 0) . "\n";
echo "Listo.\n";
