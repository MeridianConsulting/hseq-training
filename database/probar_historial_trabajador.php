<?php

declare(strict_types=1);

/**
 * Historial consolidado del trabajador: snapshots A→B→C, periodos laborales, Excel.
 * Uso: php database/probar_historial_trabajador.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\PersonalService;
use App\Services\ReporteService;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

function esperaRechazo(callable $fn, string $mensaje, int $status = 422): void
{
    try {
        $fn();
        fwrite(STDERR, "FALLO: se esperaba rechazo — {$mensaje}\n");
        exit(1);
    } catch (HttpException $e) {
        ok($e->getStatusCode() === $status, $mensaje . ' [' . $e->getStatusCode() . '] ' . $e->getMessage());
    }
}

function excelValor($hoja, string $etiqueta): mixed
{
    foreach ($hoja->getRowIterator() as $fila) {
        $n = $fila->getRowIndex();
        $a = (string)$hoja->getCell('A' . $n)->getValue();
        if (strcasecmp($a, $etiqueta) === 0) {
            return $hoja->getCell('B' . $n)->getValue();
        }
    }

    return null;
}

function excelContiene($hoja, string $texto): bool
{
    foreach ($hoja->getRowIterator() as $fila) {
        foreach ($fila->getCellIterator() as $celda) {
            $v = (string)$celda->getValue();
            if ($v !== '' && stripos($v, $texto) !== false) {
                return true;
            }
        }
    }

    return false;
}

function proyectosDeItems(array $items): array
{
    $set = [];
    foreach ($items as $item) {
        $p = is_string($item['proyecto'] ?? null) ? $item['proyecto'] : '(Sin proyecto)';
        $set[$p] = true;
    }

    return array_keys($set);
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$reportes = new ReporteService();

$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$stamp = date('YmdHis');
$doc = '900044' . substr($stamp, -6);
$proyectoA = 'HSEQ-HIST-A-' . $stamp;
$proyectoB = 'HSEQ-HIST-B-' . $stamp;
$proyectoC = 'HSEQ-HIST-C-' . $stamp;
$nombreProcOp = 'HSEQ-HIST-OP-' . $stamp;
$nombreProcAd = 'HSEQ-HIST-AD-' . $stamp;
$capIds = [];
$procesoOpId = 0;
$procesoAdId = 0;
$personaId = 0;

$limpiar = static function () use (
    &$db,
    &$personalDb,
    $personasT,
    $contratosT,
    $doc,
    &$capIds,
    &$procesoOpId,
    &$procesoAdId
): void {
    $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
    if ($prev !== null) {
        $pid = (int)$prev['persona_id'];
        $db->query('DELETE FROM historial_contexto_trabajador WHERE persona_id_ext = ?', [$pid]);
        $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
        foreach ($asigs as $a) {
            $aid = (int)$a['asignacion_id'];
            $cump = $db->fetchAll('SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ?', [$aid]);
            foreach ($cump as $c) {
                $db->query('DELETE FROM soportes_cumplimiento WHERE cumplimiento_id = ?', [(int)$c['cumplimiento_id']]);
                $db->query('DELETE FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?', [(int)$c['cumplimiento_id']]);
            }
            $db->query('DELETE FROM sesion_participantes WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [$aid]);
        }
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
    }
    foreach ($capIds as $cid) {
        $db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$cid]);
        $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$cid]);
    }
    if ($procesoOpId > 0) {
        $db->query('DELETE FROM procesos WHERE proceso_id = ?', [$procesoOpId]);
    }
    if ($procesoAdId > 0) {
        $db->query('DELETE FROM procesos WHERE proceso_id = ?', [$procesoAdId]);
    }
};

echo "== Limpieza previa ==\n";
$limpiar();

try {
    $cargos = $personal->cargos();
    ok(count($cargos) >= 2, 'Hay al menos dos cargos corporativos');
    $cargoA = (int)$cargos[0]['cargo_id'];
    $cargoB = (int)$cargos[1]['cargo_id'];
    $nombreCargoA = (string)$cargos[0]['nombre_cargo'];
    $nombreCargoB = (string)$cargos[1]['nombre_cargo'];

    $procesoOpId = (int)$db->insert('procesos', ['nombre' => $nombreProcOp, 'activo' => 1]);
    $procesoAdId = (int)$db->insert('procesos', ['nombre' => $nombreProcAd, 'activo' => 1]);
    ok($procesoOpId > 0 && $procesoAdId > 0, 'Procesos de prueba creados');

    $tipoInd = $db->fetch(
        "SELECT tipo_capacitacion_id FROM tipos_capacitacion WHERE UPPER(nombre) = 'INDUCCION' LIMIT 1"
    );
    $tipoIndId = $tipoInd !== null ? (int)$tipoInd['tipo_capacitacion_id'] : null;

    $insertCap = static function (
        Database $db,
        string $codigo,
        string $nombre,
        int $certificado,
        int $evaluacion,
        ?int $tipoId
    ): int {
        return (int)$db->insert('capacitaciones', [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'objetivo' => 'Prueba historial trabajador',
            'duracion_estimada_horas' => 4,
            'criticidad' => 'MEDIA',
            'estado' => 'ACTIVA',
            'certificado' => $certificado,
            'evaluacion' => $evaluacion,
            'nota_minima' => $evaluacion === 1 ? 3.5 : 0,
            'tipo_capacitacion_id' => $tipoId,
            'es_tarea_critica' => 0,
        ]);
    };

    $capInd = $insertCap($db, 'HIA' . substr($stamp, -8), 'Induccion hist ' . $stamp, 0, 0, $tipoIndId);
    $capAlturas = $insertCap($db, 'HIB' . substr($stamp, -8), 'Alturas hist ' . $stamp, 1, 1, null);
    $capEmerg = $insertCap($db, 'HIC' . substr($stamp, -8), 'Emergencias hist ' . $stamp, 0, 0, null);
    $capLider = $insertCap($db, 'HID' . substr($stamp, -8), 'Liderazgo hist ' . $stamp, 0, 0, null);
    $capDoc = $insertCap($db, 'HIE' . substr($stamp, -8), 'Documental hist ' . $stamp, 0, 0, null);
    $capIds = [$capInd, $capAlturas, $capEmerg, $capLider, $capDoc];

    $prep = $personal->prepararEntrada([
        'numero_documento' => $doc,
        'nombre_completo' => 'Juan Perez Historial',
        'correo' => "hist.{$stamp}@hseq.test",
        'cargo_id' => $cargoA,
        'proyecto' => $proyectoA,
        'fecha_ingreso' => '2026-01-15',
    ], null);
    ok($prep['ok'] === true, 'Trabajador de prueba válido');
    $personaId = $personal->persistirAlta($prep['datos']);
    ok($personaId > 0, 'Trabajador creado en Proyecto A');

    $periodosAlta = $db->fetchAll(
        'SELECT proyecto, vigente_hasta, origen FROM historial_contexto_trabajador WHERE persona_id_ext = ?',
        [$personaId]
    );
    ok(count($periodosAlta) === 1, 'Alta abre un periodo laboral');
    ok(($periodosAlta[0]['proyecto'] ?? '') === $proyectoA, 'Periodo inicial = Proyecto A');
    ok($periodosAlta[0]['vigente_hasta'] === null, 'Periodo inicial abierto');

    $crearAsig = static function (
        Database $db,
        int $personaId,
        int $capId,
        int $procesoId,
        string $proyecto,
        int $cargoId,
        string $fecha,
        string $origen
    ): int {
        return (int)$db->insert('asignaciones_capacitacion', [
            'persona_id_ext' => $personaId,
            'capacitacion_id' => $capId,
            'fecha_asignacion' => $fecha,
            'fecha_limite_cumplimiento' => '2026-12-31',
            'origen' => $origen,
            'proceso_id' => $procesoId,
            'proyecto' => $proyecto,
            'cargo_id_ext' => $cargoId,
        ]);
    };

    $asigInd = $crearAsig($db, $personaId, $capInd, $procesoOpId, $proyectoA, $cargoA, '2026-02-01', 'INDUCCION');
    $asigAltA = $crearAsig($db, $personaId, $capAlturas, $procesoOpId, $proyectoA, $cargoA, '2026-02-15', 'AUTOMATICA');
    $asigEmerg = $crearAsig($db, $personaId, $capEmerg, $procesoOpId, $proyectoA, $cargoA, '2026-02-20', 'MANUAL');

    $db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigInd,
        'fecha_realizacion' => '2026-02-05',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 4.0,
        'fecha_vencimiento' => '2027-02-05',
    ]);
    $cumpAlt = (int)$db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigAltA,
        'fecha_realizacion' => '2026-03-01',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8.0,
        'nota_evaluacion' => 4.2,
        'fecha_vencimiento' => '2027-03-01',
    ]);

    $snapA = $db->fetch(
        'SELECT proyecto, cargo_id_ext, proceso_id FROM asignaciones_capacitacion WHERE asignacion_id = ?',
        [$asigAltA]
    );

    echo "\n== Cambio A → B ==\n";
    $personal->editar($personaId, [
        'correo' => "hist.{$stamp}@hseq.test",
        'cargo_id' => $cargoB,
        'proyecto' => $proyectoB,
    ]);
    $asigLider = $crearAsig($db, $personaId, $capLider, $procesoOpId, $proyectoB, $cargoB, '2026-07-01', 'MANUAL');
    $asigAltB = $crearAsig($db, $personaId, $capAlturas, $procesoOpId, $proyectoB, $cargoB, '2026-07-10', 'MANUAL');
    $db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigLider,
        'fecha_realizacion' => '2026-07-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 3.0,
        'fecha_vencimiento' => '2027-07-15',
    ]);
    $db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigAltB,
        'fecha_realizacion' => '2026-07-20',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8.0,
        'nota_evaluacion' => 4.0,
        'fecha_vencimiento' => '2027-07-20',
    ]);

    $snapAdespues = $db->fetch(
        'SELECT proyecto, cargo_id_ext, proceso_id FROM asignaciones_capacitacion WHERE asignacion_id = ?',
        [$asigAltA]
    );
    ok(($snapAdespues['proyecto'] ?? '') === ($snapA['proyecto'] ?? ''), 'Snapshot Proyecto A no se sobrescribe al cambiar a B');
    ok((int)$snapAdespues['cargo_id_ext'] === $cargoA, 'Snapshot cargo A se conserva');
    ok((int)$snapAdespues['proceso_id'] === $procesoOpId, 'Snapshot proceso A se conserva');

    echo "\n== Cambio B → C ==\n";
    $personal->editar($personaId, [
        'correo' => "hist.{$stamp}@hseq.test",
        'cargo_id' => $cargoB,
        'proyecto' => $proyectoC,
    ]);
    $asigDoc = $crearAsig($db, $personaId, $capDoc, $procesoAdId, $proyectoC, $cargoB, '2026-09-01', 'MANUAL');

    $laboral = $db->fetchAll(
        'SELECT proyecto, vigente_hasta, origen FROM historial_contexto_trabajador
         WHERE persona_id_ext = ? ORDER BY historial_id ASC',
        [$personaId]
    );
    ok(count($laboral) === 3, 'Tres periodos laborales got=' . count($laboral));
    ok(($laboral[0]['proyecto'] ?? '') === $proyectoA && $laboral[0]['vigente_hasta'] !== null, 'Proyecto A cerrado');
    ok(($laboral[1]['proyecto'] ?? '') === $proyectoB && $laboral[1]['vigente_hasta'] !== null, 'Proyecto B cerrado');
    ok(($laboral[2]['proyecto'] ?? '') === $proyectoC && $laboral[2]['vigente_hasta'] === null, 'Proyecto C abierto');

    esperaRechazo(
        static fn () => $reportes->consultar('historial_trabajador', [], 1, 20),
        'Sin trabajador responde 422'
    );

    $hist = $reportes->consultar('historial_trabajador', ['persona_id' => $personaId], 1, 20);
    $proyectos = proyectosDeItems($hist['items']);
    ok(in_array($proyectoA, $proyectos, true), 'Historial incluye Proyecto A');
    ok(in_array($proyectoB, $proyectos, true), 'Historial incluye Proyecto B');
    ok(in_array($proyectoC, $proyectos, true), 'Historial incluye Proyecto C');
    ok((int)$hist['total'] >= 6, 'Al menos 6 asignaciones propias got=' . $hist['total']);

    $porId = [];
    foreach ($hist['items'] as $item) {
        $porId[(int)$item['asignacion_id']] = $item;
    }
    ok(($porId[$asigAltA]['proyecto'] ?? '') === $proyectoA, 'Alturas 1 permanece en Proyecto A');
    ok(($porId[$asigAltB]['proyecto'] ?? '') === $proyectoB, 'Alturas 2 (renovación) en Proyecto B');
    ok(($porId[$asigDoc]['proyecto'] ?? '') === $proyectoC, 'Documental en Proyecto C');
    ok(($porId[$asigAltA]['cargo'] ?? '') === $nombreCargoA, 'Cargo histórico de Alturas 1');
    ok(($porId[$asigLider]['cargo'] ?? '') === $nombreCargoB, 'Cargo histórico de Liderazgo');
    ok(($porId[$asigDoc]['proceso'] ?? '') === $nombreProcAd, 'Proceso histórico de Documental = Administrativo');
    ok(($porId[$asigInd]['origen'] ?? '') === 'INDUCCION', 'Inducción identificable');
    ok(($porId[$asigAltA]['origen'] ?? '') === 'AUTOMATICA', 'Asignación automática identificable');
    ok(($porId[$asigEmerg]['origen'] ?? '') === 'MANUAL', 'Asignación manual identificable');
    ok(($porId[$asigAltA]['evaluacion_resultado'] ?? '') === 'Aprobado', 'Evaluación asociada a Alturas 1');
    ok(abs((float)($porId[$asigAltA]['nota_evaluacion'] ?? 0) - 4.2) < 0.01, 'Nota 4.2 en Alturas 1');
    ok(($porId[$asigAltA]['evidencia'] ?? '') === 'Faltante', 'Evidencia faltante coherente con certificado sin soporte');
    ok(($hist['trabajador']['proyecto'] ?? '') === $proyectoC, 'Ficha actual = Proyecto C');
    ok(count($hist['historial_proyecto']) === 3, 'Historial de proyectos tiene 3 periodos');
    ok(count($hist['grupos']) >= 3, 'Agrupación por proyecto tiene al menos 3 grupos');

    echo "\n== Filtro por proyecto B ==\n";
    $soloB = $reportes->consultar('historial_trabajador', [
        'persona_id' => $personaId,
        'proyecto' => $proyectoB,
    ], 1, 20);
    $proyectosB = proyectosDeItems($soloB['items']);
    ok($proyectosB === [$proyectoB], 'Filtro B solo muestra B got=' . implode(',', $proyectosB));
    ok(count($hist['historial_proyecto']) === 3, 'Filtro no borra historial laboral');

    $otraVez = $reportes->consultar('historial_trabajador', ['persona_id' => $personaId], 1, 20);
    $proyectosTodos = proyectosDeItems($otraVez['items']);
    ok(
        in_array($proyectoA, $proyectosTodos, true)
        && in_array($proyectoB, $proyectosTodos, true)
        && in_array($proyectoC, $proyectosTodos, true),
        'Al quitar el filtro vuelven A, B y C'
    );

    echo "\n== Excel ==\n";
    $xlsx = $reportes->excel('historial_trabajador', ['persona_id' => $personaId], 'tester');
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hist_full.xlsx';
    file_put_contents($tmp, $xlsx['contenido']);
    $libro = IOFactory::load($tmp);
    $hoja = $libro->getActiveSheet();
    ok((int)excelValor($hoja, 'TOTAL REGISTROS') === (int)$hist['totales']['asignadas'], 'Excel TOTAL = pantalla');
    ok(excelContiene($hoja, $proyectoA) && excelContiene($hoja, $proyectoB) && excelContiene($hoja, $proyectoC), 'Excel completo trae A, B y C');
    $libro->disconnectWorksheets();
    unlink($tmp);

    $xlsxB = $reportes->excel('historial_trabajador', [
        'persona_id' => $personaId,
        'proyecto' => $proyectoB,
    ], 'tester');
    $tmpB = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hist_b.xlsx';
    file_put_contents($tmpB, $xlsxB['contenido']);
    $libroB = IOFactory::load($tmpB);
    $hojaB = $libroB->getActiveSheet();
    ok((int)excelValor($hojaB, 'TOTAL REGISTROS') === (int)$soloB['totales']['asignadas'], 'Excel filtrado TOTAL = pantalla B');
    $libroB->disconnectWorksheets();
    unlink($tmpB);

    esperaRechazo(
        static fn () => $reportes->excel('historial_trabajador', [
            'persona_id' => $personaId,
            'proyecto' => 'NO-EXISTE-' . $stamp,
        ], 'tester'),
        'Exportar vacío responde 422'
    );

    echo "\n== Evidencia ==\n";
    $db->insert('soportes_cumplimiento', [
        'cumplimiento_id' => $cumpAlt,
        'tipo_soporte' => 'CERTIFICADO',
        'nombre_archivo' => 'alturas.pdf',
        'ruta_archivo' => 'tmp/alturas.pdf',
        'mime_type' => 'application/pdf',
    ]);
    $histEv = $reportes->consultar('historial_trabajador', ['persona_id' => $personaId], 1, 20);
    $itemAlt = null;
    foreach ($histEv['items'] as $item) {
        if ((int)$item['asignacion_id'] === $asigAltA) {
            $itemAlt = $item;
            break;
        }
    }
    ok($itemAlt !== null && ($itemAlt['evidencia'] ?? '') === 'Disponible', 'Evidencia pasa a Disponible');
    ok(is_array($itemAlt['soportes'] ?? null) && count($itemAlt['soportes']) === 1, 'Un cumplimiento, un soporte anidado');

    $evFaltantes = $reportes->consultar('evidencias_faltantes', [
        'persona_id' => $personaId,
    ], 1, 20);
    $sigueFaltante = false;
    foreach ($evFaltantes['items'] as $fila) {
        if ((int)($fila['asignacion_id'] ?? 0) === $asigAltA) {
            $sigueFaltante = true;
        }
    }
    ok(!$sigueFaltante, 'Reporte evidencias faltantes ya no incluye Alturas 1');

    $busqueda = $reportes->buscarTrabajadores('Juan Perez Historial');
    $encontrado = false;
    foreach ($busqueda['items'] as $fila) {
        if ((int)$fila['persona_id'] === $personaId) {
            $encontrado = true;
        }
    }
    ok($encontrado, 'Búsqueda de trabajadores encuentra al de prueba');

    echo "\n== Limpieza final ==\n";
} finally {
    $limpiar();
}

echo "OK: Limpieza final\n";
echo "\nTodas las pruebas de historial del trabajador OK.\n";
