<?php

declare(strict_types=1);

/**
 * Pruebas del panel de alertas (RF-003, próximos 10 días).
 * Uso: php database/probar_alertas.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\AlertaService;
use App\Services\AsignacionService;
use App\Services\PersonalService;

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

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>|null
 */
function buscarItem(array $items, int $cumplimientoId): ?array
{
    foreach ($items as $item) {
        if ((int)$item['cumplimiento_id'] === $cumplimientoId) {
            return $item;
        }
    }

    return null;
}

function limpiarPersonas(Database $db, $personalDb, string $personasT, string $contratosT, array $docs): void
{
    foreach ($docs as $doc) {
        $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
        if ($prev === null) {
            continue;
        }
        $pid = (int)$prev['persona_id'];
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
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$alertas = new AlertaService();

$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$stamp = date('YmdHis');
$prefijoDoc = '900099' . substr($stamp, -4);
$docs = [];
for ($i = 1; $i <= 10; $i++) {
    $docs[] = $prefijoDoc . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
}

$codigoCap = 'ALR-' . $stamp;
$nombreProcOp = 'HSEQ-ALR-OP-' . $stamp;
$nombreProcAd = 'HSEQ-ALR-AD-' . $stamp;
$proyectoVentana = 'HSEQ-ALR-VENTANA-' . $stamp;
$proyectoNorte = 'HSEQ-ALR-NORTE-' . $stamp;
$proyectoSur = 'HSEQ-ALR-SUR-' . $stamp;

function limpiarCapYProcesos(
    Database $db,
    string $codigoCap,
    string $nombreProcOp,
    string $nombreProcAd
): void {
    $cap = $db->fetch('SELECT capacitacion_id FROM capacitaciones WHERE codigo = ?', [$codigoCap]);
    if ($cap !== null) {
        $capId = (int)$cap['capacitacion_id'];
        $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id = ?', [$capId]);
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
        $db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$capId]);
        $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capId]);
    }
    foreach ([$nombreProcOp, $nombreProcAd] as $nombre) {
        $proc = $db->fetch('SELECT proceso_id FROM procesos WHERE nombre = ?', [$nombre]);
        if ($proc !== null) {
            $db->query('DELETE FROM procesos WHERE proceso_id = ?', [(int)$proc['proceso_id']]);
        }
    }
}

echo "== Limpieza previa ==\n";
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
limpiarCapYProcesos($db, $codigoCap, $nombreProcOp, $nombreProcAd);

$cargos = $personal->cargos();
ok(count($cargos) >= 2, 'Hay al menos 2 cargos corporativos');
$cargo1 = (int)$cargos[0]['cargo_id'];
$cargo2 = (int)$cargos[1]['cargo_id'];

$procOp = (int)$db->insert('procesos', ['nombre' => $nombreProcOp, 'activo' => 1]);
$procAd = (int)$db->insert('procesos', ['nombre' => $nombreProcAd, 'activo' => 1]);
ok($procOp > 0 && $procAd > 0, 'Procesos de prueba creados');

$capId = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoCap,
    'nombre' => 'Alerta 10 días (prueba)',
    'objetivo' => 'Prueba de panel de alertas',
    'duracion_estimada_horas' => 4,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
]);
ok($capId > 0, 'Capacitación de prueba creada');

$hoy = date('Y-m-d');
$fecha = static function (int $dias) use ($hoy): string {
    return date('Y-m-d', strtotime($hoy . ' ' . $dias . ' days'));
};

/**
 * @return array{persona_id:int,asignacion_id:int,cumplimiento_id:int}
 */
function sembrar(
    PersonalService $personal,
    AsignacionService $asignaciones,
    Database $db,
    string $doc,
    string $nombre,
    int $cargoId,
    string $proyecto,
    int $procesoId,
    int $capId,
    string $realizacion,
    string $vence,
    string $resultado
): array {
    $creada = $personal->crear([
        'numero_documento' => $doc,
        'nombre_completo' => $nombre,
        'correo' => strtolower($doc) . '@hseq.test',
        'cargo_id' => $cargoId,
        'proyecto' => $proyecto,
        'fecha_ingreso' => '2026-01-15',
    ]);
    $personaId = (int)$creada['persona_id'];
    $asig = $asignaciones->crear([
        'persona_id_ext' => $personaId,
        'capacitacion_id' => $capId,
        'fecha_limite_cumplimiento' => '2035-12-31',
    ], 1);
    $asignacionId = (int)$asig['asignacion_id'];
    $db->query(
        'UPDATE asignaciones_capacitacion SET proceso_id = ?, proyecto = ?, cargo_id_ext = ? WHERE asignacion_id = ?',
        [$procesoId, $proyecto, $cargoId, $asignacionId]
    );
    $cumplimientoId = (int)$db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asignacionId,
        'fecha_realizacion' => $realizacion,
        'resultado' => $resultado,
        'horas_efectivas' => 4,
        'fecha_vencimiento' => $vence,
    ]);

    return [
        'persona_id' => $personaId,
        'asignacion_id' => $asignacionId,
        'cumplimiento_id' => $cumplimientoId,
    ];
}

echo "\n== Semilla ==\n";
$d10 = sembrar($personal, $asignaciones, $db, $docs[0], 'Alerta Diez', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(10), 'APROBADO');
$d11 = sembrar($personal, $asignaciones, $db, $docs[1], 'Alerta Once', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(11), 'APROBADO');
$d5 = sembrar($personal, $asignaciones, $db, $docs[2], 'Alerta Cinco', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(5), 'APROBADO');
$d1 = sembrar($personal, $asignaciones, $db, $docs[3], 'Alerta Uno', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(1), 'APROBADO');
$d0 = sembrar($personal, $asignaciones, $db, $docs[4], 'Alerta Hoy', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(0), 'APROBADO');
$dAyer = sembrar($personal, $asignaciones, $db, $docs[5], 'Alerta Ayer', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(-1), 'APROBADO');
$borrador = sembrar($personal, $asignaciones, $db, $docs[6], 'Alerta Borrador', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(3), 'ASISTIO');
$filtroOpNorte = sembrar($personal, $asignaciones, $db, $docs[7], 'Filtro Op Norte', $cargo1, $proyectoNorte, $procOp, $capId, $fecha(-20), $fecha(7), 'APROBADO');
$filtroAdNorte = sembrar($personal, $asignaciones, $db, $docs[8], 'Filtro Ad Norte', $cargo1, $proyectoNorte, $procAd, $capId, $fecha(-20), $fecha(7), 'APROBADO');
$filtroOpSur = sembrar($personal, $asignaciones, $db, $docs[9], 'Filtro Op Sur', $cargo2, $proyectoSur, $procOp, $capId, $fecha(-20), $fecha(7), 'APROBADO');
ok($d10['cumplimiento_id'] > 0, 'Semilla de cumplimientos creada');

echo "\n== Ventana de 10 días ==\n";
$ventana = $alertas->listar(1, 100, ['proyecto' => $proyectoVentana]);
ok(buscarItem($ventana['items'], $d10['cumplimiento_id']) !== null, '+10 días aparece');
ok((int)buscarItem($ventana['items'], $d10['cumplimiento_id'])['dias_restantes'] === 10, 'dias_restantes = 10');
ok(
    (string)buscarItem($ventana['items'], $d10['cumplimiento_id'])['fecha_vencimiento'] === $fecha(10),
    'fecha_vencimiento persistida, no recalculada'
);
ok(buscarItem($ventana['items'], $d11['cumplimiento_id']) === null, '+11 días no aparece');
ok(buscarItem($ventana['items'], $d5['cumplimiento_id']) !== null, '+5 días aparece');
ok((int)buscarItem($ventana['items'], $d5['cumplimiento_id'])['dias_restantes'] === 5, 'dias_restantes = 5');
ok(buscarItem($ventana['items'], $d1['cumplimiento_id']) !== null, '+1 día aparece');
ok((int)buscarItem($ventana['items'], $d1['cumplimiento_id'])['dias_restantes'] === 1, 'dias_restantes = 1');
ok(buscarItem($ventana['items'], $d0['cumplimiento_id']) === null, 'Vence hoy no aparece como próxima');
ok(buscarItem($ventana['items'], $dAyer['cumplimiento_id']) === null, 'Ya vencida no aparece');
ok(buscarItem($ventana['items'], $borrador['cumplimiento_id']) === null, 'Borrador ASISTIO no genera alerta');
ok($ventana['total'] === 3, 'Ventana: solo 10, 5 y 1 días');

echo "\n== Filtros ==\n";
$porProceso = $alertas->listar(1, 100, ['proceso_id' => $procAd]);
ok(buscarItem($porProceso['items'], $filtroAdNorte['cumplimiento_id']) !== null, 'Filtro proceso: incluye Administrativo');
ok(buscarItem($porProceso['items'], $filtroOpNorte['cumplimiento_id']) === null, 'Filtro proceso: excluye Operaciones');

$porProyecto = $alertas->listar(1, 100, ['proyecto' => $proyectoNorte]);
ok(buscarItem($porProyecto['items'], $filtroOpNorte['cumplimiento_id']) !== null, 'Filtro proyecto Norte: Op');
ok(buscarItem($porProyecto['items'], $filtroAdNorte['cumplimiento_id']) !== null, 'Filtro proyecto Norte: Ad');
ok(buscarItem($porProyecto['items'], $filtroOpSur['cumplimiento_id']) === null, 'Filtro proyecto Norte: excluye Sur');

$combinado = $alertas->listar(1, 100, [
    'proceso_id' => $procOp,
    'proyecto' => $proyectoNorte,
]);
ok(buscarItem($combinado['items'], $filtroOpNorte['cumplimiento_id']) !== null, 'AND proceso+proyecto: Op Norte');
ok(buscarItem($combinado['items'], $filtroAdNorte['cumplimiento_id']) === null, 'AND proceso+proyecto: excluye Ad Norte');
ok(buscarItem($combinado['items'], $filtroOpSur['cumplimiento_id']) === null, 'AND proceso+proyecto: excluye Op Sur');

$porCargo = $alertas->listar(1, 100, ['cargo_id_ext' => $cargo2]);
ok(buscarItem($porCargo['items'], $filtroOpSur['cumplimiento_id']) !== null, 'Filtro cargo 2: incluye Sur');
ok(buscarItem($porCargo['items'], $filtroOpNorte['cumplimiento_id']) === null, 'Filtro cargo 2: excluye cargo 1');

$sinFiltro = $alertas->listar(1, 100, []);
ok(buscarItem($sinFiltro['items'], $d10['cumplimiento_id']) !== null, 'Sin filtros: vuelve +10');
ok(buscarItem($sinFiltro['items'], $filtroOpSur['cumplimiento_id']) !== null, 'Sin filtros: vuelve Sur');
ok($sinFiltro['total'] >= $combinado['total'], 'Limpiar filtros restaura el total completo');

echo "\n== No duplicados ==\n";
$otraVez = $alertas->listar(1, 100, ['proyecto' => $proyectoVentana]);
$ids1 = array_map(static fn ($i) => (int)$i['cumplimiento_id'], $ventana['items']);
$ids2 = array_map(static fn ($i) => (int)$i['cumplimiento_id'], $otraVez['items']);
sort($ids1);
sort($ids2);
ok($ids1 === $ids2, 'Listar de nuevo no duplica alertas');
ok(count($ids2) === count(array_unique($ids2)), 'Sin IDs repetidos en una consulta');

echo "\n== Opciones de filtro ==\n";
$opciones = $alertas->opciones();
$nombresProc = array_column($opciones['procesos'], 'nombre');
ok(in_array($nombreProcOp, $nombresProc, true), 'Opciones incluyen proceso de prueba');
ok(in_array($proyectoNorte, $opciones['proyectos'], true), 'Opciones incluyen proyecto de prueba');
$idsCargo = array_map(static fn ($c) => (int)$c['cargo_id'], $opciones['cargos']);
ok(in_array($cargo1, $idsCargo, true) && in_array($cargo2, $idsCargo, true), 'Opciones incluyen cargos');

echo "\n== Limpieza final ==\n";
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
limpiarCapYProcesos($db, $codigoCap, $nombreProcOp, $nombreProcAd);
ok(true, 'Limpieza final');

echo "\nTodas las pruebas de alertas OK.\n";
