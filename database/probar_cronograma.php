<?php

declare(strict_types=1);

/**
 * Pruebas RF-TC: tablero de cronograma desde plan APROBADO.
 * Uso: php database/probar_cronograma.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Repositories\PersonalRepository;
use App\Services\AsignacionService;
use App\Services\CapacitacionService;
use App\Services\CronogramaService;
use App\Services\CumplimientoService;
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

function limpiarPersonaCapacitaciones(Database $db, int $personaId): void
{
    $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$personaId]);
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
}

function borrarPersonaPrueba(Database $db, $personalDb, string $personasT, string $contratosT, string $doc): ?int
{
    $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
    if ($prev === null) {
        return null;
    }
    $pid = (int)$prev['persona_id'];
    limpiarPersonaCapacitaciones($db, $pid);
    $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
    $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);

    return $pid;
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
            $cumps = $db->fetchAll('SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE sesion_id = ?', [$sid]);
            foreach ($cumps as $c) {
                $cid = (int)$c['cumplimiento_id'];
                $db->query('DELETE FROM soportes_cumplimiento WHERE cumplimiento_id = ?', [$cid]);
                $db->query('DELETE FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?', [$cid]);
            }
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
$cronograma = new CronogramaService();
$asignaciones = new AsignacionService();
$sesiones = new SesionService();
$cumplimientos = new CumplimientoService();

$anioPrueba = 2028;
$doc = '9000770301';
$doc2 = '9000770302';
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');

echo "== Limpieza previa año {$anioPrueba} ==\n";
borrarPlanAnio($db, $anioPrueba);
borrarPersonaPrueba($db, $personalDb, $personasT, $contratosT, $doc);
borrarPersonaPrueba($db, $personalDb, $personasT, $contratosT, $doc2);

$opciones = $planes->opciones();
$procesoGp = null;
$procesoOtro = null;
foreach ($opciones['procesos'] as $proceso) {
    if ($alertas->procesoEsGestionProyectos((int)$proceso['proceso_id'])) {
        $procesoGp = $proceso;
    } elseif ($procesoOtro === null) {
        $procesoOtro = $proceso;
    }
}
ok($procesoGp !== null && $procesoOtro !== null, 'Hay proceso GP y de oficina');
$procesoGpId = (int)$procesoGp['proceso_id'];
$procesoOtroId = (int)$procesoOtro['proceso_id'];

$mapaCargos = $cargosRepo->mapaCargos();
$nombreCargoPrueba = 'CRONOGRAMA PRUEBA TC';
$claveCargo = $cargosRepo->claveCargo($nombreCargoPrueba);
$cargoId = $mapaCargos['por_nombre'][$claveCargo] ?? $cargosRepo->insertarCargo($nombreCargoPrueba);

$tipo = $db->fetch('SELECT tipo_capacitacion_id FROM tipos_capacitacion WHERE activo = 1 ORDER BY tipo_capacitacion_id ASC LIMIT 1');
$modalidad = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'PRESENCIAL' AND activo = 1 LIMIT 1")
    ?? $db->fetch('SELECT modalidad_id FROM modalidades WHERE activo = 1 ORDER BY modalidad_id ASC LIMIT 1');
$ubicacion = $db->fetch('SELECT ubicacion_id FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($tipo !== null && $modalidad !== null, 'Hay tipo y modalidad');
ok($ubicacion !== null && $proveedor !== null, 'Hay ubicación y proveedor');

$vigencia = $db->fetch('SELECT vigencia_id, nombre FROM vigencias WHERE activo = 1 ORDER BY vigencia_id ASC LIMIT 1');
ok($vigencia !== null, 'Hay vigencia en catálogo');

$stamp = date('YmdHis');
$creada = $caps->crear([
    'codigo' => 'CAP-TC-' . $stamp,
    'nombre' => 'Trabajo en alturas (prueba cronograma)',
    'objetivo' => 'Prueba RF-TC tablero',
    'duracion_estimada_horas' => 8,
    'tipo_capacitacion_id' => (int)$tipo['tipo_capacitacion_id'],
    'modalidad_default_id' => (int)$modalidad['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
    'vigencia_id' => (int)$vigencia['vigencia_id'],
    'es_tarea_critica' => 1,
    'evaluacion' => 1,
    'certificado' => 1,
    'nota_minima' => 3,
    'estado' => 'ACTIVA',
], 1);
$capId = (int)$creada['capacitacion_id'];
ok($capId > 0, 'Capacitación creada');

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
    'nombre_completo' => 'Prueba Cronograma Uno',
    'correo' => 'cronograma1@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'FRONTERA',
    'fecha_ingreso' => '2026-01-15',
]);
$personaId = (int)$persona['persona_id'];

$plan = $planes->crear(['anio' => $anioPrueba], 1);
$planId = (int)$plan['plan_anual_id'];
$plan = $planes->crearActividad($planId, [
    'capacitacion_id' => $capId,
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
    'fecha_programada' => '2028-03-15',
]);
$plan = $planes->crearActividad($planId, [
    'capacitacion_id' => $capId,
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
    'fecha_programada' => '2028-09-15',
]);
ok(count($plan['detalles']) === 2, 'Dos programaciones de la misma cap');

$tableroBorrador = $cronograma->tablero(['tipo' => 'anual', 'anio' => $anioPrueba]);
ok((int)$tableroBorrador['total'] === 0, 'Borrador no aparece en cronograma');

$planes->enviarRevision($planId);
$planes->aprobar($planId, 1);

echo "\n== Tablero del plan aprobado ==\n";
$tablero = $cronograma->tablero(['tipo' => 'anual', 'anio' => $anioPrueba]);
ok((int)$tablero['total'] === 2, 'Aparece automáticamente tras aprobar');
ok(in_array('FRONTERA', $tablero['proyectos'] ?? [], true), 'Catálogo de proyectos incluye FRONTERA');
$nombresProceso = array_map(static fn (array $p): string => mb_strtoupper((string)$p['nombre'], 'UTF-8'), $tablero['procesos']);
ok(
    (bool)array_filter($nombresProceso, static fn (string $n): bool => str_contains($n, 'GESTION DE PROYECTOS')),
    'El combo incluye Gestión de Proyectos'
);
ok(count($tablero['procesos']) >= 4, 'Los procesos activos del catálogo alimentan el combo');
$marzo = $tablero['items'][0];
ok($marzo['fecha_programada'] === '2028-03-15', 'Orden cronológico: marzo primero');
ok($marzo['proyecto'] === 'FRONTERA', 'Proyecto en el ítem');
ok($marzo['estado_operativo'] === 'PROGRAMADA', 'Estado Programada sin sesiones');
ok($marzo['cargos_aplicables'] !== [], 'Cargos de matriz en el ítem');
$detalleId = (int)$marzo['plan_detalle_id'];
$septiembreId = (int)$tablero['items'][1]['plan_detalle_id'];

$porProceso = $cronograma->tablero([
    'tipo' => 'anual',
    'anio' => $anioPrueba,
    'proceso_id' => $procesoOtroId,
]);
ok((int)$porProceso['total'] === 0, 'Filtro de oficina no muestra GP/FRONTERA');

$porProyecto = $cronograma->tablero([
    'tipo' => 'anual',
    'anio' => $anioPrueba,
    'proceso_id' => $procesoGpId,
    'proyecto' => 'FRONTERA',
]);
ok((int)$porProyecto['total'] === 2, 'Filtro GP + FRONTERA');

$busqueda = $cronograma->tablero([
    'tipo' => 'anual',
    'anio' => $anioPrueba,
    'buscar' => 'alturas',
]);
ok((int)$busqueda['total'] === 2, 'Búsqueda por nombre');

$mensual = $cronograma->tablero(['tipo' => 'mensual', 'anio' => $anioPrueba, 'mes' => 3]);
ok((int)$mensual['total'] === 1, 'Filtro mensual marzo');

$semestral = $cronograma->tablero(['tipo' => 'semestral', 'anio' => $anioPrueba, 'semestre' => 1]);
ok((int)$semestral['total'] === 1, 'Primer semestre solo marzo');

$ver = $cronograma->ver($detalleId);
ok($ver['codigo'] === $marzo['codigo'], 'GET detalle');
ok($ver['objetivo'] !== '', 'Objetivo del catálogo');
ok(($ver['vigencia_nombre'] ?? null) === $vigencia['nombre'], 'Vigencia del catálogo en el ítem');
ok(($ver['requiere_evaluacion'] ?? false) === true, 'Requiere evaluación');
ok(($ver['requiere_certificado'] ?? false) === true, 'Requiere certificado');

echo "\n== Trabajadores ==\n";
$listaInicial = $cronograma->trabajadores($detalleId);
ok((int)$listaInicial['cantidad_programada'] >= 1, 'Cantidad programada viene de la matriz');
if ((int)$listaInicial['total'] === 0) {
    $asignaciones->crear([
        'persona_id_ext' => $personaId,
        'capacitacion_id' => $capId,
        'fecha_asignacion' => '2028-01-10',
        'fecha_limite_cumplimiento' => '2028-12-31',
    ], 1);
}
$conAsig = $cronograma->trabajadores($detalleId);
ok((int)$conAsig['total'] >= 1, 'Trabajador del cargo aparece');
ok($conAsig['items'][0]['numero_documento'] === $doc, 'Documento del trabajador');
ok(($conAsig['items'][0]['estado_asignacion'] ?? '') !== '', 'Estado de la asignación');

$cargoOtroId = 0;
foreach ($mapaCargos['por_id'] as $idCargo => $_nombre) {
    if ((int)$idCargo !== (int)$cargoId) {
        $cargoOtroId = (int)$idCargo;
        break;
    }
}
ok($cargoOtroId > 0, 'Hay otro cargo en el catálogo');
$matriz->crear([
    'capacitacion_id' => $capId,
    'cargo_id_ext' => $cargoOtroId,
    'proceso_id' => $procesoOtroId,
    'ambito' => 'ADMINISTRACION',
    'obligatoria' => 1,
    'activa' => 1,
], 1);
$persona2 = $personal->crear([
    'numero_documento' => $doc2,
    'nombre_completo' => 'Prueba Cronograma Dos',
    'correo' => 'cronograma2@hseq.test',
    'cargo_id' => $cargoOtroId,
    'fecha_ingreso' => '2026-01-15',
], false);
$persona2Id = (int)$persona2['persona_id'];
$asignaciones->crear([
    'persona_id_ext' => $persona2Id,
    'capacitacion_id' => $capId,
    'fecha_asignacion' => '2028-01-10',
    'fecha_limite_cumplimiento' => '2028-12-31',
], 1);
$mixtos = $cronograma->trabajadores($detalleId);
ok((int)$mixtos['total'] >= 2, 'También lista asignados de otro cargo');
$docsLista = array_map(static fn (array $t): string => (string)$t['numero_documento'], $mixtos['items']);
ok(in_array($doc2, $docsLista, true), 'Incluye al trabajador de otro cargo');
borrarPersonaPrueba($db, $personalDb, $personasT, $contratosT, $doc2);

echo "\n== Reprogramar ==\n";
$reprog = $cronograma->reprogramar($detalleId, ['fecha_programada' => '2028-04-20']);
ok($reprog['fecha_programada'] === '2028-04-20', 'Fecha actualizada');
ok((int)$reprog['mes'] === 4, 'mes_programado derivado');

try {
    $cronograma->reprogramar($detalleId, ['fecha_programada' => '2028-09-15']);
    ok(false, 'Duplicado debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Reprogramar duplicado = 409');
}

try {
    $cronograma->reprogramar($detalleId, ['fecha_programada' => '2027-04-20']);
    ok(false, 'Año ajeno debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Fecha fuera del año = 422');
}

echo "\n== Iniciar y finalizar ==\n";
$vacia = $sesiones->crear([
    'plan_detalle_id' => $detalleId,
    'fecha' => '2028-04-20',
    'hora' => '08:00',
    'modalidad_id' => (int)$modalidad['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 1,
], 1);
ok((int)$vacia['convocados'] === 0, 'Sesión creada sin convocados');

$iniciada = $cronograma->iniciar($detalleId, 1);
ok($iniciada['estado_operativo'] === 'EN_EJECUCION', 'Iniciar pasa a En ejecución');
ok(count($iniciada['sesiones']) === 1, 'Una sesión operativa');
ok(($iniciada['sesiones'][0]['estado'] ?? '') === 'PROGRAMADA', 'Sesión PROGRAMADA');
ok((int)$iniciada['sesiones'][0]['sesion_id'] === (int)$vacia['sesion_id'], 'Reutiliza la sesión vacía');
ok((int)$iniciada['sesiones'][0]['convocados'] >= 1, 'Rellena convocados al iniciar');
ok(
    (int)$iniciada['sesiones'][0]['cupo_maximo'] >= (int)$iniciada['sesiones'][0]['convocados'],
    'Amplía el cupo al convocar'
);
$sesionId = (int)$iniciada['sesiones'][0]['sesion_id'];

$otraVez = $cronograma->iniciar($detalleId, 1);
ok((int)$otraVez['sesiones'][0]['sesion_id'] === $sesionId, 'Iniciar de nuevo devuelve la misma sesión');

try {
    $sesiones->finalizar($sesionId);
    ok(false, 'Finalizar incompleto debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Finalizar sin asistencia = 422');
    ok(str_contains($e->getMessage(), 'asistencia'), 'Mensaje indica asistencia pendiente: ' . $e->getMessage());
}

$asigId = (int)$conAsig['items'][0]['asignacion_id'];
$conAsistencia = $sesiones->guardarAsistencia($sesionId, [
    'items' => [[
        'asignacion_id' => $asigId,
        'estado_asistencia' => 'ASISTIO',
    ]],
], 1);
ok(($conAsistencia['participantes'][0]['cumplimiento_id'] ?? null) !== null, 'Asistencia crea cumplimiento');
$cumpId = (int)$conAsistencia['participantes'][0]['cumplimiento_id'];

try {
    $sesiones->finalizar($sesionId);
    ok(false, 'Finalizar sin nota debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Finalizar sin evaluación = 422');
    ok(str_contains($e->getMessage(), 'evaluación') || str_contains($e->getMessage(), 'nota'), 'Mensaje indica nota: ' . $e->getMessage());
}

$cumplimientos->registrarEvaluaciones([
    'sesion_id' => $sesionId,
    'items' => [['asignacion_id' => $asigId, 'nota' => 4.5]],
], 1);

try {
    $sesiones->finalizar($sesionId);
    ok(false, 'Finalizar sin soporte debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 422, 'Finalizar sin soporte = 422');
    ok(str_contains($e->getMessage(), 'soporte'), 'Mensaje indica soporte: ' . $e->getMessage());
}

$db->insert('soportes_cumplimiento', [
    'cumplimiento_id' => $cumpId,
    'tipo_soporte' => 'CERTIFICADO',
    'nombre_archivo' => 'certificado-prueba.pdf',
    'ruta_archivo' => 'tmp/certificado-prueba.pdf',
    'mime_type' => 'application/pdf',
]);

$cerrada = $sesiones->finalizar($sesionId);
ok(($cerrada['estado'] ?? '') === 'EJECUTADA', 'Sesión EJECUTADA');
$finalizada = $cronograma->ver($detalleId);
ok($finalizada['estado_operativo'] === 'FINALIZADA', 'Ítem Finalizada');
$cump = $db->fetch(
    'SELECT fecha_realizacion, fecha_vencimiento, nota_evaluacion FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?',
    [$cumpId]
);
ok($cump !== null && $cump['fecha_realizacion'] !== null, 'Cumplimiento con fecha_realizacion');
ok($cump['fecha_vencimiento'] !== null, 'Cumplimiento con fecha_vencimiento');
ok((float)$cump['nota_evaluacion'] === 4.5, 'Nota persistida');

try {
    $sesiones->finalizar($sesionId);
    ok(false, 'Finalizar ya ejecutada debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'Ya finalizada = 409');
}

try {
    $sesiones->guardarAsistencia($sesionId, [
        'items' => [['asignacion_id' => $asigId, 'estado_asistencia' => 'ASISTIO']],
    ], 1);
    ok(false, 'Asistencia tras finalizar debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'No edita asistencia de finalizada');
}

echo "\n== Cancelar ==\n";
$cancelada = $cronograma->cancelar($septiembreId);
ok($cancelada['estado_operativo'] === 'CANCELADA', 'Estado Cancelada');
$fila = $db->fetch('SELECT plan_detalle_id, estado_programacion FROM plan_anual_detalle WHERE plan_detalle_id = ?', [$septiembreId]);
ok($fila !== null && $fila['estado_programacion'] === 'CANCELADA', 'No se borra el detalle');

$planSigue = $db->fetch('SELECT plan_anual_id FROM planes_anuales WHERE plan_anual_id = ?', [$planId]);
ok($planSigue !== null, 'No se borra el Plan Anual');
$capSigue = $db->fetch('SELECT capacitacion_id FROM capacitaciones WHERE capacitacion_id = ?', [$capId]);
ok($capSigue !== null, 'No se borra la capacitación');

try {
    $cronograma->reprogramar($septiembreId, ['fecha_programada' => '2028-10-01']);
    ok(false, 'Reprogramar cancelada debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'No reprograma cancelada');
}

try {
    $cronograma->iniciar($septiembreId, 1);
    ok(false, 'Iniciar cancelada debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'No inicia cancelada');
}

try {
    $sesiones->crear([
        'plan_detalle_id' => $septiembreId,
        'fecha' => '2028-09-20',
        'hora' => '08:00',
        'modalidad_id' => (int)$modalidad['modalidad_id'],
        'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
        'proveedor_id' => (int)$proveedor['proveedor_id'],
        'cupo_maximo' => 5,
    ], 1);
    ok(false, 'Sesión sobre cancelada debió fallar');
} catch (HttpException $e) {
    ok($e->getStatusCode() === 409, 'No crea sesión sobre cancelada');
}

$sigueEnTablero = $cronograma->tablero(['tipo' => 'anual', 'anio' => $anioPrueba]);
ok((int)$sigueEnTablero['total'] === 2, 'Cancelada sigue visible en el tablero');

echo "\n== Limpieza ==\n";
borrarPersonaPrueba($db, $personalDb, $personasT, $contratosT, $doc);
borrarPersonaPrueba($db, $personalDb, $personasT, $contratosT, $doc2);
borrarPlanAnio($db, $anioPrueba);
$matrizFilas = $db->fetchAll('SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$capId]);
foreach ($matrizFilas as $m) {
    $db->query('DELETE FROM matriz_aplicabilidad WHERE matriz_aplicabilidad_id = ?', [(int)$m['matriz_aplicabilidad_id']]);
}
$db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capId]);

echo "\nPruebas de cronograma OK.\n";
