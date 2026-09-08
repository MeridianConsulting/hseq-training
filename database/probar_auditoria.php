<?php

declare(strict_types=1);

/**
 * Pruebas de auditoría: valor anterior/nuevo, actor del token, motor, evidencias y rollback.
 * Uso: php database/probar_auditoria.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Services\AsignacionService;
use App\Services\AuditoriaService;
use App\Services\CapacitacionService;
use App\Services\CatalogService;
use App\Services\CronogramaService;
use App\Services\CumplimientoService;
use App\Services\MatrizService;
use App\Services\MotorAsignacionService;
use App\Services\PersonalService;
use App\Services\PlanAnualService;
use App\Services\SesionService;
use App\Services\SoporteService;

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

function archivoTmp(string $nombre, string $contenido): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'aud');
    file_put_contents($tmp, $contenido);

    return [
        'name' => $nombre,
        'type' => 'application/octet-stream',
        'tmp_name' => $tmp,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($contenido),
    ];
}

function vigenciaId(Database $db, int $cantidad, string $unidad, string $nombre): int
{
    $fila = $db->fetch(
        'SELECT vigencia_id FROM vigencias WHERE cantidad = ? AND unidad = ? LIMIT 1',
        [$cantidad, $unidad]
    );
    if ($fila !== null) {
        return (int)$fila['vigencia_id'];
    }

    return (int)$db->insert('vigencias', [
        'nombre' => $nombre,
        'cantidad' => $cantidad,
        'unidad' => $unidad,
        'activo' => 1,
    ]);
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

function borrarPlanYSesiones(Database $db, SoporteService $soportes, int $anio): void
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
                $soportes->eliminarArchivosDeCumplimiento((int)$c['cumplimiento_id']);
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

function limpiarPersonas(Database $db, $personalDb, SoporteService $soportes, string $personasT, string $contratosT, array $docs): void
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
                $soportes->eliminarArchivosDeCumplimiento((int)$c['cumplimiento_id']);
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

/**
 * @param list<array<string,mixed>> $cambios
 */
function cambioDe(array $cambios, string $campo): ?array
{
    foreach ($cambios as $cambio) {
        if (($cambio['campo'] ?? '') === $campo) {
            return $cambio;
        }
    }

    return null;
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$caps = new CapacitacionService();
$asignaciones = new AsignacionService();
$matriz = new MatrizService();
$motor = new MotorAsignacionService();
$planes = new PlanAnualService();
$sesiones = new SesionService();
$cumplimientos = new CumplimientoService();
$soportes = new SoporteService();
$auditoria = new AuditoriaService();
$catalogos = new CatalogService();
$cronograma = new CronogramaService();

$anioPrueba = 2033;
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$docs = ['9000660101', '9000660102'];
$stamp = date('YmdHis');
$proyecto = 'HSEQ-AUD-' . $stamp;

echo "== Limpieza previa año {$anioPrueba} ==\n";
borrarPlanYSesiones($db, $soportes, $anioPrueba);
limpiarPersonas($db, $personalDb, $soportes, $personasT, $contratosT, $docs);

$usuario = $db->fetch('SELECT usuario_id, nombre_usuario FROM usuarios ORDER BY usuario_id ASC LIMIT 1');
ok($usuario !== null, 'Hay un usuario para el actor');
$usuarioId = (int)$usuario['usuario_id'];
$actor = [
    'usuario_id' => $usuarioId,
    'nombre' => (string)$usuario['nombre_usuario'],
    'ip' => '127.0.0.1',
];

$presencial = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'PRESENCIAL' AND activo = 1 LIMIT 1");
$ubicacion = $db->fetch('SELECT ubicacion_id FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($presencial !== null && $ubicacion !== null && $proveedor !== null, 'Catálogos de sesión disponibles');

$cargos = $personal->cargos();
ok(count($cargos) >= 1, 'Hay cargos corporativos');
$cargoId = (int)$cargos[0]['cargo_id'];
$per12 = periodicidadId($db, 12, 'MESES', 'AUD-PRU-12M');
$vig12 = vigenciaId($db, 12, 'MESES', '12 meses');
$vig24 = vigenciaId($db, 24, 'MESES', '24 meses');
$procesoFila = $db->fetch('SELECT proceso_id FROM procesos WHERE activo = 1 ORDER BY proceso_id ASC LIMIT 1');
ok($procesoFila !== null, 'Hay un proceso para matriz');
$procesoId = (int)$procesoFila['proceso_id'];

echo "\n== Crear capacitación ==\n";
$antesTs = new DateTimeImmutable('now');
$cap = $caps->crear([
    'codigo' => 'AUD-' . $stamp,
    'nombre' => 'Auditoría vencimiento',
    'objetivo' => 'Prueba de trazabilidad',
    'duracion_estimada_horas' => 4,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
    'periodicidad_default_id' => $per12,
    'vigencia_id' => $vig12,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
    'certificado' => 0,
], $usuarioId, $actor);
$capId = (int)$cap['capacitacion_id'];
ok($capId > 0, 'Capacitación creada');

$alta = $auditoria->listar(1, 5, ['entidad' => 'capacitaciones', 'entidad_id' => $capId, 'accion' => 'crear']);
ok($alta['total'] >= 1, 'Auditoría crear capacitación');
$filaAlta = $alta['items'][0];
ok((int)$filaAlta['usuario_id_ext'] === $usuarioId, 'Usuario del actor, no del body');
ok((string)$filaAlta['nombre_usuario'] === $actor['nombre'], 'Nombre de usuario en auditoría');
$created = substr((string)$filaAlta['created_at'], 0, 19);
$dtCreated = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $created);
ok($dtCreated instanceof DateTimeImmutable, 'created_at tiene fecha y hora');
ok(
    abs($dtCreated->getTimestamp() - $antesTs->getTimestamp()) <= 2,
    'created_at alineado a America/Bogota ±2s got=' . $created
);

echo "\n== Duración 4 → 8 ==\n";
$caps->actualizar($capId, ['duracion_estimada_horas' => 8], $actor);
$updCap = $auditoria->listar(1, 5, ['entidad' => 'capacitaciones', 'entidad_id' => $capId, 'accion' => 'actualizar']);
ok($updCap['total'] >= 1, 'Auditoría actualizar capacitación');
$diffDur = cambioDe($updCap['items'][0]['cambios'] ?? [], 'duracion_estimada_horas');
ok($diffDur !== null, 'Diff incluye duración');
ok((int)$diffDur['anterior'] === 4 && (int)$diffDur['nuevo'] === 8, 'Duración 4 → 8');
ok(count($updCap['items'][0]['cambios']) === 1, 'Un solo cambio en el diff');
ok(isset($updCap['items'][0]['resumen']) && $updCap['items'][0]['resumen'] !== '', 'Listado incluye resumen');
ok(($updCap['items'][0]['modulo'] ?? null) === 'capacitaciones', 'Módulo capacitaciones');

echo "\n== RF-AU-041 Vigencia 12 → 24 meses ==\n";
$caps->actualizar($capId, ['vigencia_id' => $vig24], $actor);
$updVig = $auditoria->listar(1, 5, ['entidad' => 'capacitaciones', 'entidad_id' => $capId, 'accion' => 'actualizar']);
$diffVig = cambioDe($updVig['items'][0]['cambios'] ?? [], 'vigencia_nombre');
ok($diffVig !== null, 'Diff incluye vigencia');
ok((string)$diffVig['anterior'] !== (string)$diffVig['nuevo'], 'Vigencia cambió de nombre');
ok(count($updVig['items'][0]['cambios']) === 1, 'Solo vigencia en el evento');
$caps->actualizar($capId, ['vigencia_id' => $vig12], $actor);

echo "\n== Personas y asignaciones ==\n";
$p1 = $personal->crear([
    'numero_documento' => $docs[0],
    'nombre_completo' => 'Auditoría Uno',
    'correo' => 'aud1@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => $proyecto,
    'fecha_ingreso' => '2026-01-15',
], false);
$p2 = $personal->crear([
    'numero_documento' => $docs[1],
    'nombre_completo' => 'Auditoría Dos',
    'correo' => 'aud2@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => $proyecto,
    'fecha_ingreso' => '2026-01-15',
], false);
$persona1 = (int)$p1['persona_id'];
$persona2 = (int)$p2['persona_id'];

echo "\n== RF-AU-042 Celda matriz No aplica → Aplica ==\n";
$celda = $matriz->crear([
    'capacitacion_id' => $capId,
    'cargo_id_ext' => $cargoId,
    'proceso_id' => $procesoId,
    'proyecto' => $proyecto,
    'periodicidad_id' => $per12,
    'obligatoria' => 1,
    'activa' => 0,
], $usuarioId, $actor);
$celdaId = (int)$celda['matriz_aplicabilidad_id'];
ok($celdaId > 0, 'Celda de matriz creada inactiva');
$matriz->actualizar($celdaId, ['activa' => 1], $actor);
$audCelda = $auditoria->listar(1, 5, [
    'entidad' => 'matriz_aplicabilidad',
    'entidad_id' => $celdaId,
    'accion' => 'reactivar',
]);
ok($audCelda['total'] >= 1, 'Auditoría reactivar celda');
$diffAplica = cambioDe($audCelda['items'][0]['cambios'] ?? [], 'aplica');
ok($diffAplica !== null, 'Diff incluye aplica');
ok((string)$diffAplica['anterior'] === 'No aplica' && (string)$diffAplica['nuevo'] === 'Aplica', 'No aplica → Aplica');

$manual = $asignaciones->crear([
    'persona_id_ext' => $persona1,
    'capacitacion_id' => $capId,
    'fecha_limite_cumplimiento' => '2027-12-31',
], $usuarioId, $actor);
$asig1 = (int)$manual['asignacion_id'];
$audManual = $auditoria->listar(1, 5, ['entidad' => 'asignaciones_capacitacion', 'entidad_id' => $asig1, 'accion' => 'crear']);
ok($audManual['total'] >= 1, 'RF-AU-043 auditoría asignación manual');
ok(($audManual['items'][0]['valor_nuevo']['origen'] ?? null) === 'MANUAL', 'Origen MANUAL');
ok((int)($audManual['items'][0]['valor_nuevo']['persona_id_ext'] ?? 0) === $persona1, 'Asignación manual deja la persona');
ok((int)($audManual['items'][0]['valor_nuevo']['capacitacion_id'] ?? 0) === $capId, 'Asignación manual deja la capacitación');

$masivo = $asignaciones->crearMasivo([
    'persona_ids_ext' => [$persona2],
    'capacitacion_id' => $capId,
    'fecha_limite_cumplimiento' => '2027-12-31',
], $usuarioId, $actor);
ok((int)$masivo['creadas'] === 1, 'Asignación masiva creó 1');
$asig2 = (int)$masivo['items'][0]['asignacion_id'];
$audMasivo = $auditoria->listar(1, 10, ['entidad' => 'asignaciones_capacitacion', 'accion' => 'asignar_masivo']);
$hayMasivo = false;
foreach ($audMasivo['items'] as $item) {
    $ids = $item['valor_nuevo']['asignacion_ids'] ?? [];
    if (in_array($asig2, $ids, true)) {
        $hayMasivo = true;
        ok((int)($item['valor_nuevo']['trabajadores'] ?? 0) === 1, 'Masivo un solo evento resumen');
        ok((int)($item['valor_nuevo']['seleccionados'] ?? 0) === 1, 'RF-AU-044 seleccionados');
        ok((int)($item['valor_nuevo']['creadas'] ?? 0) === 1, 'RF-AU-044 creadas');
        ok(array_key_exists('omitidas', $item['valor_nuevo']), 'RF-AU-044 omitidas');
        ok(array_key_exists('errores', $item['valor_nuevo']), 'RF-AU-044 errores');
        ok((int)($item['valor_nuevo']['capacitacion_id'] ?? 0) === $capId, 'RF-AU-044 capacitación');
        break;
    }
}
ok($hayMasivo, 'Auditoría asignar_masivo');

echo "\n== Motor de asignaciones ==\n";
$capMotor = $caps->crear([
    'codigo' => 'AUDM-' . $stamp,
    'nombre' => 'Motor automático',
    'objetivo' => 'Prueba motor',
    'duracion_estimada_horas' => 2,
    'criticidad' => 'BAJA',
    'estado' => 'ACTIVA',
    'periodicidad_default_id' => $per12,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
], $usuarioId, $actor);
$capMotorId = (int)$capMotor['capacitacion_id'];
$matriz->crear([
    'capacitacion_id' => $capMotorId,
    'cargo_id_ext' => $cargoId,
    'proceso_id' => $procesoId,
    'proyecto' => $proyecto,
    'periodicidad_id' => $per12,
    'obligatoria' => 1,
], $usuarioId);

$gen = $motor->generar($usuarioId, ['capacitacion_id' => $capMotorId]);
ok((int)$gen['creadas'] >= 1, 'Motor creó asignaciones got=' . (int)$gen['creadas']);
$audMotor = $auditoria->listar(1, 10, [
    'entidad' => 'asignaciones_capacitacion',
    'accion' => 'generar_automaticas',
]);
$eventoMotor = null;
foreach ($audMotor['items'] as $item) {
    $ids = $item['valor_nuevo']['asignacion_ids'] ?? [];
    $origen = $item['valor_nuevo']['origen'] ?? null;
    if ($ids !== [] && in_array($origen, ['AUTOMATICA', 'MIXTO'], true)) {
        $eventoMotor = $item;
        break;
    }
}
ok($eventoMotor !== null, 'Auditoría generar_automaticas');
ok($eventoMotor['usuario_id_ext'] === null, 'Motor no atribuye usuario humano');
ok((string)$eventoMotor['nombre_usuario'] === AuditoriaService::ACTOR_SISTEMA, 'Nombre Motor de asignaciones');

echo "\n== Cumplimiento y vencimiento 2027-08-01 → 2027-08-15 ==\n";
$plan = $planes->crear(['anio' => $anioPrueba], $usuarioId);
$planId = (int)$plan['plan_anual_id'];
$incluir = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => [$asig1],
    'mes_programado' => 8,
]);
$detalleId = (int)$incluir['items'][0]['plan_detalle_id'];
$planes->enviarRevision($planId);
$planes->aprobar($planId, $usuarioId);

echo "\n== RF-AU-045 Reprogramar fecha ==\n";
$cronograma->reprogramar($detalleId, ['fecha_programada' => '2033-08-22'], $actor);
$audRep = $auditoria->listar(1, 5, [
    'entidad' => 'plan_anual_detalle',
    'entidad_id' => $detalleId,
    'accion' => 'reprogramar',
]);
ok($audRep['total'] >= 1, 'Auditoría reprogramar');
$diffFecha = cambioDe($audRep['items'][0]['cambios'] ?? [], 'fecha_programada');
ok($diffFecha !== null, 'Diff incluye fecha_programada');
ok(
    (string)$diffFecha['anterior'] === '2033-08-01' && (string)$diffFecha['nuevo'] === '2033-08-22',
    'Fecha 2033-08-01 → 2033-08-22'
);
ok((int)($audRep['items'][0]['usuario_id_ext'] ?? 0) === $usuarioId, 'Reprogramar atribuye al usuario');

$sesion = $sesiones->crear([
    'plan_detalle_id' => $detalleId,
    'fecha' => '2033-08-01',
    'hora' => '08:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 4,
    'asignacion_ids' => [$asig1],
], $usuarioId);
$sesionId = (int)$sesion['sesion_id'];
echo "\n== RF-AU-046 Asistencia por trabajador ==\n";
$sesiones->guardarAsistencia($sesionId, [
    'items' => [['asignacion_id' => $asig1, 'estado_asistencia' => 'ASISTIO']],
], $usuarioId, $actor);
$audAsis = $auditoria->listar(1, 5, [
    'entidad' => 'sesiones_capacitacion',
    'entidad_id' => $sesionId,
    'accion' => 'asistencia',
]);
ok($audAsis['total'] >= 1, 'Auditoría asistencia');
$diffAsis = cambioDe($audAsis['items'][0]['cambios'] ?? [], 'estado_asistencia');
ok($diffAsis !== null, 'Diff de asistencia por persona');
ok((string)$diffAsis['nuevo'] === 'ASISTIO', 'Estado nuevo ASISTIO');
ok(isset($diffAsis['persona_id_ext']) && (int)$diffAsis['persona_id_ext'] === $persona1, 'Asistencia identifica al trabajador');

$uno = $cumplimientos->registrar([
    'asignacion_id' => $asig1,
    'sesion_id' => $sesionId,
    'fecha_realizacion' => '2026-08-01',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'usuario_id' => 99999,
], $usuarioId, $actor);
$cumpId = (int)$uno['cumplimiento_id'];
ok((string)$uno['fecha_vencimiento'] === '2027-08-01', 'Vencimiento calculado 2027-08-01 got=' . ($uno['fecha_vencimiento'] ?? 'null'));
$audCrearCump = $auditoria->listar(1, 5, [
    'entidad' => 'cumplimientos_capacitacion',
    'entidad_id' => $cumpId,
    'accion' => 'crear',
]);
ok($audCrearCump['total'] >= 1, 'Auditoría crear cumplimiento');
ok((int)$audCrearCump['items'][0]['usuario_id_ext'] === $usuarioId, 'usuario_id falso del body se ignora');
ok(($audCrearCump['items'][0]['origen'] ?? null) === AuditoriaService::ORIGEN_SISTEMA, 'Alta con origen sistema');

$editVence = $cumplimientos->actualizar($cumpId, [
    'fecha_realizacion' => '2026-08-01',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
    'fecha_vencimiento' => '2027-08-15',
    'usuario_id' => 88888,
], $usuarioId, $actor);
ok((string)$editVence['fecha_vencimiento'] === '2027-08-15', 'Override 2027-08-15');
$audVence = $auditoria->listar(1, 5, [
    'entidad' => 'cumplimientos_capacitacion',
    'entidad_id' => $cumpId,
    'accion' => 'actualizar',
]);
ok($audVence['total'] >= 1, 'Auditoría actualizar vencimiento');
$ev = $audVence['items'][0];
ok((int)$ev['usuario_id_ext'] === $usuarioId, 'Override atribuye al actor del token');
ok(($ev['origen'] ?? null) === AuditoriaService::ORIGEN_USUARIO, 'Override origen usuario');
$cv = cambioDe($ev['cambios'] ?? [], 'fecha_vencimiento');
ok($cv !== null, 'Diff incluye fecha_vencimiento');
ok((string)$cv['anterior'] === '2027-08-01' && (string)$cv['nuevo'] === '2027-08-15', '2027-08-01 → 2027-08-15');

echo "\n== Tres campos a la vez ==\n";
$tres = $cumplimientos->actualizar($cumpId, [
    'fecha_realizacion' => '2026-08-01',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 6,
    'observaciones' => 'Ajuste HSEQ',
    'fecha_vencimiento' => '2027-08-20',
], $usuarioId, $actor);
ok((string)$tres['fecha_vencimiento'] === '2027-08-20', 'Tercer vencimiento persistido');
$audTres = $auditoria->listar(1, 5, [
    'entidad' => 'cumplimientos_capacitacion',
    'entidad_id' => $cumpId,
    'accion' => 'actualizar',
]);
$camposTres = [];
foreach ($audTres['items'][0]['cambios'] ?? [] as $cambio) {
    $camposTres[] = $cambio['campo'];
}
ok(in_array('horas_efectivas', $camposTres, true), 'Diff horas');
ok(in_array('observaciones', $camposTres, true), 'Diff observaciones');
ok(in_array('fecha_vencimiento', $camposTres, true), 'Diff vencimiento');
ok(count($camposTres) >= 3, 'Tres campos en el mismo evento');

echo "\n== Evidencias ==\n";
$pdf = "%PDF-1.4\n%evidencia auditoria\n";
$cargado = $soportes->cargar(
    $cumpId,
    archivoTmp('evidencia_aud.pdf', $pdf),
    'CERTIFICADO',
    $usuarioId,
    $actor
);
$soporteId = (int)$cargado['soporte_id'];
ok($soporteId > 0, 'Evidencia cargada');
$audCargar = $auditoria->listar(1, 5, ['entidad' => 'soportes_cumplimiento', 'entidad_id' => $soporteId, 'accion' => 'cargar']);
ok($audCargar['total'] >= 1, 'Auditoría cargar evidencia');
ok(($audCargar['items'][0]['valor_nuevo']['nombre_archivo'] ?? null) === 'evidencia_aud.pdf', 'Nombre de archivo, no binario');

$soportes->eliminar($soporteId, $actor);
ok($soportes->contar($cumpId) === 0, 'Evidencia eliminada del cumplimiento');
$audEli = $auditoria->listar(1, 5, ['entidad' => 'soportes_cumplimiento', 'entidad_id' => $soporteId, 'accion' => 'eliminar']);
ok($audEli['total'] >= 1, 'Auditoría eliminar permanece');
ok(($audEli['items'][0]['valor_nuevo']['nombre_archivo'] ?? null) === 'evidencia_aud.pdf', 'Baja conserva el nombre');

echo "\n== Rollback si falla la auditoría ==\n";
$antesRollback = $db->fetch(
    'SELECT horas_efectivas, fecha_vencimiento FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?',
    [$cumpId]
);
AuditoriaService::$fallarRegistro = true;
$lanzo = false;
try {
    $cumplimientos->actualizar($cumpId, [
        'fecha_realizacion' => '2026-08-01',
        'horas_efectivas' => 3,
        'fecha_vencimiento' => '2027-09-01',
    ], $usuarioId, $actor);
} catch (RuntimeException $e) {
    $lanzo = true;
    ok(str_contains($e->getMessage(), 'auditoría'), 'Fallo forzado de auditoría');
} finally {
    AuditoriaService::$fallarRegistro = false;
}
ok($lanzo, 'Update lanza si no puede auditar');
$despuesRollback = $db->fetch(
    'SELECT horas_efectivas, fecha_vencimiento FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?',
    [$cumpId]
);
ok(
    (string)$despuesRollback['fecha_vencimiento'] === (string)$antesRollback['fecha_vencimiento']
        && (float)$despuesRollback['horas_efectivas'] === (float)$antesRollback['horas_efectivas'],
    'El cumplimiento no queda a medias'
);

echo "\n== RF-AU-047 Nota 3.75 → 4.25 (escala 0-5, equivalente 75→85) ==\n";
$caps->actualizar($capId, ['evaluacion' => 1, 'nota_minima' => 3], $actor);
$cumplimientos->registrarEvaluaciones([
    'sesion_id' => $sesionId,
    'items' => [['asignacion_id' => $asig1, 'nota' => 3.75]],
], $usuarioId, $actor);
$cumplimientos->registrarEvaluaciones([
    'sesion_id' => $sesionId,
    'items' => [['asignacion_id' => $asig1, 'nota' => 4.25]],
], $usuarioId, $actor);
$audNotas = $auditoria->listar(1, 5, [
    'entidad' => 'cumplimientos_capacitacion',
    'entidad_id' => $sesionId,
    'accion' => 'registrar_evaluaciones',
]);
ok($audNotas['total'] >= 1, 'Auditoría registrar evaluaciones');
$diffNota = cambioDe($audNotas['items'][0]['cambios'] ?? [], 'nota_evaluacion');
ok($diffNota !== null, 'Diff incluye nota_evaluacion');
ok((float)$diffNota['anterior'] === 3.75 && (float)$diffNota['nuevo'] === 4.25, 'Nota 3.75 → 4.25');

echo "\n== RF-AU-048 Inactivar proyecto ==\n";
$defProyectos = $catalogos->definicion('proyectos');
$proyectoCat = $catalogos->crear($defProyectos, ['nombre' => 'HSEQ-AUD-PROJ-' . $stamp], $actor);
$proyectoCatId = (int)$proyectoCat['proyecto_id'];
$catalogos->eliminar($defProyectos, $proyectoCatId, $actor);
$audProj = $auditoria->listar(1, 5, [
    'entidad' => 'proyectos',
    'entidad_id' => $proyectoCatId,
    'accion' => 'inactivar',
]);
ok($audProj['total'] >= 1, 'Auditoría inactivar proyecto');
$diffEstado = cambioDe($audProj['items'][0]['cambios'] ?? [], 'activo');
ok($diffEstado !== null, 'Diff Estado del proyecto');
ok((string)$diffEstado['anterior'] === 'Activo' && (string)$diffEstado['nuevo'] === 'Inactivo', 'Activo → Inactivo');

echo "\n== Filtros de listado ==\n";
$filtrado = $auditoria->listar(1, 20, [
    'entidad' => 'cumplimientos_capacitacion',
    'accion' => 'actualizar',
    'usuario' => $actor['nombre'],
    'entidad_id' => $cumpId,
]);
ok($filtrado['total'] >= 1, 'Filtros entidad/acción/usuario/id');

$porModulo = $auditoria->listar(1, 20, ['modulo' => 'capacitaciones', 'entidad_id' => $capId]);
ok($porModulo['total'] >= 1, 'Filtro módulo capacitaciones');
ok(($porModulo['items'][0]['modulo'] ?? null) === 'capacitaciones', 'Módulo derivado capacitaciones');
ok(($porModulo['items'][0]['modulo_etiqueta'] ?? '') !== '', 'Etiqueta de módulo en listado');
ok(($porModulo['items'][0]['resumen'] ?? '') !== '', 'Resumen en listado');

echo "\n== GET-only y permiso ==\n";
$rutas = (string)file_get_contents(BASE_PATH . '/routes/api.php');
ok(str_contains($rutas, "get('/auditoria'"), 'Ruta GET /auditoria');
ok(str_contains($rutas, 'auditoria.ver'), 'Permiso auditoria.ver');
ok(!preg_match("/->(post|put|patch|delete)\\(['\\\"]\\/auditoria/", $rutas), 'Sin POST/PUT/PATCH/DELETE de auditoría');
$permisos = include BASE_PATH . '/config/permisos.php';
ok(in_array('auditoria.ver', $permisos['codigos'] ?? [], true), 'auditoria.ver en catálogo de permisos');

echo "\nLimpieza...\n";
borrarPlanYSesiones($db, $soportes, $anioPrueba);
limpiarPersonas($db, $personalDb, $soportes, $personasT, $contratosT, $docs);
$db->query(
    'DELETE FROM plan_detalle_asignaciones WHERE asignacion_id IN (
        SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id IN (?, ?)
    )',
    [$capId, $capMotorId]
);
$db->query('DELETE FROM asignaciones_capacitacion WHERE capacitacion_id IN (?, ?)', [$capId, $capMotorId]);
$db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id IN (?, ?)', [$capId, $capMotorId]);
$db->query('DELETE FROM capacitaciones WHERE capacitacion_id IN (?, ?)', [$capId, $capMotorId]);

echo "TODAS LAS PRUEBAS DE AUDITORÍA PASARON\n";
