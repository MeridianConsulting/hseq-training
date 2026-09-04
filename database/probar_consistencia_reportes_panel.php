<?php

declare(strict_types=1);

/**
 * Validación RF-REP-026: consistencia Panel vs Reportes (cumplimiento general)
 * y checklist básico de reportes.
 * Uso: php database/probar_consistencia_reportes_panel.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\DashboardService;
use App\Services\ReporteService;

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

$anio = (int)date('Y');
$desde = sprintf('%04d-01-01', $anio);
$hasta = sprintf('%04d-12-31', $anio);

$dashboard = new DashboardService();
$reportes = new ReporteService();

$panel = $dashboard->indicadores([
    'tipo' => 'anual',
    'anio' => $anio,
]);

$reporte = $reportes->consultar('cumplimiento_general', [
    'desde' => $desde,
    'hasta' => $hasta,
], 1, 5);

$panelProg = (int)$panel['cobertura']['general']['programado'];
$panelEjec = (int)$panel['cobertura']['general']['ejecutado'];
$panelPct = $panel['cobertura']['general']['porcentaje'];

$repProg = (int)($reporte['totales']['programadas'] ?? $reporte['totales']['asignadas']);
$repEjec = (int)($reporte['totales']['ejecutadas'] ?? $reporte['totales']['completadas']);
$repPct = $reporte['totales']['porcentaje'];

ok($repProg === $panelProg, "Programadas Panel={$panelProg} Reportes={$repProg}");
ok($repEjec === $panelEjec, "Ejecutadas Panel={$panelEjec} Reportes={$repEjec}");
ok($repPct === $panelPct, "Porcentaje Panel=" . var_export($panelPct, true) . ' Reportes=' . var_export($repPct, true));

$opciones = $reportes->opciones();
ok(isset($opciones['procesos']) && is_array($opciones['procesos']), 'Opciones incluyen procesos');
ok(isset($opciones['proyectos']) && is_array($opciones['proyectos']), 'Opciones incluyen proyectos');

$nombresProc = [];
foreach ($opciones['procesos'] as $p) {
    $nombresProc[] = mb_strtoupper((string)$p['nombre'], 'UTF-8');
}
$basura = false;
foreach ($nombresProc as $n) {
    if (str_contains($n, 'HSEQ-REP-') || str_contains($n, 'HSEQ-AL-')) {
        $basura = true;
        break;
    }
}
ok(!$basura, 'Procesos de opciones sin residuos de pruebas');

$trabajador = $reportes->consultar('cumplimiento_trabajador', [
    'desde' => $desde,
    'hasta' => $hasta,
], 1, 5);
ok(isset($trabajador['items']), 'Cumplimiento por trabajador responde');
if ($trabajador['items'] !== []) {
    $fila = $trabajador['items'][0];
    ok(array_key_exists('programadas', $fila), 'Fila trabajador tiene programadas');
    ok(array_key_exists('ejecutadas', $fila), 'Fila trabajador tiene ejecutadas');
    ok(array_key_exists('persona_id_ext', $fila), 'Fila trabajador agregada por persona');
}

$cargo = $reportes->consultar('cumplimiento_cargo', [
    'desde' => $desde,
    'hasta' => $hasta,
], 1, 3);
ok(isset($cargo['items'][0]['programadas']) || $cargo['total'] === 0, 'Cargo usa programadas');

if ($reporte['items'] !== []) {
    $item = $reporte['items'][0];
    ok(array_key_exists('cumplimiento_id', $item), 'Detalle general expone cumplimiento_id');
    ok(array_key_exists('soportes_count', $item), 'Detalle general expone soportes_count');
    ok(array_key_exists('tiene_soporte', $item), 'Detalle general expone tiene_soporte');
}

// Proyecto solo aplica con Gestión de Proyectos
$filtrado = $reportes->consultar('cumplimiento_general', [
    'desde' => $desde,
    'hasta' => $hasta,
    'proceso_id' => $opciones['procesos'][0]['proceso_id'] ?? null,
    'proyecto' => 'FRONTERA',
], 1, 1);
$etiquetas = $filtrado['filtros_etiqueta'];
$procesoNombre = (string)($etiquetas['Proceso'] ?? '');
$esGestionProyectos = str_contains(
    mb_strtolower(strtr($procesoNombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']), 'UTF-8'),
    'gestion de proyectos'
);
if (!$esGestionProyectos) {
    ok(($etiquetas['Proyecto'] ?? '') === 'Todos', 'Proyecto ignorado si proceso no es Gestión de Proyectos');
}

echo "Checklist RF-REP-026 básico: OK\n";
