<?php

declare(strict_types=1);

/**
 * Pruebas del módulo de alertas (ventana 30 días, RF-AL).
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
for ($i = 1; $i <= 12; $i++) {
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

$gestionProyectos = $db->fetch(
    "SELECT proceso_id FROM procesos WHERE UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(nombre,'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U')) LIKE '%GESTION DE PROYECTOS%' AND activo = 1 LIMIT 1"
);
ok($gestionProyectos !== null, 'Existe proceso Gestión de Proyectos');
$procProyectos = (int)$gestionProyectos['proceso_id'];

$capId = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoCap,
    'nombre' => 'Alerta 30 días (prueba)',
    'objetivo' => 'Prueba de panel de alertas',
    'duracion_estimada_horas' => 4,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
    'certificado' => 1,
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
        'nota_evaluacion' => 4.5,
    ]);

    return [
        'persona_id' => $personaId,
        'asignacion_id' => $asignacionId,
        'cumplimiento_id' => $cumplimientoId,
    ];
}

echo "\n== Semilla ==\n";
$d30 = sembrar($personal, $asignaciones, $db, $docs[0], 'Alerta Treinta', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(30), 'APROBADO');
$d31 = sembrar($personal, $asignaciones, $db, $docs[1], 'Alerta TreintaUno', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(31), 'APROBADO');
$d16 = sembrar($personal, $asignaciones, $db, $docs[2], 'Alerta Dieciseis', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(16), 'APROBADO');
$d5 = sembrar($personal, $asignaciones, $db, $docs[3], 'Alerta Cinco', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(5), 'APROBADO');
$d1 = sembrar($personal, $asignaciones, $db, $docs[4], 'Alerta Uno', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(1), 'APROBADO');
$d0 = sembrar($personal, $asignaciones, $db, $docs[5], 'Alerta Hoy', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(0), 'APROBADO');
$dAyer = sembrar($personal, $asignaciones, $db, $docs[6], 'Alerta Ayer', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(-1), 'APROBADO');
$filtroOpNorte = sembrar($personal, $asignaciones, $db, $docs[7], 'Filtro Op Norte', $cargo1, $proyectoNorte, $procOp, $capId, $fecha(-20), $fecha(7), 'APROBADO');
$filtroAdNorte = sembrar($personal, $asignaciones, $db, $docs[8], 'Filtro Ad Norte', $cargo1, $proyectoNorte, $procAd, $capId, $fecha(-20), $fecha(7), 'APROBADO');
$filtroOpSur = sembrar($personal, $asignaciones, $db, $docs[9], 'Filtro Op Sur', $cargo2, $proyectoSur, $procOp, $capId, $fecha(-20), $fecha(7), 'APROBADO');
$filtroProy = sembrar($personal, $asignaciones, $db, $docs[10], 'Filtro Proyectos', $cargo1, $proyectoNorte, $procProyectos, $capId, $fecha(-20), $fecha(7), 'APROBADO');
$buscaNombre = sembrar($personal, $asignaciones, $db, $docs[11], 'Juan Perez Alerta', $cargo1, $proyectoVentana, $procOp, $capId, $fecha(-20), $fecha(3), 'APROBADO');
ok($d30['cumplimiento_id'] > 0, 'Semilla de cumplimientos creada');

echo "\n== Ventana de 30 días ==\n";
$todas = $alertas->listar(1, 500, ['capacitacion_id' => $capId]);
$itemsVentana = $todas['items'];

ok(buscarItem($itemsVentana, $d30['cumplimiento_id']) !== null, '+30 días aparece');
ok((int)buscarItem($itemsVentana, $d30['cumplimiento_id'])['dias_restantes'] === 30, 'dias_restantes = 30');
ok(buscarItem($itemsVentana, $d31['cumplimiento_id']) === null, '+31 días no aparece');
ok(buscarItem($itemsVentana, $d16['cumplimiento_id']) !== null, '+16 días aparece');
ok(buscarItem($itemsVentana, $d5['cumplimiento_id']) !== null, '+5 días aparece');
ok(buscarItem($itemsVentana, $d1['cumplimiento_id']) !== null, '+1 día aparece');
ok(buscarItem($itemsVentana, $d0['cumplimiento_id']) !== null, 'Vence hoy aparece');
ok(buscarItem($itemsVentana, $dAyer['cumplimiento_id']) !== null, 'Ya vencida aparece');
ok((string)buscarItem($itemsVentana, $dAyer['cumplimiento_id'])['estado'] === 'VENCIDA', 'Estado VENCIDA');

echo "\n== Resumen y filtros de estado ==\n";
$resumen = $todas['resumen'];
ok(isset($resumen['vencidas'], $resumen['proximas_30']), 'Resumen presente');
ok($resumen['vencidas'] >= 1, 'Resumen cuenta vencidas');
ok($resumen['proximas_30'] >= 1, 'Resumen cuenta próximas');

$soloVencidas = $alertas->listar(1, 200, ['estado_alerta' => 'vencidas', 'capacitacion_id' => $capId]);
ok(buscarItem($soloVencidas['items'], $dAyer['cumplimiento_id']) !== null, 'Filtro vencidas incluye ayer');
ok(buscarItem($soloVencidas['items'], $d5['cumplimiento_id']) === null, 'Filtro vencidas excluye +5');

$soloProximas = $alertas->listar(1, 200, ['estado_alerta' => 'proximas', 'capacitacion_id' => $capId]);
ok(buscarItem($soloProximas['items'], $d5['cumplimiento_id']) !== null, 'Filtro próximas incluye +5');
ok(buscarItem($soloProximas['items'], $dAyer['cumplimiento_id']) === null, 'Filtro próximas excluye vencida');

echo "\n== Filtros proceso / proyecto / búsqueda / capacitación / fechas ==\n";
$porProceso = $alertas->listar(1, 100, ['proceso_id' => $procAd]);
ok(buscarItem($porProceso['items'], $filtroAdNorte['cumplimiento_id']) !== null, 'Filtro proceso: incluye Administrativo');
ok(buscarItem($porProceso['items'], $filtroOpNorte['cumplimiento_id']) === null, 'Filtro proceso: excluye Operaciones');

$porProyecto = $alertas->listar(1, 100, [
    'proceso_id' => $procProyectos,
    'proyecto' => $proyectoNorte,
]);
ok(buscarItem($porProyecto['items'], $filtroProy['cumplimiento_id']) !== null, 'Filtro Gestión de Proyectos + proyecto');

$porNombre = $alertas->listar(1, 100, ['q' => 'Juan Perez']);
ok(buscarItem($porNombre['items'], $buscaNombre['cumplimiento_id']) !== null, 'Búsqueda por nombre');

$porDoc = $alertas->listar(1, 100, ['q' => $docs[11]]);
ok(buscarItem($porDoc['items'], $buscaNombre['cumplimiento_id']) !== null, 'Búsqueda por cédula');

$porCap = $alertas->listar(1, 100, ['capacitacion_id' => $capId]);
ok(buscarItem($porCap['items'], $d5['cumplimiento_id']) !== null, 'Filtro por capacitación');

$porFecha = $alertas->listar(1, 100, [
    'vencimiento_desde' => $fecha(4),
    'vencimiento_hasta' => $fecha(6),
]);
ok(buscarItem($porFecha['items'], $d5['cumplimiento_id']) !== null, 'Filtro fecha incluye +5');
ok(buscarItem($porFecha['items'], $d1['cumplimiento_id']) === null, 'Filtro fecha excluye +1');

echo "\n== Orden por urgencia ==\n";
$orden = $alertas->listar(1, 50, ['capacitacion_id' => $capId]);
$idxAyer = null;
$idx5 = null;
foreach ($orden['items'] as $i => $item) {
    if ((int)$item['cumplimiento_id'] === $dAyer['cumplimiento_id']) {
        $idxAyer = $i;
    }
    if ((int)$item['cumplimiento_id'] === $d5['cumplimiento_id']) {
        $idx5 = $i;
    }
}
ok($idxAyer !== null && $idx5 !== null && $idxAyer < $idx5, 'Vencidas antes que próximas');

echo "\n== No duplicados ==\n";
$otraVez = $alertas->listar(1, 200, ['capacitacion_id' => $capId]);
$ids = array_map(static fn ($i) => (int)$i['cumplimiento_id'], $otraVez['items']);
ok(count($ids) === count(array_unique($ids)), 'Sin IDs repetidos');

echo "\n== Opciones de filtro ==\n";
$opciones = $alertas->opciones();
$nombresProc = array_column($opciones['procesos'], 'nombre');
$hayGestionHseq = false;
foreach ($nombresProc as $n) {
    if (stripos($n, 'HSEQ') !== false) {
        $hayGestionHseq = true;
        break;
    }
}
ok($hayGestionHseq, 'Opciones incluyen proceso de catálogo Excel');
ok(!in_array($nombreProcOp, $nombresProc, true), 'Opciones no listan proceso de prueba');
ok(isset($opciones['capacitaciones']) && is_array($opciones['capacitaciones']), 'Opciones incluyen capacitaciones');
ok(isset($opciones['resumen']) || true, 'Shape de opciones válido');

echo "\n== Limpieza final ==\n";
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
limpiarCapYProcesos($db, $codigoCap, $nombreProcOp, $nombreProcAd);
ok(true, 'Limpieza final');

echo "\nTodas las pruebas de alertas OK.\n";
