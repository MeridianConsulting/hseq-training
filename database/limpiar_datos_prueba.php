<?php

/**
 * Borra cursos, planes, sesiones y personas creados por las pruebas.
 * Conserva catálogos y las capacitaciones oficiales HSEQ-*.
 * Uso: php database/limpiar_datos_prueba.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(BASE_PATH);

$db = Database::getInstance();
$personal = Database::personal();
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$cargosT = Database::personalTable('cargos');

$oficiales = [
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
echo '  sesiones=' . ($db->fetch('SELECT COUNT(*) AS c FROM sesiones_capacitacion')['c'] ?? 0) . "\n";
echo '  planes=' . ($db->fetch('SELECT COUNT(*) AS c FROM planes_anuales')['c'] ?? 0) . "\n";

$phOficial = implode(',', array_fill(0, count($oficiales), '?'));
$pruebaCaps = $db->fetchAll(
    "SELECT capacitacion_id, codigo, nombre FROM capacitaciones WHERE codigo NOT IN ({$phOficial})",
    $oficiales
);
$idsPrueba = array_map(static fn (array $r): int => (int)$r['capacitacion_id'], $pruebaCaps);

echo '  cursos de prueba a borrar=' . count($idsPrueba) . "\n";
foreach ($pruebaCaps as $cap) {
    echo '    ' . $cap['codigo'] . ' — ' . $cap['nombre'] . "\n";
}

if ($idsPrueba !== []) {
    $ph = implode(',', array_fill(0, count($idsPrueba), '?'));

    $sesiones = $db->fetchAll(
        "SELECT sesion_id FROM sesiones_capacitacion WHERE capacitacion_id IN ({$ph})",
        $idsPrueba
    );
    $sesionIds = array_map(static fn (array $r): int => (int)$r['sesion_id'], $sesiones);

    $asigs = $db->fetchAll(
        "SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id IN ({$ph})",
        $idsPrueba
    );
    $asigIds = array_map(static fn (array $r): int => (int)$r['asignacion_id'], $asigs);

    $cumpIds = [];
    if ($asigIds !== []) {
        $phAsig = implode(',', array_fill(0, count($asigIds), '?'));
        $cumps = $db->fetchAll(
            "SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id IN ({$phAsig})",
            $asigIds
        );
        foreach ($cumps as $c) {
            $cumpIds[(int)$c['cumplimiento_id']] = (int)$c['cumplimiento_id'];
        }
    }
    if ($sesionIds !== []) {
        $phSes = implode(',', array_fill(0, count($sesionIds), '?'));
        $cumpsSes = $db->fetchAll(
            "SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE sesion_id IN ({$phSes})",
            $sesionIds
        );
        foreach ($cumpsSes as $c) {
            $cumpIds[(int)$c['cumplimiento_id']] = (int)$c['cumplimiento_id'];
        }
    }

    $upload = rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/storage/uploads';
    if ($cumpIds !== []) {
        $phCump = implode(',', array_fill(0, count($cumpIds), '?'));
        $rutas = $db->fetchAll(
            "SELECT ruta_archivo FROM soportes_cumplimiento WHERE cumplimiento_id IN ({$phCump})",
            array_values($cumpIds)
        );
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
        $db->query(
            "DELETE FROM soportes_cumplimiento WHERE cumplimiento_id IN ({$phCump})",
            array_values($cumpIds)
        );
        $db->query(
            "DELETE FROM cumplimientos_capacitacion WHERE cumplimiento_id IN ({$phCump})",
            array_values($cumpIds)
        );
    }

    if ($sesionIds !== []) {
        $phSes = implode(',', array_fill(0, count($sesionIds), '?'));
        $db->query("DELETE FROM sesion_participantes WHERE sesion_id IN ({$phSes})", $sesionIds);
        $db->query("DELETE FROM sesiones_capacitacion WHERE sesion_id IN ({$phSes})", $sesionIds);
    }

    if ($asigIds !== []) {
        $phAsig = implode(',', array_fill(0, count($asigIds), '?'));
        $db->query("DELETE FROM plan_detalle_asignaciones WHERE asignacion_id IN ({$phAsig})", $asigIds);
        $db->query("DELETE FROM asignaciones_capacitacion WHERE asignacion_id IN ({$phAsig})", $asigIds);
    }

    $db->query("DELETE FROM plan_anual_detalle WHERE capacitacion_id IN ({$ph})", $idsPrueba);
    $db->query("DELETE FROM matriz_aplicabilidad WHERE capacitacion_id IN ({$ph})", $idsPrueba);
    $db->query("DELETE FROM capacitaciones WHERE capacitacion_id IN ({$ph})", $idsPrueba);
}

$vacios = $db->fetchAll(
    'SELECT p.plan_anual_id
     FROM planes_anuales p
     LEFT JOIN plan_anual_detalle d ON d.plan_anual_id = p.plan_anual_id
     GROUP BY p.plan_anual_id
     HAVING COUNT(d.plan_detalle_id) = 0'
);
foreach ($vacios as $plan) {
    $db->query('DELETE FROM planes_anuales WHERE plan_anual_id = ?', [(int)$plan['plan_anual_id']]);
}

$pruebas = $personal->fetchAll(
    "SELECT persona_id, numero_documento
     FROM {$personasT}
     WHERE correo_corporativo LIKE '%@hseq.test'
        OR nombre_completo_nombres_primero LIKE '%Prueba%'
        OR numero_documento IN (
            '9000880088','9000880101','9000770301','9000770302',
            '9000999999','9000777001','9000888001','9000888002',
            '9000888003','9000888004','9000888005'
        )"
);
foreach ($pruebas as $p) {
    $pid = (int)$p['persona_id'];
    $db->query('DELETE FROM historial_contexto_trabajador WHERE persona_id_ext = ?', [$pid]);
    $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
    foreach ($asigs as $a) {
        $aid = (int)$a['asignacion_id'];
        $cumps = $db->fetchAll('SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ?', [$aid]);
        foreach ($cumps as $c) {
            $cid = (int)$c['cumplimiento_id'];
            $db->query('DELETE FROM soportes_cumplimiento WHERE cumplimiento_id = ?', [$cid]);
            $db->query('DELETE FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?', [$cid]);
        }
        $db->query('DELETE FROM sesion_participantes WHERE asignacion_id = ?', [$aid]);
        $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [$aid]);
        $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [$aid]);
    }
    $personal->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
    $personal->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
    echo 'Persona de prueba eliminada: ' . $p['numero_documento'] . "\n";
}

$cargosPrueba = $personal->fetchAll(
    "SELECT cargo_id, nombre_cargo FROM {$cargosT}
     WHERE nombre_cargo LIKE '%PRUEBA%' OR nombre_cargo LIKE '%HSEQ TEST%'"
);
foreach ($cargosPrueba as $cargo) {
    $cid = (int)$cargo['cargo_id'];
    $uso = $personal->fetch("SELECT COUNT(*) AS n FROM {$personasT} WHERE cargo_id = ?", [$cid]);
    if ((int)($uso['n'] ?? 0) > 0) {
        echo 'Cargo de prueba en uso, no se borra: ' . $cargo['nombre_cargo'] . "\n";
        continue;
    }
    $personal->query("DELETE FROM {$cargosT} WHERE cargo_id = ?", [$cid]);
    echo 'Cargo de prueba eliminado: ' . $cargo['nombre_cargo'] . "\n";
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
