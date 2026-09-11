<?php

declare(strict_types=1);

/**
 * Pruebas RF-PA: plan por año, actividades desde catálogo+matriz, estados y historial.
 * Uso: php database/probar_plan_anual.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\PersonalRepository;
use App\Services\CapacitacionService;
use App\Services\CronogramaService;
use App\Services\DashboardService;
use App\Services\MatrizService;
use App\Services\PersonalService;
use App\Services\PlanAnualService;
use App\Services\SesionService;

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

function borrarPlanAnio(Database $db, int $anio): void
{
    $plan = $db->fetch('SELECT plan_anual_id FROM planes_anuales WHERE anio = ?', [$anio]);
    if ($plan === null) {
        return;
    }
    $planId = (int)$plan['plan_anual_id'];
    $detalles = $db->fetchAll(
        'SELECT plan_detalle_id FROM plan_anual_detalle WHERE plan_anual_id = ?',
        [$planId]
    );
    foreach ($detalles as $d) {
        $id = (int)$d['plan_detalle_id'];
        $sesiones = $db->fetchAll('SELECT sesion_id FROM sesiones_capacitacion WHERE plan_detalle_id = ?', [$id]);
        foreach ($sesiones as $s) {
            $sid = (int)$s['sesion_id'];
            $db->query('DELETE FROM sesion_participantes WHERE sesion_id = ?', [$sid]);
            $db->query('DELETE FROM sesiones_capacitacion WHERE sesion_id = ?', [$sid]);
        }
        $db->query('DELETE FROM plan_detalle_asignaciones WHERE plan_detalle_id = ?', [$id]);
        $db->query('DELETE FROM plan_anual_detalle WHERE plan_detalle_id = ?', [$id]);
    }
    $db->query('DELETE FROM planes_anuales WHERE plan_anual_id = ?', [$planId]);
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$cargosRepo = new PersonalRepository();
$caps = new CapacitacionService();
$matriz = new MatrizService();
$alertas = new AlertaRepository();
$planes = new PlanAnualService();
$sesiones = new SesionService();
$dashRepo = new DashboardRepository();
$periodos = new DashboardService();
$cronograma = new CronogramaService();

$anioPrueba = 2027;
$doc = '9000770201';
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');

echo "== Limpieza previa año {$anioPrueba} ==\n";
borrarPlanAnio($db, $anioPrueba);
$prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
if ($prev !== null) {
    $pid = (int)$prev['persona_id'];
    $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
    foreach ($asigs as $a) {
        $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [(int)$a['asignacion_id']]);
        $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [(int)$a['asignacion_id']]);
    }
    $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
    $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
}

$opciones = $planes->opciones();
ok($opciones['procesos'] !== [], 'Opciones incluyen procesos');
ok(in_array('FRONTERA', $opciones['proyectos'], true), 'Catálogo de proyectos incluye FRONTERA');

$procesoGp = null;
$procesoOtro = null;
foreach ($opciones['procesos'] as $proceso) {
    if ($alertas->procesoEsGestionProyectos((int)$proceso['proceso_id'])) {
        $procesoGp = $proceso;
    } elseif ($procesoOtro === null) {
        $procesoOtro = $proceso;
    }
}
ok($procesoGp !== null, 'Existe Gestión de Proyectos');
ok($procesoOtro !== null, 'Existe un proceso de oficina');
$procesoGpId = (int)$procesoGp['proceso_id'];
$procesoOtroId = (int)$procesoOtro['proceso_id'];

$mapaCargos = $cargosRepo->mapaCargos();
$nombreCargoPrueba = 'PLAN ANUAL PRUEBA PA';
$claveCargo = $cargosRepo->claveCargo($nombreCargoPrueba);
$cargoId = $mapaCargos['por_nombre'][$claveCargo] ?? $cargosRepo->insertarCargo($nombreCargoPrueba);

$tipo = $db->fetch('SELECT tipo_capacitacion_id FROM tipos_capacitacion WHERE activo = 1 ORDER BY tipo_capacitacion_id ASC LIMIT 1');
$modalidad = $db->fetch('SELECT modalidad_id FROM modalidades WHERE activo = 1 ORDER BY modalidad_id ASC LIMIT 1');
$ubicacion = $db->fetch('SELECT ubicacion_id FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($tipo !== null && $modalidad !== null, 'Hay tipo y modalidad');
ok($ubicacion !== null && $proveedor !== null, 'Hay ubicación y proveedor');

$stamp = date('YmdHis');
$creada = $caps->crear([
    'codigo' => 'CAP-PA-' . $stamp,
    'nombre' => 'Trabajo en alturas (prueba plan)',
    'objetivo' => 'Prueba RF-PA-042',
    'duracion_estimada_horas' => 4,
    'tipo_capacitacion_id' => (int)$tipo['tipo_capacitacion_id'],
    'modalidad_default_id' => (int)$modalidad['modalidad_id'],
    'es_tarea_critica' => 1,
    'evaluacion' => 1,
    'nota_minima' => 3,
    'estado' => 'ACTIVA',
], 1);
$capId = (int)$creada['capacitacion_id'];
ok($capId > 0, 'Capacitación de catálogo creada');
ok($creada['es_tarea_critica'] === true, 'Conserva tarea crítica del catálogo');

$matriz->crear([
    'capacitacion_id' => $capId,
    'cargo_id_ext' => $cargoId,
    'proceso_id' => $procesoGpId,
    'ambito' => 'PROYECTO',
    'proyecto' => 'FRONTERA',
    'obligatoria' => 1,
    'activa' => 1,
], 1);

$persona = $personal->crear([
    'numero_documento' => $doc,
    'nombre_completo' => 'Prueba Plan Anual Uno',
    'correo' => 'plananual1@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'FRONTERA',
    'fecha_ingreso' => '2026-01-15',
]);
$personaId = (int)$persona['persona_id'];

echo "\n== Crear plan 2027 borrador ==\n";
$plan = $planes->crear(['anio' => $anioPrueba], 1);
ok($plan['estado'] === 'BORRADOR', 'Estado BORRADOR');
ok((int)$plan['anio'] === $anioPrueba, 'Año 2027');
$planId = (int)$plan['plan_anual_id'];

try {
    $planes->crearActividad($planId, [
        'capacitacion_id' => 999999999,
        'proceso_id' => $procesoGpId,
        'proyecto' => 'FRONTERA',
        'fecha_programada' => '2027-03-15',
    ]);
    ok(false, 'Cap inexistente debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Cap inexistente = 422');
    ok(str_contains($e->getMessage(), 'no existe o no se encuentra disponible'), 'Mensaje de capacitación no disponible');
}

try {
    $planes->crearActividad($planId, [
        'capacitacion_id' => $capId,
        'proceso_id' => $procesoGpId,
        'proyecto' => null,
        'fecha_programada' => '2027-03-15',
    ]);
    ok(false, 'GP sin proyecto debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'GP sin proyecto = 422');
}

try {
    $planes->crearActividad($planId, [
        'capacitacion_id' => $capId,
        'proceso_id' => $procesoOtroId,
        'proyecto' => null,
        'fecha_programada' => '2027-03-15',
    ]);
    ok(false, 'Sin matriz debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Sin aplicabilidad = 422');
    ok(str_contains($e->getMessage(), 'no está definida como aplicable'), 'Mensaje de matriz');
}

$plan = $planes->crearActividad($planId, [
    'capacitacion_id' => $capId,
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
    'fecha_programada' => '2027-03-15',
]);
ok(count($plan['detalles']) === 1, 'Actividad de marzo creada');
$marzo = $plan['detalles'][0];
ok($marzo['fecha_programada'] === '2027-03-15', 'Fecha 15/03/2027');
ok((int)$marzo['mes_programado'] === 3, 'mes_programado derivado = 3');
ok($marzo['es_tarea_critica'] === true, 'Tarea crítica viene del catálogo');
ok((float)$marzo['duracion_estimada_horas'] === 4.0, 'Duración viene del catálogo');
ok($marzo['proyecto'] === 'FRONTERA', 'Proyecto Frontera');
$idsCargos = array_map(static fn (array $c): int => (int)$c['cargo_id'], $marzo['cargos_aplicables']);
ok(in_array((int)$cargoId, $idsCargos, true), 'Alcance incluye el cargo de la matriz');
ok((int)$marzo['cantidad_programada'] >= 1, 'cantidad_programada cubre gente del cargo');
$detalleMarzoId = (int)$marzo['plan_detalle_id'];

try {
    $planes->crearActividad($planId, [
        'capacitacion_id' => $capId,
        'proceso_id' => $procesoGpId,
        'proyecto' => 'FRONTERA',
        'fecha_programada' => '2027-03-15',
    ]);
    ok(false, 'Duplicado debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Duplicado = 409');
}

$plan = $planes->crearActividad($planId, [
    'capacitacion_id' => $capId,
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
    'fecha_programada' => '2027-09-15',
]);
ok(count($plan['detalles']) === 2, 'Misma cap en marzo y septiembre = dos filas');
$detalleSeptId = 0;
foreach ($plan['detalles'] as $d) {
    if (($d['fecha_programada'] ?? '') === '2027-09-15') {
        $detalleSeptId = (int)$d['plan_detalle_id'];
        break;
    }
}
ok($detalleSeptId > 0, 'Detalle de septiembre identificado');
ok((float)$plan['total_horas'] === 8.0, 'Total horas 4+4');

$periodo2027 = $periodos->periodo(['tipo' => 'anual', 'anio' => $anioPrueba]);
ok($dashRepo->programado($periodo2027, 'general') === 0, 'Borrador no cuenta en programado');
ok($cronograma->tablero(['tipo' => 'anual', 'anio' => $anioPrueba])['total'] === 0, 'Cronograma no muestra borrador');

echo "\n== Enviar, devolver y volver a enviar ==\n";
$enRev = $planes->enviarRevision($planId);
ok($enRev['estado'] === 'EN_REVISION', 'Pendiente de aprobación (EN_REVISION)');
ok($dashRepo->programado($periodo2027, 'general') === 0, 'En revisión no cuenta');

try {
    $planes->crearActividad($planId, [
        'capacitacion_id' => $capId,
        'proceso_id' => $procesoGpId,
        'proyecto' => 'FRONTERA',
        'fecha_programada' => '2027-04-01',
    ]);
    ok(false, 'No debe editar en revisión');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Edición en revisión rechazada');
}

$devuelto = $planes->devolver($planId);
ok($devuelto['estado'] === 'BORRADOR', 'Devuelto vuelve a BORRADOR');
ok(count($devuelto['detalles']) === 2, 'No se borra el plan al devolver');

$enRev = $planes->enviarRevision($planId);
ok($enRev['estado'] === 'EN_REVISION', 'Reenviado a aprobación');

echo "\n== Aprobar ==\n";
$aprobado = $planes->aprobar($planId, 1);
ok($aprobado['estado'] === 'APROBADO', 'Estado APROBADO');
ok($aprobado['aprobado_por_usuario_id_ext'] === 1, 'Usuario aprobador');
ok($aprobado['fecha_aprobacion'] !== null && $aprobado['fecha_aprobacion'] !== '', 'Fecha de aprobación');

$programado = $dashRepo->programado($periodo2027, 'general');
ok($programado >= 1, "Programado 2027={$programado}");

$tablero = $cronograma->tablero(['tipo' => 'anual', 'anio' => $anioPrueba]);
ok($tablero['total'] === 2, 'Cronograma muestra 2 actividades total=' . $tablero['total']);
$mesesVistos = [];
foreach ($tablero['meses'] as $bloque) {
    if ($bloque['total'] > 0) {
        $mesesVistos[] = (int)$bloque['mes'];
    }
}
ok(in_array(3, $mesesVistos, true) && in_array(9, $mesesVistos, true), 'Meses marzo y septiembre');

try {
    $planes->aprobar($planId, 1);
    ok(false, 'Re-aprobar no debe pasar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Aprobación idempotente rechazada');
}

echo "\n== Editar plan aprobado ==\n";
$conJunio = $planes->crearActividad($planId, [
    'capacitacion_id' => $capId,
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
    'fecha_programada' => '2027-06-15',
]);
ok(count($conJunio['detalles']) === 3, 'Se puede agregar actividad en aprobado');
$junioId = 0;
foreach ($conJunio['detalles'] as $d) {
    if (($d['fecha_programada'] ?? '') === '2027-06-15') {
        $junioId = (int)$d['plan_detalle_id'];
        break;
    }
}
ok($junioId > 0, 'Actividad de junio creada');
$sinJunio = $planes->eliminarActividad($planId, $junioId);
ok(count($sinJunio['detalles']) === 2, 'Se puede retirar actividad sin sesiones en aprobado');

echo "\n== Sesión usa el detalle aprobado ==\n";
$sesion = $sesiones->crear([
    'plan_detalle_id' => $detalleMarzoId,
    'fecha' => '2027-03-15',
    'hora' => '08:00',
    'modalidad_id' => (int)$modalidad['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 10,
], 1);
ok((int)$sesion['plan_detalle_id'] === $detalleMarzoId, 'Sesión ligada al plan_detalle_id');
ok((int)$sesion['capacitacion_id'] === $capId, 'Sesión reutiliza la capacitación del plan');

$sesionSept = $sesiones->crear([
    'plan_detalle_id' => $detalleSeptId,
    'fecha' => '2027-09-15',
    'hora' => '08:00',
    'modalidad_id' => (int)$modalidad['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 10,
], 1);
ok((int)$sesionSept['sesion_id'] > 0, 'Sesión de septiembre creada');
$sinSept = $planes->eliminarActividad($planId, $detalleSeptId);
ok(count($sinSept['detalles']) === 1, 'Se retira actividad con sesión programada sin ejecución');

$db->query("UPDATE sesiones_capacitacion SET estado = 'EJECUTADA' WHERE sesion_id = ?", [(int)$sesion['sesion_id']]);
try {
    $planes->eliminarActividad($planId, $detalleMarzoId);
    ok(false, 'No eliminar actividad ejecutada');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Eliminar con ejecución rechazado');
}

echo "\n== Un plan por año e histórico ==\n";
try {
    $planes->crear(['anio' => $anioPrueba], 1);
    ok(false, 'No duplicar año');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Año duplicado 409');
}

$plan2026 = $db->fetch('SELECT plan_anual_id, estado FROM planes_anuales WHERE anio = 2026');
ok($plan2026 !== null, 'Plan 2026 histórico se conserva');

$lista = $planes->listar(1, 20, null, null);
$anios = array_map(static fn (array $p): int => (int)$p['anio'], $lista['items']);
ok(in_array(2026, $anios, true) && in_array(2027, $anios, true), 'Listado muestra 2026 y 2027');

$filtrado = $planes->listar(1, 20, $anioPrueba, null, 'alturas');
ok($filtrado['total'] >= 1, 'Búsqueda por nombre de capacitación');

try {
    $nuevo = $planes->crear(['anio' => 2028], 1);
    $planes->aprobar((int)$nuevo['plan_anual_id'], 1);
    ok(false, 'BORRADOR no salta a APROBADO');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'BORRADOR a APROBADO rechazado');
    borrarPlanAnio($db, 2028);
}

echo "\n== Limpieza ==\n";
borrarPlanAnio($db, $anioPrueba);
$asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$personaId]);
foreach ($asigs as $a) {
    $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [(int)$a['asignacion_id']]);
    $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [(int)$a['asignacion_id']]);
}
$personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$personaId]);
$personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$personaId]);
$db->query('UPDATE matriz_aplicabilidad SET activa = 0 WHERE capacitacion_id = ?', [$capId]);
$ref = $db->fetch('SELECT plan_detalle_id FROM plan_anual_detalle WHERE capacitacion_id = ? LIMIT 1', [$capId]);
$asig = $db->fetch('SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id = ? LIMIT 1', [$capId]);
$mat = $db->fetch('SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad WHERE capacitacion_id = ? LIMIT 1', [$capId]);
if ($ref === null && $asig === null && $mat === null) {
    $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capId]);
} else {
    $db->query("UPDATE capacitaciones SET estado = 'INACTIVA' WHERE capacitacion_id = ?", [$capId]);
}

echo "\nPruebas de plan anual OK.\n";
