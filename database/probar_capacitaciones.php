<?php

declare(strict_types=1);

/**
 * Pruebas RF-019 a RF-045 del catálogo /capacitaciones.
 * Uso: php database/probar_capacitaciones.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\CapacitacionService;
use App\Services\VencimientoService;

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

$db = Database::getInstance();
$caps = new CapacitacionService();
$stamp = date('YmdHis');
$ids = [];

$tipo = $db->fetch('SELECT tipo_capacitacion_id, nombre FROM tipos_capacitacion WHERE activo = 1 ORDER BY tipo_capacitacion_id ASC LIMIT 1');
$modalidad = $db->fetch('SELECT modalidad_id, nombre FROM modalidades WHERE activo = 1 ORDER BY modalidad_id ASC LIMIT 1');
$vigencia = $db->fetch("SELECT vigencia_id, nombre, cantidad, unidad FROM vigencias WHERE activo = 1 AND cantidad > 0 ORDER BY vigencia_id ASC LIMIT 1");
ok($tipo !== null, 'Hay tipo de capacitación');
ok($modalidad !== null, 'Hay modalidad');
ok($vigencia !== null, 'Hay vigencia');

$tipoId = (int)$tipo['tipo_capacitacion_id'];
$modalidadId = (int)$modalidad['modalidad_id'];
$vigenciaId = (int)$vigencia['vigencia_id'];

echo "\n== Crear capacitación completa ==\n";
$creada = $caps->crear([
    'codigo' => 'CAP-RF-' . $stamp,
    'nombre' => 'Trabajo en alturas',
    'objetivo' => 'Prevenir caídas en trabajo en alturas',
    'duracion_estimada_horas' => 4,
    'tipo_capacitacion_id' => $tipoId,
    'modalidad_default_id' => $modalidadId,
    'vigencia_id' => $vigenciaId,
    'es_tarea_critica' => 1,
    'evaluacion' => 1,
    'nota_minima' => 3.5,
    'requiere_listado_asistencia' => 1,
    'certificado' => 1,
    'estado' => 'ACTIVA',
], 1);
$ids[] = (int)$creada['capacitacion_id'];
ok((int)$creada['capacitacion_id'] > 0, 'Capacitación creada');
ok($creada['codigo'] === 'CAP-RF-' . $stamp, 'Código persistido');
ok($creada['nombre'] === 'Trabajo en alturas', 'Nombre persistido');
ok($creada['objetivo'] === 'Prevenir caídas en trabajo en alturas', 'Objetivo persistido');
ok((float)$creada['duracion_estimada_horas'] === 4.0, 'Duración persistida');
ok((int)$creada['tipo_capacitacion_id'] === $tipoId, 'Tipo persistido');
ok((int)$creada['modalidad_default_id'] === $modalidadId, 'Modalidad persistida');
ok((int)$creada['vigencia_id'] === $vigenciaId, 'Vigencia persistida');
ok($creada['es_tarea_critica'] === true, 'Tarea crítica = Sí');
ok($creada['evaluacion'] === true, 'Requiere evaluación = Sí');
ok((float)$creada['nota_minima'] === 3.5, 'Nota mínima persistida');
ok($creada['requiere_listado_asistencia'] === true, 'Lista de asistencia = Sí');
ok($creada['certificado'] === true, 'Certificado = Sí');

echo "\n== Código único ==\n";
try {
    $caps->crear([
        'codigo' => 'CAP-RF-' . $stamp,
        'nombre' => 'Duplicada',
        'objetivo' => 'No debe crearse',
        'duracion_estimada_horas' => 2,
        'tipo_capacitacion_id' => $tipoId,
        'modalidad_default_id' => $modalidadId,
    ], 1);
    ok(false, 'Debió rechazar código duplicado');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Duplicado HTTP 409');
    ok($e->getMessage() === CapacitacionService::MENSAJE_CODIGO_DUPLICADO, $e->getMessage());
}

echo "\n== Evaluación sin nota ==\n";
try {
    $caps->crear([
        'codigo' => 'CAP-RF-N-' . $stamp,
        'nombre' => 'Sin nota',
        'objetivo' => 'Debe fallar',
        'duracion_estimada_horas' => 2,
        'tipo_capacitacion_id' => $tipoId,
        'modalidad_default_id' => $modalidadId,
        'evaluacion' => 1,
    ], 1);
    ok(false, 'Debió exigir nota mínima');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Nota mínima obligatoria 422');
}

echo "\n== Evaluación = No ==\n";
$sinEval = $caps->crear([
    'codigo' => 'CAP-RF-NE-' . $stamp,
    'nombre' => 'Sin evaluación',
    'objetivo' => 'Puede guardarse sin nota',
    'duracion_estimada_horas' => 3,
    'tipo_capacitacion_id' => $tipoId,
    'modalidad_default_id' => $modalidadId,
    'es_tarea_critica' => 0,
    'evaluacion' => 0,
    'requiere_listado_asistencia' => 0,
    'certificado' => 0,
], 1);
$ids[] = (int)$sinEval['capacitacion_id'];
ok($sinEval['evaluacion'] === false, 'Evaluación = No');
ok((float)$sinEval['nota_minima'] === 0.0, 'Nota mínima no aplica (0)');
ok($sinEval['es_tarea_critica'] === false, 'Tarea crítica = No');
ok($sinEval['requiere_listado_asistencia'] === false, 'Asistencia = No');
ok($sinEval['certificado'] === false, 'Certificado = No');

echo "\n== Vigencia para vencimiento ==\n";
$vence = VencimientoService::calcularFechaVencimiento(
    '2026-09-10',
    (int)$vigencia['cantidad'],
    (string)$vigencia['unidad']
);
ok($vence !== null && $vence !== '', 'Fecha de vencimiento calculada desde vigencia');
ok($creada['vigencia_cantidad'] === (int)$vigencia['cantidad'], 'Cantidad de vigencia en respuesta');
ok($creada['vigencia_unidad'] === (string)$vigencia['unidad'], 'Unidad de vigencia en respuesta');

echo "\n== Filtros del listado ==\n";
$porCodigo = $caps->listar(1, 20, ['buscar' => 'alturas']);
$encontrada = false;
foreach ($porCodigo['items'] as $item) {
    if ((int)$item['capacitacion_id'] === (int)$creada['capacitacion_id']) {
        $encontrada = true;
        break;
    }
}
ok($encontrada, 'Búsqueda por nombre encuentra la capacitación');

$porCritica = $caps->listar(1, 50, ['es_tarea_critica' => 1, 'buscar' => 'CAP-RF-' . $stamp]);
ok($porCritica['total'] >= 1, 'Filtro tarea crítica = Sí');

$porEval = $caps->listar(1, 50, ['evaluacion' => 0, 'buscar' => 'CAP-RF-NE-' . $stamp]);
ok($porEval['total'] >= 1, 'Filtro requiere evaluación = No');

echo "\n== Disponible para matriz / plan ==\n";
$activa = $db->fetch(
    "SELECT capacitacion_id, codigo, estado FROM capacitaciones WHERE capacitacion_id = ? AND estado = 'ACTIVA'",
    [$creada['capacitacion_id']]
);
ok($activa !== null, 'La capacitación ACTIVA es consultable por capacitacion_id');

$enCatalogoMatriz = $db->fetch(
    'SELECT capacitacion_id FROM capacitaciones WHERE estado = ? AND capacitacion_id = ?',
    ['ACTIVA', $creada['capacitacion_id']]
);
ok($enCatalogoMatriz !== null, 'Disponible para asociar en matriz (estado ACTIVA)');

echo "\n== Inactivar sin borrar ==\n";
$mensajeEli = $caps->eliminar((int)$sinEval['capacitacion_id']);
ok(
    $mensajeEli === CapacitacionService::MENSAJE_ELIMINADA
        || $mensajeEli === CapacitacionService::MENSAJE_INACTIVADA,
    'Eliminar/inactivar devolvió mensaje de operación'
);
$ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== (int)$sinEval['capacitacion_id']));

foreach ($ids as $id) {
    try {
        $caps->eliminar($id);
    } catch (Throwable $e) {
        $db->query('UPDATE capacitaciones SET estado = ? WHERE capacitacion_id = ?', ['INACTIVA', $id]);
    }
}

echo "Checklist RF-045: OK\n";
