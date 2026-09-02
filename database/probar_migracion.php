<?php

declare(strict_types=1);

/**
 * Pruebas de carga inicial desde la matriz Excel HSEQ (RF-021).
 * Uso: php database/probar_migracion.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AsignacionRepository;
use App\Repositories\CumplimientoRepository;
use App\Services\MigracionService;
use App\Services\PersonalService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

function esperaExcepcion(callable $fn, string $mensaje): Throwable
{
    try {
        $fn();
        fwrite(STDERR, "FALLO: se esperaba excepción — {$mensaje}\n");
        exit(1);
    } catch (Throwable $e) {
        ok(true, $mensaje . ' [' . $e->getMessage() . ']');
        return $e;
    }
}

function archivoDesdeRuta(string $ruta, string $nombre = 'matriz.xlsx'): array
{
    return [
        'name' => $nombre,
        'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'tmp_name' => $ruta,
        'error' => UPLOAD_ERR_OK,
        'size' => (int)filesize($ruta),
    ];
}

/**
 * @param list<array<string,mixed>> $caps
 * @param list<array<string,mixed>> $trabajadores
 */
function escribirFixture(
    string $ruta,
    array $caps,
    array $trabajadores,
    string $cargo,
    string $proyecto,
    string $proceso,
    bool $conFechaIngreso = true
): void {
    $libro = new Spreadsheet();
    $crono = $libro->getActiveSheet();
    $crono->setTitle('CRONOGRAMA');
    $crono->setCellValue('A3', 'ITEM');
    $crono->setCellValue('E3', 'TEMA');
    $crono->setCellValue('G3', 'DEDICACIÓN');
    $crono->setCellValue('H3', 'OBJETIVO');
    $crono->setCellValue('I3', 'METODOLOGÍA');
    $fila = 5;
    foreach ($caps as $cap) {
        $crono->setCellValue("A{$fila}", $cap['item']);
        $crono->setCellValue("E{$fila}", $cap['nombre']);
        $crono->setCellValue("G{$fila}", $cap['horas'] ?? 4);
        $crono->setCellValue("H{$fila}", $cap['objetivo'] ?? $cap['nombre']);
        $crono->setCellValue("I{$fila}", 'Virtual');
        $fila++;
    }

    $matriz = $libro->createSheet();
    $matriz->setTitle('MATRIZ POR CARGO');
    $matriz->setCellValue('A3', 'CARGO');
    $matriz->setCellValue('B3', 'PROYECTO');
    $matriz->setCellValue('C3', 'PROCESO');
    $col = 4;
    foreach ($caps as $cap) {
        $matriz->setCellValueByColumnAndRow($col, 3, $cap['nombre']);
        $col++;
    }
    $matriz->setCellValue('A4', $cargo);
    $matriz->setCellValue('B4', $proyecto);
    $matriz->setCellValue('C4', $proceso);
    $col = 4;
    foreach ($caps as $cap) {
        if (!empty($cap['x'])) {
            $matriz->setCellValueByColumnAndRow($col, 4, 'X');
        }
        $col++;
    }

    $seg = $libro->createSheet();
    $seg->setTitle('SEGUIMIENTO_PERSONAL');
    $seg->setCellValue('A9', 'IDENTIFICACION');
    $seg->setCellValue('B9', 'NOMBRE COMPLETO');
    $seg->setCellValue('C9', 'CORREO');
    $seg->setCellValue('D9', 'CARGO');
    $seg->setCellValue('E9', 'ESTADO');
    if ($conFechaIngreso) {
        $seg->setCellValue('F9', 'Fecha de ingreso');
        $seg->setCellValue('G9', 'AREA');
    } else {
        $seg->setCellValue('F9', 'AREA');
    }
    $col = 10;
    foreach ($caps as $cap) {
        $seg->setCellValueByColumnAndRow($col, 6, $cap['nombre']);
        $seg->setCellValueByColumnAndRow($col, 9, 'ESTADO');
        $seg->setCellValueByColumnAndRow($col + 1, 9, 'RESULTADO');
        $seg->setCellValueByColumnAndRow($col + 2, 9, 'CERTIFICADO');
        $seg->setCellValueByColumnAndRow($col + 3, 9, 'MES');
        $col += 4;
    }

    $fila = 10;
    foreach ($trabajadores as $t) {
        $seg->setCellValue("A{$fila}", $t['documento'] ?? '');
        $seg->setCellValue("B{$fila}", $t['nombre'] ?? '');
        $seg->setCellValue("C{$fila}", $t['correo'] ?? '');
        $seg->setCellValue("D{$fila}", $t['cargo'] ?? $cargo);
        $seg->setCellValue("E{$fila}", $t['estado'] ?? 'Activo');
        if ($conFechaIngreso) {
            $seg->setCellValue("F{$fila}", $t['fecha_ingreso'] ?? '01/01/2023');
            $seg->setCellValue("G{$fila}", $t['area'] ?? $proyecto);
        } else {
            $seg->setCellValue("F{$fila}", $t['area'] ?? $proyecto);
        }
        $col = 10;
        $estados = is_array($t['estados'] ?? null) ? $t['estados'] : [];
        foreach ($caps as $i => $cap) {
            $celda = $estados[$i] ?? null;
            if (is_array($celda)) {
                $seg->setCellValueByColumnAndRow($col, $fila, $celda['estado'] ?? '');
                $seg->setCellValueByColumnAndRow($col + 1, $fila, $celda['nota'] ?? '');
                $seg->setCellValueByColumnAndRow($col + 2, $fila, $celda['certificado'] ?? '');
                $seg->setCellValueByColumnAndRow($col + 3, $fila, $celda['mes'] ?? '');
            } elseif (is_string($celda) && $celda !== '') {
                $seg->setCellValueByColumnAndRow($col, $fila, $celda);
                if ($celda === 'E') {
                    $seg->setCellValueByColumnAndRow($col + 1, $fila, 4.5);
                    $seg->setCellValueByColumnAndRow($col + 3, $fila, 'FEBRERO');
                }
            }
            $col += 4;
        }
        $fila++;
    }

    $resumen = $libro->createSheet();
    $resumen->setTitle('RESUMEN');
    $resumen->setCellValue('A1', 'Ignorar');

    $escritor = new Xlsx($libro);
    $escritor->save($ruta);
    $libro->disconnectWorksheets();
}

function capsPrueba(int $desdeItem, int $cantidad, bool $conX = true): array
{
    $caps = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $item = $desdeItem + $i;
        $caps[] = [
            'item' => $item,
            'nombre' => 'Tema migracion ' . $item,
            'horas' => 4,
            'objetivo' => 'Objetivo tema ' . $item,
            'x' => $conX,
        ];
    }

    return $caps;
}

function estadosE(int $cantidadE, int $totalCaps): array
{
    $out = [];
    for ($i = 0; $i < $totalCaps; $i++) {
        $out[] = $i < $cantidadE ? 'E' : 'N/A';
    }

    return $out;
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$cargosT = Database::personalTable('cargos');
$personal = new PersonalService();
$migracion = new MigracionService();
$asignaciones = new AsignacionRepository();
$cumplimientos = new CumplimientoRepository();
$actor = ['usuario_id' => 1, 'nombre' => 'admin.hseq', 'ip' => '127.0.0.1'];

$cargoFila = $personalDb->fetch("SELECT cargo_id, nombre_cargo FROM {$cargosT} ORDER BY cargo_id ASC LIMIT 1");
ok($cargoFila !== null, 'Hay un cargo corporativo para el fixture');
$cargoNombre = (string)$cargoFila['nombre_cargo'];
$cargoId = (int)$cargoFila['cargo_id'];

$procesoFila = $db->fetch('SELECT proceso_id, nombre FROM procesos ORDER BY proceso_id ASC LIMIT 1');
ok($procesoFila !== null, 'Hay un proceso en catálogo');
$procesoNombre = (string)$procesoFila['nombre'];

$proyecto = 'MIG-FRON-8819';
$prefijo = '8819';
$codigos = [];
for ($i = 81; $i <= 92; $i++) {
    $codigos[] = sprintf('HSEQ-%02d', $i);
}

function limpiarMigracion(
    Database $db,
    $personalDb,
    string $personasT,
    string $contratosT,
    array $codigos,
    string $prefijo
): void {
    $caps = [];
    foreach ($codigos as $codigo) {
        $fila = $db->fetch('SELECT capacitacion_id FROM capacitaciones WHERE codigo = ?', [$codigo]);
        if ($fila !== null) {
            $caps[] = (int)$fila['capacitacion_id'];
        }
    }
    $personas = $personalDb->fetchAll(
        "SELECT persona_id FROM {$personasT} WHERE numero_documento LIKE ?",
        [$prefijo . '%']
    );
    $pids = array_map(static fn (array $f): int => (int)$f['persona_id'], $personas);
    $asigIds = [];
    if ($pids !== []) {
        $ph = implode(',', array_fill(0, count($pids), '?'));
        $asigs = $db->fetchAll("SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext IN ({$ph})", $pids);
        foreach ($asigs as $a) {
            $asigIds[] = (int)$a['asignacion_id'];
        }
    }
    if ($caps !== []) {
        $phc = implode(',', array_fill(0, count($caps), '?'));
        $asigsCap = $db->fetchAll("SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id IN ({$phc})", $caps);
        foreach ($asigsCap as $a) {
            $asigIds[] = (int)$a['asignacion_id'];
        }
        $asigIds = array_values(array_unique($asigIds));
    }
    if ($asigIds !== []) {
        $pha = implode(',', array_fill(0, count($asigIds), '?'));
        $db->query("DELETE FROM cumplimientos_capacitacion WHERE asignacion_id IN ({$pha})", $asigIds);
        $db->query("DELETE FROM sesion_participantes WHERE asignacion_id IN ({$pha})", $asigIds);
        $db->query("DELETE FROM plan_detalle_asignaciones WHERE asignacion_id IN ({$pha})", $asigIds);
        $db->query("DELETE FROM asignaciones_capacitacion WHERE asignacion_id IN ({$pha})", $asigIds);
    }
    if ($caps !== []) {
        $phc = implode(',', array_fill(0, count($caps), '?'));
        $db->query("DELETE FROM matriz_aplicabilidad WHERE capacitacion_id IN ({$phc})", $caps);
        $db->query("DELETE FROM capacitaciones WHERE capacitacion_id IN ({$phc})", $caps);
    }
    if ($pids !== []) {
        $php = implode(',', array_fill(0, count($pids), '?'));
        $db->query("DELETE FROM historial_contexto_trabajador WHERE persona_id_ext IN ({$php})", $pids);
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id IN ({$php})", $pids);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id IN ({$php})", $pids);
    }
    $db->query("DELETE FROM auditoria WHERE accion = 'migracion_inicial' AND entidad = 'migraciones'");
    $migs = $db->fetchAll("SELECT migracion_id FROM migraciones WHERE nombre_archivo LIKE 'fixture_mig%' OR nombre_archivo = 'frontera.xlsx'");
    foreach ($migs as $m) {
        $id = (int)$m['migracion_id'];
        $dir = BASE_PATH . '/storage/uploads/migraciones/' . $id;
        if (is_dir($dir)) {
            $archivos = glob($dir . '/*') ?: [];
            foreach ($archivos as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }
    $db->query("DELETE FROM migraciones WHERE nombre_archivo LIKE 'fixture_mig%' OR nombre_archivo = 'frontera.xlsx'");
}

limpiarMigracion($db, $personalDb, $personasT, $contratosT, $codigos, $prefijo);

$tmpDir = sys_get_temp_dir();
$caps10 = capsPrueba(81, 10, true);

$trabajadores50 = [];
for ($n = 1; $n <= 50; $n++) {
    $estados = estadosE(2, 10);
    if ($n === 1) {
        $estados[0] = ['estado' => 'E', 'nota' => 4.5, 'certificado' => 'SI', 'mes' => 'FEBRERO'];
        $estados[1] = 'E';
        $estados[2] = 'P';
    }
    $trabajadores50[] = [
        'documento' => sprintf('881900%02d', $n),
        'nombre' => sprintf('Trabajador Migracion %02d Prueba', $n),
        'correo' => sprintf('mig%02d@hseq.test', $n),
        'cargo' => $cargoNombre,
        'estado' => $n === 50 ? 'Retirado' : 'Activo',
        'fecha_ingreso' => '15/01/2023',
        'estados' => $estados,
    ];
}

$rutaConteo = $tmpDir . DIRECTORY_SEPARATOR . 'fixture_mig_conteo.xlsx';
escribirFixture($rutaConteo, $caps10, $trabajadores50, $cargoNombre, $proyecto, $procesoNombre);

echo "== Conteo 50 / 10 / 100 E ==\n";
$job = $migracion->validar(archivoDesdeRuta($rutaConteo, 'fixture_mig_conteo.xlsx'), 2023, $actor);
ok(($job['estado'] ?? '') === 'VALIDADA', 'Dry-run deja estado VALIDADA');
ok((int)$job['resumen']['trabajadores']['detectados'] === 50, 'Excel trabajadores = 50');
ok((int)$job['resumen']['capacitaciones']['detectados'] === 10, 'Excel capacitaciones = 10');
ok((int)$job['resumen']['cumplimientos']['detectados'] === 100, 'Excel cumplimientos E = 100');
ok((int)$job['resumen']['matriz']['detectados'] === 10, 'Excel matriz X = 10');
ok((int)$job['resumen']['trabajadores']['inconsistencias'] === 0, 'Sin errores de trabajador en fixture válido');

$antesPersonas = (int)$personalDb->fetch(
    "SELECT COUNT(*) AS n FROM {$personasT} WHERE numero_documento LIKE ?",
    [$prefijo . '%']
)['n'];
ok($antesPersonas === 0, 'Dry-run no inserta personas');

$confirmado = $migracion->confirmar((int)$job['migracion_id'], $actor);
ok(($confirmado['estado'] ?? '') === 'CONFIRMADA', 'Confirmar deja CONFIRMADA');
$c = $confirmado['conteos'];
ok((int)$c['trabajadores']['excel'] === 50, 'Conteo final trabajadores Excel = 50');
ok((int)$c['trabajadores']['importados'] === 50, 'Importados trabajadores = 50');
ok((int)$c['trabajadores']['sistema'] === 50, 'Sistema trabajadores = Excel');
ok((int)$c['capacitaciones']['excel'] === 10 && (int)$c['capacitaciones']['sistema'] === 10, 'Sistema capacitaciones = Excel');
ok((int)$c['cumplimientos']['excel'] === 100 && (int)$c['cumplimientos']['sistema'] === 100, 'Sistema cumplimientos = Excel');
ok((int)$c['matriz']['excel'] === 10 && (int)$c['matriz']['sistema'] === 10, 'Sistema matriz = Excel');
ok((int)$c['trabajadores']['importados'] + (int)$c['trabajadores']['rechazados'] === 50, 'importados + rechazados = detectados');

$retirado = $personalDb->fetch("SELECT estado FROM {$personasT} WHERE numero_documento = ?", ['88190050']);
ok(($retirado['estado'] ?? '') === 'Inactivo', 'Retirado se importa como Inactivo');

$pid1 = (int)$personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", ['88190001'])['persona_id'];
$asigsPersona = $db->fetchAll(
    'SELECT origen FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$pid1]
);
$origenes = array_column($asigsPersona, 'origen');
ok(!in_array('AUTOMATICA', $origenes, true), 'Migración no dispara el motor de asignaciones');

echo "\n== 5 trabajadores inválidos y reporte antes de confirmar ==\n";
$capsErr = capsPrueba(81, 2, false);
$trabErr = [];
for ($n = 1; $n <= 8; $n++) {
    $fila = [
        'documento' => $n <= 3 ? '' : sprintf('881901%02d', $n),
        'nombre' => sprintf('Error Migracion %02d Prueba', $n),
        'correo' => sprintf('err%02d@hseq.test', $n),
        'cargo' => $cargoNombre,
        'fecha_ingreso' => $n === 4 || $n === 5 ? '' : '01/02/2023',
        'estados' => ['E', 'N/A'],
    ];
    $trabErr[] = $fila;
}
$rutaErr = $tmpDir . DIRECTORY_SEPARATOR . 'fixture_mig_err.xlsx';
escribirFixture($rutaErr, $capsErr, $trabErr, $cargoNombre, $proyecto, $procesoNombre);
$jobErr = $migracion->validar(archivoDesdeRuta($rutaErr, 'fixture_mig_err.xlsx'), 2023, $actor);
ok((int)$jobErr['resumen']['errores'] >= 5, 'Al menos 5 errores de trabajador');
ok((int)$jobErr['resumen']['trabajadores']['validos'] === 3, '3 trabajadores válidos importables');
$rep = $migracion->reporteExcel((int)$jobErr['migracion_id']);
ok($rep['contenido'] !== '', 'Reporte Excel descargable antes de confirmar');
$tmpRep = $tmpDir . DIRECTORY_SEPARATOR . 'reporte_mig.xlsx';
file_put_contents($tmpRep, $rep['contenido']);
$libroRep = IOFactory::load($tmpRep);
$hojaRep = $libroRep->getActiveSheet();
ok((string)$hojaRep->getCell('A1')->getValue() === 'Hoja', 'Reporte tiene encabezados');
ok($hojaRep->getHighestRow() >= 6, 'Reporte lista las inconsistencias');
$inc = $migracion->inconsistencias((int)$jobErr['migracion_id'], 1, 20);
ok($inc['total'] >= 5, 'Inconsistencias paginadas antes de confirmar');
$migracion->cancelar((int)$jobErr['migracion_id']);
$docsErr = $personalDb->fetch(
    "SELECT COUNT(*) AS n FROM {$personasT} WHERE numero_documento LIKE '881901%'"
);
ok((int)$docsErr['n'] === 0, 'Cancelar no inserta personas del archivo de errores');

echo "\n== Documentos duplicados en archivo ==\n";
$capsDup = capsPrueba(81, 1, false);
$rutaDup = $tmpDir . DIRECTORY_SEPARATOR . 'fixture_mig_dup.xlsx';
escribirFixture($rutaDup, $capsDup, [
    [
        'documento' => '88190201',
        'nombre' => 'Duplicado Uno Prueba',
        'correo' => 'dup1@hseq.test',
        'cargo' => $cargoNombre,
        'estados' => ['N/A'],
    ],
    [
        'documento' => '88190201',
        'nombre' => 'Duplicado Dos Prueba',
        'correo' => 'dup2@hseq.test',
        'cargo' => $cargoNombre,
        'estados' => ['N/A'],
    ],
], $cargoNombre, $proyecto, $procesoNombre);
$jobDup = $migracion->validar(archivoDesdeRuta($rutaDup, 'fixture_mig_dup.xlsx'), 2023, $actor);
$motivos = array_column($migracion->inconsistencias((int)$jobDup['migracion_id'], 1, 50)['items'], 'motivo');
ok(in_array('Documento duplicado.', $motivos, true), 'Dry-run detecta documento duplicado');
$migracion->cancelar((int)$jobDup['migracion_id']);

echo "\n== E a documento inexistente ==\n";
$capsGhost = capsPrueba(81, 1, false);
$rutaGhost = $tmpDir . DIRECTORY_SEPARATOR . 'fixture_mig_ghost.xlsx';
escribirFixture($rutaGhost, $capsGhost, [
    [
        'documento' => '',
        'nombre' => 'Sin Documento Prueba',
        'correo' => 'ghost@hseq.test',
        'cargo' => $cargoNombre,
        'estados' => ['E'],
    ],
], $cargoNombre, $proyecto, $procesoNombre);
$jobGhost = $migracion->validar(archivoDesdeRuta($rutaGhost, 'fixture_mig_ghost.xlsx'), 2023, $actor);
$confirmGhost = $migracion->confirmar((int)$jobGhost['migracion_id'], $actor);
ok((int)$confirmGhost['conteos']['cumplimientos']['importados'] === 0, 'No crea cumplimiento si el trabajador no existe');

echo "\n== Segunda carga del mismo fixture ==\n";
$job2 = $migracion->validar(archivoDesdeRuta($rutaConteo, 'fixture_mig_conteo.xlsx'), 2023, $actor);
$conf2 = $migracion->confirmar((int)$job2['migracion_id'], $actor);
ok((int)$conf2['conteos']['trabajadores']['importados'] === 0, 'Reimportación no inserta personas nuevas');
ok((int)$conf2['conteos']['capacitaciones']['importados'] === 0, 'Reimportación no inserta capacitaciones nuevas');
ok((int)$conf2['conteos']['cumplimientos']['importados'] === 0, 'Reimportación no inserta cumplimientos nuevos');
ok((int)$conf2['conteos']['matriz']['importados'] === 0, 'Reimportación no inserta matriz nueva');

echo "\n== Cancelar tras validar ==\n";
$capsCan = capsPrueba(91, 1, true);
$rutaCan = $tmpDir . DIRECTORY_SEPARATOR . 'fixture_mig_can.xlsx';
escribirFixture($rutaCan, $capsCan, [
    [
        'documento' => '88190301',
        'nombre' => 'Cancelado Migracion Prueba',
        'correo' => 'can@hseq.test',
        'cargo' => $cargoNombre,
        'estados' => ['E'],
    ],
], $cargoNombre, $proyecto, $procesoNombre);
$jobCan = $migracion->validar(archivoDesdeRuta($rutaCan, 'fixture_mig_can.xlsx'), 2023, $actor);
$cancelado = $migracion->cancelar((int)$jobCan['migracion_id']);
ok(($cancelado['estado'] ?? '') === 'CANCELADA', 'Estado CANCELADA');
ok($personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", ['88190301']) === null, 'Cancelar no crea persona');
ok($db->fetch('SELECT capacitacion_id FROM capacitaciones WHERE codigo = ?', ['HSEQ-91']) === null, 'Cancelar no crea capacitación');

echo "\n== Rollback por fallo a mitad ==\n";
$capsRoll = capsPrueba(92, 1, true);
$rutaRoll = $tmpDir . DIRECTORY_SEPARATOR . 'fixture_mig_roll.xlsx';
escribirFixture($rutaRoll, $capsRoll, [
    [
        'documento' => '88190401',
        'nombre' => 'Rollback Migracion Prueba',
        'correo' => 'roll@hseq.test',
        'cargo' => $cargoNombre,
        'estados' => ['E'],
    ],
], $cargoNombre, $proyecto, $procesoNombre);
$jobRoll = $migracion->validar(archivoDesdeRuta($rutaRoll, 'fixture_mig_roll.xlsx'), 2023, $actor);
MigracionService::$fallarImportacion = true;
esperaExcepcion(
    static fn () => $migracion->confirmar((int)$jobRoll['migracion_id'], $actor),
    'Confirmar lanza si falla a mitad'
);
$fallida = $migracion->ver((int)$jobRoll['migracion_id']);
ok(($fallida['estado'] ?? '') === 'FALLIDA', 'Estado FALLIDA tras rollback');
ok($personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", ['88190401']) === null, 'Rollback no deja persona');
ok($db->fetch('SELECT capacitacion_id FROM capacitaciones WHERE codigo = ?', ['HSEQ-92']) === null, 'Rollback no deja capacitación');

echo "\n== Auditoría un evento ==\n";
$auds = $db->fetchAll(
    "SELECT auditoria_id, usuario_id_ext, usuario_nombre, valor_nuevo, created_at
     FROM auditoria
     WHERE accion = 'migracion_inicial' AND entidad = 'migraciones' AND entidad_id = ?
     ORDER BY auditoria_id DESC",
    [(int)$job['migracion_id']]
);
ok(count($auds) === 1, 'Un solo evento de auditoría para la confirmación');
ok((int)$auds[0]['usuario_id_ext'] === 1, 'Auditoría conserva el usuario del actor');
ok((string)$auds[0]['usuario_nombre'] === 'admin.hseq', 'Auditoría conserva el nombre del actor');
$detalle = json_decode((string)$auds[0]['valor_nuevo'], true);
ok(is_array($detalle) && isset($detalle['conteos']), 'Auditoría incluye conteos');
ok(isset($detalle['archivo']), 'Auditoría incluye el archivo');

echo "\n== Historial del trabajador con E ==\n";
$cap81 = $db->fetch('SELECT capacitacion_id FROM capacitaciones WHERE codigo = ?', ['HSEQ-81']);
ok($cap81 !== null, 'HSEQ-81 quedó en sistema');
$asigE = $asignaciones->buscarPorPersonaYCapacitacion($pid1, (int)$cap81['capacitacion_id']);
ok($asigE !== null, 'Hay asignación para el trabajador con E');
$cumpE = $cumplimientos->buscarPorAsignacion((int)$asigE['asignacion_id']);
ok($cumpE !== null, 'Hay cumplimiento asociado a la asignación');
ok(($cumpE['sesion_id'] ?? null) === null || $cumpE['sesion_id'] === '', 'Cumplimiento migrado sin sesión');
ok(($cumpE['fecha_vencimiento'] ?? null) === null || $cumpE['fecha_vencimiento'] === '', 'Vencimiento queda NULL');
ok((string)$cumpE['fecha_realizacion'] === '2023-02-01', 'FEBRERO + año 2023 = 2023-02-01');

echo "\n== Matriz usable por el motor en un alta posterior ==\n";
$regla = $db->fetch(
    'SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad
     WHERE capacitacion_id = ? AND cargo_id_ext = ? AND proyecto = ?',
    [(int)$cap81['capacitacion_id'], $cargoId, $proyecto]
);
ok($regla !== null, 'La X de matriz quedó consultable');
$alta = $personal->crear([
    'documento' => '88190501',
    'nombre' => 'Alta Posterior Motor Prueba',
    'correo' => 'motor.post@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => $proyecto,
    'fecha_ingreso' => '2026-01-15',
]);
$asigMotor = $asignaciones->buscarPorPersonaYCapacitacion((int)$alta['persona_id'], (int)$cap81['capacitacion_id']);
ok($asigMotor !== null, 'El motor asigna la capacitación de la matriz migrada al alta posterior');
ok(($asigMotor['origen'] ?? '') === 'AUTOMATICA', 'El alta posterior recibe origen AUTOMATICA');

echo "\n== Sin columna de fecha de ingreso usa el 1 de enero del programa ==\n";
$rutaSinFecha = $tmpDir . DIRECTORY_SEPARATOR . 'fixture_mig_sin_fecha.xlsx';
escribirFixture(
    $rutaSinFecha,
    capsPrueba(91, 1, true),
    [[
        'documento' => '88190301',
        'nombre' => 'Sin Fecha Ingreso Prueba',
        'correo' => 'sinfecha@hseq.test',
        'cargo' => $cargoNombre,
        'estados' => ['N/A'],
    ]],
    $cargoNombre,
    $proyecto,
    $procesoNombre,
    false
);
$jobSinFecha = $migracion->validar(archivoDesdeRuta($rutaSinFecha, 'fixture_mig_sin_fecha.xlsx'), 2024, $actor);
ok((int)$jobSinFecha['resumen']['trabajadores']['validos'] === 1, 'Sin fecha de ingreso el trabajador sigue siendo válido');
ok((int)$jobSinFecha['resumen']['advertencias'] >= 1, 'Advertencia de fecha de ingreso por defecto');
$incSinFecha = $migracion->inconsistencias((int)$jobSinFecha['migracion_id'], 1, 50);
$errFechaCol = 0;
$advFechaCol = 0;
foreach ($incSinFecha['items'] as $item) {
    if (($item['campo'] ?? '') !== 'fecha_ingreso') {
        continue;
    }
    if (($item['severidad'] ?? '') === 'Error') {
        $errFechaCol++;
    }
    if (($item['severidad'] ?? '') === 'Advertencia') {
        $advFechaCol++;
    }
}
ok($errFechaCol === 0, 'Sin columna de ingreso no genera error por fila');
ok($advFechaCol >= 1, 'Hay advertencia de archivo por fecha ausente');
$confSinFecha = $migracion->confirmar((int)$jobSinFecha['migracion_id'], $actor);
ok(($confSinFecha['estado'] ?? '') === 'CONFIRMADA', 'Confirma migración sin columna de ingreso');
$contratoSinFecha = $personalDb->fetch(
    "SELECT ct.fecha_inicio FROM {$contratosT} ct
     INNER JOIN {$personasT} p ON p.persona_id = ct.persona_id
     WHERE p.numero_documento = ? LIMIT 1",
    ['88190301']
);
ok(($contratoSinFecha['fecha_inicio'] ?? '') === '2024-01-01', 'Fecha por defecto = 1 de enero del año del programa');

echo "\n== Corrida contra el Excel Frontera ==\n";
$frontera = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR
    . '02_HSEQ_PRG_10_Capacitacion_entrenamiento_Frontera.xlsx';
if (is_file($frontera)) {
    $jobF = $migracion->validar(archivoDesdeRuta($frontera, 'frontera.xlsx'), 2023, $actor);
    $errFecha = 0;
    $page = 1;
    $total = 1;
    do {
        $lote = $migracion->inconsistencias((int)$jobF['migracion_id'], $page, 100);
        $total = (int)($lote['total'] ?? 0);
        foreach ($lote['items'] as $item) {
            if (($item['campo'] ?? '') === 'fecha_ingreso' && ($item['severidad'] ?? '') === 'Error') {
                $errFecha++;
            }
        }
        $page++;
    } while (($page - 1) * 100 < $total);
    echo 'Frontera trabajadores detectados=' . (int)$jobF['resumen']['trabajadores']['detectados']
        . ' validos=' . (int)$jobF['resumen']['trabajadores']['validos']
        . ' matriz_validos=' . (int)$jobF['resumen']['matriz']['validos']
        . ' cumplimientos_validos=' . (int)$jobF['resumen']['cumplimientos']['validos']
        . ' errores_fecha_ingreso=' . $errFecha . "\n";
    ok((int)$jobF['resumen']['trabajadores']['validos'] > 0, 'Frontera tiene trabajadores válidos');
    ok($errFecha === 0, 'Frontera no reporta error de fecha de ingreso');
    ok((int)$jobF['resumen']['matriz']['validos'] > 0, 'Frontera matriz con filas válidas');
    ok((int)$jobF['resumen']['cumplimientos']['validos'] > 0, 'Frontera cumplimientos E válidos');
    if (($jobF['estado'] ?? '') === 'VALIDADA') {
        $migracion->cancelar((int)$jobF['migracion_id']);
    }
} else {
    echo "INFORMATIVO: no está el xlsx Frontera en docs/; se omite.\n";
}

limpiarMigracion($db, $personalDb, $personasT, $contratosT, $codigos, $prefijo);
@unlink($rutaConteo);
@unlink($rutaErr);
@unlink($rutaDup);
@unlink($rutaGhost);
@unlink($rutaCan);
@unlink($rutaRoll);
@unlink($tmpRep);
@unlink($rutaSinFecha);

echo "\nTodas las pruebas de migración OK.\n";
