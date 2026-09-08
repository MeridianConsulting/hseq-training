<?php

declare(strict_types=1);

/**
 * Consulta consolidada de cumplimientos (RF-CU-054 a RF-CU-059).
 * Uso: php database/probar_cumplimientos_consulta.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
use App\Services\AsignacionService;
use App\Services\CumplimientoService;
use App\Services\MatrizService;
use App\Services\PersonalService;
use App\Services\PlanAnualService;
use App\Services\SesionService;
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

function esperaRechazo(callable $fn, string $mensaje, int $status = 422): string
{
    try {
        $fn();
        fwrite(STDERR, "FALLO: se esperaba rechazo — {$mensaje}\n");
        exit(1);
    } catch (HttpException $e) {
        ok($e->getStatusCode() === $status, $mensaje . ' [' . $e->getStatusCode() . '] ' . $e->getMessage());

        return $e->getMessage();
    }
}

function estadoDe(Database $db, int $asignacionId): string
{
    $fila = $db->fetch(
        'SELECT estado_calculado FROM vw_estado_asignaciones WHERE asignacion_id = ? LIMIT 1',
        [$asignacionId]
    );

    return (string)($fila['estado_calculado'] ?? '');
}

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>|null
 */
function itemDe(array $items, int $asignacionId): ?array
{
    foreach ($items as $item) {
        if ((int)($item['asignacion_id'] ?? 0) === $asignacionId) {
            return $item;
        }
    }

    return null;
}

function periodicidadId(Database $db, int $cantidad, string $unidad, string $nombre): int
{
    $fila = $db->fetch(
        'SELECT periodicidad_id FROM periodicidades WHERE cantidad = ? AND unidad = ? LIMIT 1',
        [$cantidad, $unidad]
    );
    if ($fila !== null) {
        return (int)$fila['periodicidad_id'];
    }

    return (int)$db->insert('periodicidades', [
        'nombre' => $nombre,
        'cantidad' => $cantidad,
        'unidad' => $unidad,
        'activo' => 1,
    ]);
}

function borrarPlanYSesiones(Database $db, int $anio): void
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
            $cump = $db->fetchAll('SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE sesion_id = ?', [$sid]);
            foreach ($cump as $c) {
                $db->query('DELETE FROM soportes_cumplimiento WHERE cumplimiento_id = ?', [(int)$c['cumplimiento_id']]);
            }
            $db->query('DELETE FROM cumplimientos_capacitacion WHERE sesion_id = ?', [$sid]);
            $db->query('DELETE FROM sesion_participantes WHERE sesion_id = ?', [$sid]);
            $db->query('DELETE FROM sesiones_capacitacion WHERE sesion_id = ?', [$sid]);
        }
        $db->query('DELETE FROM plan_detalle_asignaciones WHERE plan_detalle_id = ?', [$id]);
        $db->query('DELETE FROM plan_anual_detalle WHERE plan_detalle_id = ?', [$id]);
    }
    $db->query('DELETE FROM planes_anuales WHERE plan_anual_id = ?', [$planId]);
}

function limpiarPersonas(Database $db, $personalDb, string $personasT, string $contratosT, array $docs): void
{
    foreach ($docs as $doc) {
        $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
        if ($prev === null) {
            continue;
        }
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
}

function insertarSoportes(Database $db, int $cumplimientoId): void
{
    foreach (['CERTIFICADO', 'LISTADO_ASISTENCIA'] as $tipo) {
        $db->insert('soportes_cumplimiento', [
            'cumplimiento_id' => $cumplimientoId,
            'tipo_soporte' => $tipo,
            'nombre_archivo' => strtolower($tipo) . '.pdf',
            'ruta_archivo' => 'prueba/' . $cumplimientoId . '/' . strtolower($tipo) . '.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }
}

function cumplimientoIdDe(Database $db, int $asignacionId): int
{
    $fila = $db->fetch(
        'SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ? LIMIT 1',
        [$asignacionId]
    );
    ok($fila !== null, "Hay cumplimiento para asignación {$asignacionId}");

    return (int)$fila['cumplimiento_id'];
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$matriz = new MatrizService();
$planes = new PlanAnualService();
$sesiones = new SesionService();
$cumplimientos = new CumplimientoService();
$alertasRepo = new AlertaRepository();
$actor = ['usuario_id' => 1, 'nombre' => 'admin.hseq', 'ip' => '127.0.0.1'];

$anio = 2038;
$fecha = '2038-08-08';
$vence12 = '2039-08-08';
$stamp = date('YmdHis');
$docs = [
    '90013' . substr($stamp, -6) . '1',
    '90013' . substr($stamp, -6) . '2',
    '90013' . substr($stamp, -6) . '3',
    '90013' . substr($stamp, -6) . '4',
    '90013' . substr($stamp, -6) . '5',
];
$prefijoCap = 'CONS-CU-' . substr($stamp, -6);
$capIds = [];
$matrizIds = [];

$limpiar = static function () use ($db, $personalDb, $personasT, $contratosT, $docs, $anio, &$capIds, &$matrizIds): void {
    borrarPlanYSesiones($db, $anio);
    limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
    foreach ($matrizIds as $mid) {
        if ($mid > 0) {
            $db->query('DELETE FROM matriz_aplicabilidad WHERE matriz_aplicabilidad_id = ?', [$mid]);
        }
    }
    foreach ($capIds as $cid) {
        if ($cid > 0) {
            $db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$cid]);
            $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$cid]);
        }
    }
};

$limpiar();
register_shutdown_function($limpiar);

$presencial = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'PRESENCIAL' AND activo = 1 LIMIT 1");
$ubicacion = $db->fetch('SELECT ubicacion_id FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($presencial !== null && $ubicacion !== null && $proveedor !== null, 'Catálogos de sesión disponibles');

$cargos = $personal->cargos();
ok(count($cargos) >= 2, 'Hay al menos dos cargos corporativos');
$cargoA = (int)$cargos[0]['cargo_id'];
$cargoB = (int)$cargos[1]['cargo_id'];
$nombreCargoA = (string)$cargos[0]['nombre_cargo'];
$nombreCargoB = (string)$cargos[1]['nombre_cargo'];

$proceso = $db->fetch(
    "SELECT proceso_id, nombre FROM procesos
     WHERE activo = 1
     ORDER BY (nombre LIKE '%Gestión de Proyectos%' OR nombre LIKE '%Gestion de Proyectos%') DESC,
              proceso_id ASC
     LIMIT 1"
);
ok($proceso !== null, 'Hay un proceso activo');
$procesoId = (int)$proceso['proceso_id'];
$esGP = $alertasRepo->procesoEsGestionProyectos($procesoId);
$proyecto = $esGP ? 'FRONTERA' : 'CONS-CU-PRU';
$per12 = periodicidadId($db, 12, 'MESES', 'CONS-CU-12M');

$baseCap = [
    'objetivo' => 'Prueba consulta de cumplimientos',
    'duracion_estimada_horas' => 8,
    'criticidad' => 'ALTA',
    'estado' => 'ACTIVA',
    'periodicidad_default_id' => $per12,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
];

$capAlturas = (int)$db->insert('capacitaciones', array_merge($baseCap, [
    'codigo' => $prefijoCap . '-ALT',
    'nombre' => 'Trabajo en alturas (consulta)',
    'es_tarea_critica' => 1,
    'evaluacion' => 1,
    'nota_minima' => 4.00,
    'certificado' => 1,
    'requiere_listado_asistencia' => 1,
]));
$capSinEval = (int)$db->insert('capacitaciones', array_merge($baseCap, [
    'codigo' => $prefijoCap . '-SIN',
    'nombre' => 'Inducción sin evaluación',
    'es_tarea_critica' => 0,
    'evaluacion' => 0,
    'nota_minima' => 0,
    'certificado' => 0,
    'requiere_listado_asistencia' => 0,
]));
$capVencida = (int)$db->insert('capacitaciones', array_merge($baseCap, [
    'codigo' => $prefijoCap . '-VEN',
    'nombre' => 'Curso histórico vencido',
    'es_tarea_critica' => 0,
    'evaluacion' => 0,
    'nota_minima' => 0,
    'certificado' => 0,
    'requiere_listado_asistencia' => 0,
]));
$capCargoB = (int)$db->insert('capacitaciones', array_merge($baseCap, [
    'codigo' => $prefijoCap . '-CB',
    'nombre' => 'Curso del cargo nuevo',
    'es_tarea_critica' => 0,
    'evaluacion' => 0,
    'nota_minima' => 0,
    'certificado' => 0,
    'requiere_listado_asistencia' => 0,
]));
$capIds = [$capAlturas, $capSinEval, $capVencida, $capCargoB];
ok($capAlturas > 0 && $capSinEval > 0 && $capVencida > 0 && $capCargoB > 0, 'Capacitaciones de prueba creadas');

$reglaMatriz = static function (int $capId, int $cargoId) use ($matriz, $per12, $procesoId, $esGP, $proyecto): int {
    $creada = $matriz->crear([
        'capacitacion_id' => $capId,
        'cargo_id_ext' => $cargoId,
        'proceso_id' => $procesoId,
        'ambito' => $esGP ? 'PROYECTO' : 'ADMINISTRACION',
        'proyecto' => $esGP ? $proyecto : null,
        'periodicidad_id' => $per12,
        'obligatoria' => 1,
    ], 1);

    return (int)$creada['matriz_aplicabilidad_id'];
};

$matrizIds[] = $reglaMatriz($capAlturas, $cargoA);
$matrizIds[] = $reglaMatriz($capSinEval, $cargoA);
$matrizIds[] = $reglaMatriz($capVencida, $cargoA);
$matrizIds[] = $reglaMatriz($capCargoB, $cargoB);

$crearPersona = static function (string $doc, string $nombre, string $correo) use ($personal, $cargoA, $proyecto): int {
    $creada = $personal->crear([
        'numero_documento' => $doc,
        'nombre_completo' => $nombre,
        'correo' => $correo,
        'cargo_id' => $cargoA,
        'proyecto' => $proyecto,
        'fecha_ingreso' => '2026-01-15',
    ], false);

    return (int)$creada['persona_id'];
};

$p054 = $crearPersona($docs[0], 'Consulta Alturas Aprobado', 'cump.054.' . $stamp . '@hseq.test');
$p055 = $crearPersona($docs[1], 'Consulta Alturas Reprobado', 'cump.055.' . $stamp . '@hseq.test');
$p056 = $crearPersona($docs[2], 'Consulta Sin Evaluacion', 'cump.056.' . $stamp . '@hseq.test');
$p057 = $crearPersona($docs[3], 'Consulta Historico Vencido', 'cump.057.' . $stamp . '@hseq.test');
$p058 = $crearPersona($docs[4], 'Consulta Cambio Cargo', 'cump.058.' . $stamp . '@hseq.test');

$crearAsig = static function (int $personaId, int $capId) use ($asignaciones): int {
    return (int)$asignaciones->crear([
        'persona_id_ext' => $personaId,
        'capacitacion_id' => $capId,
        'fecha_limite_cumplimiento' => '2038-12-31',
    ], 1)['asignacion_id'];
};

$asig054 = $crearAsig($p054, $capAlturas);
$asig055 = $crearAsig($p055, $capAlturas);
$asig056 = $crearAsig($p056, $capSinEval);
$asig057 = $crearAsig($p057, $capVencida);
$asig058 = $crearAsig($p058, $capSinEval);

$plan = $planes->crear(['anio' => $anio], 1);
$planId = (int)$plan['plan_anual_id'];
$incluir = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => [$asig054, $asig055, $asig056, $asig057, $asig058],
    'mes_programado' => 8,
]);
ok((int)$incluir['creadas'] === 5, '5 asignaciones incluidas en el plan');
$planes->enviarRevision($planId);
$planes->aprobar($planId, 1);

$detallePorCap = [];
foreach ($incluir['items'] as $detalle) {
    $detallePorCap[(int)$detalle['capacitacion_id']] = (int)$detalle['plan_detalle_id'];
}
ok(isset($detallePorCap[$capAlturas], $detallePorCap[$capSinEval], $detallePorCap[$capVencida]), 'Detalles de plan por capacitación');

$baseSesion = [
    'fecha' => $fecha,
    'hora' => '08:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
];

$sesionAlturas = $sesiones->crear(array_merge($baseSesion, [
    'plan_detalle_id' => $detallePorCap[$capAlturas],
    'asignacion_ids' => [$asig054, $asig055],
    'cupo_maximo' => 4,
]), 1);
$sesionSin = $sesiones->crear(array_merge($baseSesion, [
    'plan_detalle_id' => $detallePorCap[$capSinEval],
    'asignacion_ids' => [$asig056, $asig058],
    'cupo_maximo' => 4,
]), 1);
$sesionVen = $sesiones->crear(array_merge($baseSesion, [
    'plan_detalle_id' => $detallePorCap[$capVencida],
    'asignacion_ids' => [$asig057],
    'cupo_maximo' => 2,
]), 1);
$sesionAlturasId = (int)$sesionAlturas['sesion_id'];
$sesionSinId = (int)$sesionSin['sesion_id'];
$sesionVenId = (int)$sesionVen['sesion_id'];

$sesiones->guardarAsistencia($sesionAlturasId, [
    'items' => [
        ['asignacion_id' => $asig054, 'estado_asistencia' => 'ASISTIO'],
        ['asignacion_id' => $asig055, 'estado_asistencia' => 'ASISTIO'],
    ],
], 1);
$sesiones->guardarAsistencia($sesionSinId, [
    'items' => [
        ['asignacion_id' => $asig056, 'estado_asistencia' => 'ASISTIO'],
        ['asignacion_id' => $asig058, 'estado_asistencia' => 'ASISTIO'],
    ],
], 1);
$sesiones->guardarAsistencia($sesionVenId, [
    'items' => [['asignacion_id' => $asig057, 'estado_asistencia' => 'ASISTIO']],
], 1);

ok(estadoDe($db, $asig054) === 'PENDIENTE', 'Asistencia sin APROBADO sigue PENDIENTE');

$cump054 = cumplimientoIdDe($db, $asig054);
$cump055 = cumplimientoIdDe($db, $asig055);
insertarSoportes($db, $cump054);
insertarSoportes($db, $cump055);

echo "== RF-CU-054 Alturas + eval + certificado + 12 meses ==\n";
$reg054 = $cumplimientos->registrar([
    'asignacion_id' => $asig054,
    'sesion_id' => $sesionAlturasId,
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'nota_evaluacion' => 4.50,
], 1);
ok((string)$reg054['resultado'] === 'APROBADO', '054 APROBADO');
ok((string)$reg054['fecha_vencimiento'] === $vence12, "054 vence +12 meses got=" . ($reg054['fecha_vencimiento'] ?? 'null'));
ok(estadoDe($db, $asig054) === 'COMPLETADA', '054 estado COMPLETADA');

$consulta054 = $cumplimientos->consultar(1, 20, [
    'persona_id' => $p054,
    'estado_laboral' => 'Activo',
]);
ok(itemDe($consulta054['items'], $asig054) !== null, '054 aparece en consulta por trabajador');
ok(
    (itemDe($consulta054['items'], $asig054)['estado_calculado'] ?? '') === 'COMPLETADA',
    '054 consulta etiqueta Cumple = COMPLETADA'
);

$porBuscar = $cumplimientos->consultar(1, 20, ['buscar' => $docs[0], 'estado_laboral' => 'Activo']);
ok(itemDe($porBuscar['items'], $asig054) !== null, '054 se encuentra por documento');

$detalle054 = $cumplimientos->consultarDetalle($asig054);
ok($detalle054['estado_actual'] === 'COMPLETADA', '054 detalle estado COMPLETADA');
ok(($detalle054['trabajador']['cargo'] ?? '') === $nombreCargoA, '054 detalle muestra cargo de la asignación');
ok($detalle054['capacitacion']['requiere_evaluacion'] === true, '054 capacitación exige evaluación');
ok($detalle054['capacitacion']['requiere_certificado'] === true, '054 capacitación exige certificado');
ok($detalle054['evaluacion']['aprobada'] === true, '054 evaluación aprobada');
ok((float)$detalle054['evaluacion']['nota_obtenida'] === 4.5, '054 nota 4.5');
ok($detalle054['asistencia']['valida'] === true, '054 asistencia válida');
ok($detalle054['aplicabilidad']['aplica'] === true, '054 aplica por matriz');
ok($detalle054['aplicabilidad']['fuente'] === 'Matriz', '054 fuente aplicabilidad = Matriz');
ok($detalle054['obligacion']['fuente'] === 'Asignaciones', '054 fuente obligación = Asignaciones');
ok($detalle054['ejecucion']['fuente'] === 'Sesión', '054 fuente ejecución = Sesión');
ok($detalle054['vigencia']['fecha_vencimiento'] === $vence12, '054 vigencia +12 meses');
ok(
    in_array(
        (string)($detalle054['vigencia']['origen_periodicidad'] ?? ''),
        [VencimientoService::ORIGEN_MATRIZ_SNAPSHOT, VencimientoService::ORIGEN_MATRIZ_ACTIVA],
        true
    ),
    '054 origen de periodicidad = matriz'
);
$tiposSoporte = array_map(
    static fn (array $s): string => (string)($s['tipo_soporte'] ?? ''),
    $detalle054['soportes']['items'] ?? []
);
ok(in_array('CERTIFICADO', $tiposSoporte, true), '054 tiene certificado');
ok(in_array('LISTADO_ASISTENCIA', $tiposSoporte, true), '054 tiene listado de asistencia');

$opciones = $cumplimientos->opcionesConsulta();
ok(isset($opciones['cargos'], $opciones['procesos'], $opciones['capacitaciones']), 'Opciones de consulta disponibles');

echo "== RF-CU-055 Nota insuficiente no completa ==\n";
esperaRechazo(
    static fn () => $cumplimientos->registrar([
        'asignacion_id' => $asig055,
        'sesion_id' => $sesionAlturasId,
        'fecha_realizacion' => $fecha,
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
        'nota_evaluacion' => 3.50,
    ], 1),
    '055 nota 3.5 rechazada',
    422
);
$asis055 = $db->fetch(
    'SELECT estado_asistencia FROM sesion_participantes WHERE asignacion_id = ? AND sesion_id = ?',
    [$asig055, $sesionAlturasId]
);
ok(($asis055['estado_asistencia'] ?? '') === 'ASISTIO', '055 asistencia ASISTIO intacta');
$borrador055 = $db->fetch(
    'SELECT resultado, nota_evaluacion FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$asig055]
);
ok(($borrador055['resultado'] ?? '') === 'ASISTIO', '055 resultado sigue ASISTIO');
ok($borrador055['nota_evaluacion'] === null, '055 no persistió la nota reprobada');
ok(estadoDe($db, $asig055) === 'PENDIENTE', '055 no queda COMPLETADA');

echo "== RF-CU-056 Sin evaluación requerida cumple sin nota ==\n";
$reg056 = $cumplimientos->registrar([
    'asignacion_id' => $asig056,
    'sesion_id' => $sesionSinId,
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 4,
], 1);
ok((string)$reg056['resultado'] === 'APROBADO', '056 APROBADO sin nota');
ok($reg056['nota_evaluacion'] === null, '056 sin nota persistida');
ok(estadoDe($db, $asig056) === 'COMPLETADA', '056 COMPLETADA sin evaluación');
$detalle056 = $cumplimientos->consultarDetalle($asig056);
ok($detalle056['evaluacion']['requiere'] === false, '056 no exige evaluación');
ok($detalle056['evaluacion']['nota_obtenida'] === null, '056 detalle sin nota');

echo "== RF-CU-057 Histórico vencido permanece ==\n";
$reg057 = $cumplimientos->registrar([
    'asignacion_id' => $asig057,
    'sesion_id' => $sesionVenId,
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 4,
], 1);
$cump057 = (int)$reg057['cumplimiento_id'];
$db->query(
    'UPDATE cumplimientos_capacitacion
     SET fecha_realizacion = ?, fecha_vencimiento = ?
     WHERE cumplimiento_id = ?',
    ['2025-05-10', '2026-05-10', $cump057]
);
ok(estadoDe($db, $asig057) === 'VENCIDA', '057 vigencia vencida');
$historico = $db->fetch(
    'SELECT cumplimiento_id, fecha_realizacion, fecha_vencimiento, resultado
     FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?',
    [$cump057]
);
ok($historico !== null, '057 fila histórica intacta');
ok((string)$historico['fecha_realizacion'] === '2025-05-10', '057 fecha de ejecución 10/05/2025');
ok((string)$historico['resultado'] === 'APROBADO', '057 resultado histórico APROBADO');
$consulta057 = $cumplimientos->consultar(1, 20, [
    'persona_id' => $p057,
    'estado' => 'VENCIDA',
    'estado_laboral' => 'Activo',
]);
ok(itemDe($consulta057['items'], $asig057) !== null, '057 aparece filtrado como VENCIDA');

echo "== RF-CU-058 Cambio de cargo no reescribe historial ==\n";
$reg058 = $cumplimientos->registrar([
    'asignacion_id' => $asig058,
    'sesion_id' => $sesionSinId,
    'fecha_realizacion' => $fecha,
    'resultado' => 'APROBADO',
    'horas_efectivas' => 4,
], 1);
ok((string)$reg058['resultado'] === 'APROBADO', '058 APROBADO en cargo A');
$detalleAntes = $cumplimientos->consultarDetalle($asig058);
ok(($detalleAntes['trabajador']['cargo'] ?? '') === $nombreCargoA, '058 historial con cargo A');

$editado = $personal->editar($p058, [
    'cargo_id' => $cargoB,
    'correo_corporativo' => 'cump.058.' . $stamp . '@hseq.test',
    'proyecto' => $proyecto,
]);
ok((int)($editado['cargo_id'] ?? 0) === $cargoB, '058 ficha pasa a cargo B');

$detalleDespues = $cumplimientos->consultarDetalle($asig058);
ok(($detalleDespues['trabajador']['cargo'] ?? '') === $nombreCargoA, '058 detalle conserva cargo del snapshot');
ok((string)$detalleDespues['estado_actual'] === 'COMPLETADA', '058 historial sigue COMPLETADA');

$sit058 = $cumplimientos->consultarTrabajador($p058);
ok(($sit058['trabajador']['cargo'] ?? '') === $nombreCargoB, '058 situación actual usa cargo B');
$capsAplicables = array_map(
    static fn (array $f): int => (int)($f['capacitacion_id'] ?? 0),
    $sit058['aplicables'] ?? []
);
ok(in_array($capCargoB, $capsAplicables, true), '058 obligaciones actuales incluyen matriz del cargo nuevo');
ok(itemDe($sit058['items'], $asig058) !== null, '058 la obligación histórica sigue en la consulta del trabajador');

echo "== RF-CU-059 Inactivo conserva historial y se filtra ==\n";
$inactivado = $personal->inactivar($p054, $actor);
ok(($inactivado['estado'] ?? '') === 'Inactivo', '059 queda Inactivo');

$activos = $cumplimientos->consultar(1, 20, [
    'persona_id' => $p054,
    'estado_laboral' => 'Activo',
]);
ok(itemDe($activos['items'], $asig054) === null, '059 no aparece con filtro Activo');

$inactivos = $cumplimientos->consultar(1, 20, [
    'persona_id' => $p054,
    'estado_laboral' => 'Inactivo',
]);
ok(itemDe($inactivos['items'], $asig054) !== null, '059 aparece con filtro Inactivo');
ok(
    (itemDe($inactivos['items'], $asig054)['estado_calculado'] ?? '') === 'COMPLETADA',
    '059 historial COMPLETADA se conserva'
);

$detalleInactivo = $cumplimientos->consultarDetalle($asig054);
ok($detalleInactivo['estado_actual'] === 'COMPLETADA', '059 detalle histórico intacto');
ok(($detalleInactivo['trabajador']['estado_laboral'] ?? '') === 'Inactivo', '059 detalle refleja Inactivo');

$sit059 = $cumplimientos->consultarTrabajador($p054);
ok(($sit059['trabajador']['estado_laboral'] ?? '') === 'Inactivo', '059 situación del trabajador Inactivo');
ok(itemDe($sit059['items'], $asig054) !== null, '059 consulta por trabajador conserva la obligación');

echo "\nListo: consulta consolidada de cumplimientos.\n";
