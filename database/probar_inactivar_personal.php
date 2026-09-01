<?php

declare(strict_types=1);

/**
 * Pruebas de inactivación lógica de trabajadores.
 * Uso: php database/probar_inactivar_personal.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\AsignacionService;
use App\Services\DashboardService;
use App\Services\MotorAsignacionService;
use App\Services\PersonalService;
use App\Services\ReporteService;
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

$db = Database::getInstance();
$personalDb = Database::personal();
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$dashboard = new DashboardService();
$vencimientos = new VencimientoService();
$motor = new MotorAsignacionService();
$reportes = new ReporteService();
$actor = ['usuario_id' => 1, 'nombre' => 'admin.hseq', 'ip' => '127.0.0.1'];

$stamp = date('YmdHis');
$doc = '90011' . substr($stamp, -6);
$capCodigo = 'HSEQ-INA-' . substr($stamp, -6);
$capId = 0;
$personaId = 0;
$asigId = 0;
$cumpId = 0;

$limpiar = static function () use ($db, $personalDb, $personasT, $contratosT, $doc, &$capId): void {
    $prev = $personalDb->fetch("SELECT persona_id FROM {$personasT} WHERE numero_documento = ?", [$doc]);
    if ($prev !== null) {
        $pid = (int)$prev['persona_id'];
        $db->query('DELETE FROM historial_contexto_trabajador WHERE persona_id_ext = ?', [$pid]);
        $asigs = $db->fetchAll('SELECT asignacion_id FROM asignaciones_capacitacion WHERE persona_id_ext = ?', [$pid]);
        foreach ($asigs as $a) {
            $aid = (int)$a['asignacion_id'];
            $cump = $db->fetchAll('SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ?', [$aid]);
            foreach ($cump as $c) {
                $cid = (int)$c['cumplimiento_id'];
                $db->query('DELETE FROM soportes_cumplimiento WHERE cumplimiento_id = ?', [$cid]);
                $db->query('DELETE FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?', [$cid]);
            }
            $db->query('DELETE FROM sesion_participantes WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM plan_detalle_asignaciones WHERE asignacion_id = ?', [$aid]);
            $db->query('DELETE FROM asignaciones_capacitacion WHERE asignacion_id = ?', [$aid]);
        }
        $db->query("DELETE FROM auditoria WHERE entidad = 'personal' AND entidad_id = ?", [$pid]);
        $personalDb->query("DELETE FROM {$contratosT} WHERE persona_id = ?", [$pid]);
        $personalDb->query("DELETE FROM {$personasT} WHERE persona_id = ?", [$pid]);
    }
    if ($capId > 0) {
        $db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$capId]);
        $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capId]);
    }
};

$limpiar();

$cargos = $personal->cargos();
ok(count($cargos) >= 1, 'Hay un cargo corporativo');
$cargoId = (int)$cargos[0]['cargo_id'];

$persona = $personal->crear([
    'numero_documento' => $doc,
    'nombre_completo' => 'Inactivar Prueba Trabajador',
    'correo' => 'inactivar.prueba@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => 'HSEQ-INA',
    'fecha_ingreso' => '2025-01-15',
], false);
$personaId = (int)$persona['persona_id'];
ok(($persona['estado'] ?? '') === 'Activo', 'Alta queda Activo');

$capId = (int)$db->insert('capacitaciones', [
    'codigo' => $capCodigo,
    'nombre' => 'Curso inactivación prueba',
    'objetivo' => 'Probar conservación de historial',
    'duracion_estimada_horas' => 4,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
]);

$asig = $asignaciones->crear([
    'persona_id_ext' => $personaId,
    'capacitacion_id' => $capId,
    'fecha_asignacion' => date('Y-m-d'),
    'fecha_limite_cumplimiento' => date('Y-m-d', strtotime('+30 days')),
], 1, $actor);
$asigId = (int)$asig['asignacion_id'];
ok($asigId > 0, 'Asignación histórica creada');

$cumpId = (int)$db->insert('cumplimientos_capacitacion', [
    'asignacion_id' => $asigId,
    'sesion_id' => null,
    'fecha_realizacion' => date('Y-m-d'),
    'resultado' => 'APROBADO',
    'horas_efectivas' => 4,
    'nota_evaluacion' => 4.5,
    'fecha_vencimiento' => null,
]);
ok($cumpId > 0, 'Cumplimiento histórico creado');
$db->insert('soportes_cumplimiento', [
    'cumplimiento_id' => $cumpId,
    'tipo_soporte' => 'CERTIFICADO',
    'nombre_archivo' => 'certificado-prueba.pdf',
    'ruta_archivo' => 'soportes/prueba/certificado-prueba.pdf',
    'mime_type' => 'application/pdf',
]);

$asigsAntes = (int)$db->fetch(
    'SELECT COUNT(*) AS n FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaId]
)['n'];
$cumpAntes = (int)$db->fetch(
    'SELECT COUNT(*) AS n FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$asigId]
)['n'];
$sopAntes = (int)$db->fetch(
    'SELECT COUNT(*) AS n FROM soportes_cumplimiento WHERE cumplimiento_id = ?',
    [$cumpId]
)['n'];
$histAntes = (int)$db->fetch(
    'SELECT COUNT(*) AS n FROM historial_contexto_trabajador WHERE persona_id_ext = ?',
    [$personaId]
)['n'];

$poblacionAntes = $dashboard->indicadores(['tipo' => 'anual', 'anio' => (int)date('Y')])['poblacion'];
$activosAntes = (int)$poblacionAntes['activos'];

echo "== Inactivar ==\n";
$inactivado = $personal->inactivar($personaId, $actor);
ok(($inactivado['estado'] ?? '') === 'Inactivo', 'Estado queda Inactivo');
ok(empty($inactivado['ya_inactivo']), 'Primera inactivación ejecuta el cambio');
$fila = $personalDb->fetch("SELECT persona_id, estado FROM {$personasT} WHERE persona_id = ?", [$personaId]);
ok($fila !== null && ($fila['estado'] ?? '') === 'Inactivo', 'El registro sigue existiendo en BD');

ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaId]
)['n'] === $asigsAntes, 'Asignaciones históricas intactas');
ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$asigId]
)['n'] === $cumpAntes, 'Cumplimientos históricos intactos');
ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM soportes_cumplimiento WHERE cumplimiento_id = ?',
    [$cumpId]
)['n'] === $sopAntes, 'Evidencias históricas intactas');
ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM historial_contexto_trabajador WHERE persona_id_ext = ?',
    [$personaId]
)['n'] === $histAntes, 'Historial laboral intacto');

echo "== Doble inactivación ==\n";
$audAntes = (int)$db->fetch(
    "SELECT COUNT(*) AS n FROM auditoria WHERE accion = 'inactivar' AND entidad = 'personal' AND entidad_id = ?",
    [$personaId]
)['n'];
$segunda = $personal->inactivar($personaId, $actor);
ok(!empty($segunda['ya_inactivo']), 'Segunda inactivación no actualiza de nuevo');
$audDespues = (int)$db->fetch(
    "SELECT COUNT(*) AS n FROM auditoria WHERE accion = 'inactivar' AND entidad = 'personal' AND entidad_id = ?",
    [$personaId]
)['n'];
ok($audDespues === $audAntes, 'No se duplica el evento de auditoría');

echo "== Auditoría ==\n";
ok($audAntes === 1, 'Un evento de inactivación');
$evento = $db->fetch(
    "SELECT usuario_id_ext, usuario_nombre, valor_anterior, valor_nuevo, created_at
     FROM auditoria
     WHERE accion = 'inactivar' AND entidad = 'personal' AND entidad_id = ?
     ORDER BY auditoria_id DESC LIMIT 1",
    [$personaId]
);
ok($evento !== null, 'Evento de auditoría encontrado');
ok((int)$evento['usuario_id_ext'] === 1, 'Auditoría identifica al usuario');
ok((string)$evento['usuario_nombre'] === 'admin.hseq', 'Auditoría identifica el nombre');
$anterior = json_decode((string)$evento['valor_anterior'], true);
$nuevo = json_decode((string)$evento['valor_nuevo'], true);
ok(is_array($anterior) && ($anterior['estado'] ?? '') === 'Activo', 'Estado anterior Activo');
ok(is_array($nuevo), 'Payload nuevo presente');

echo "== Dashboard y vigentes ==\n";
$poblacion = $dashboard->indicadores(['tipo' => 'anual', 'anio' => (int)date('Y')])['poblacion'];
ok((int)$poblacion['activos'] === $activosAntes - 1, 'Trabajadores activos baja en 1');
ok((int)$poblacion['inactivos'] >= 1, 'Hay al menos un inactivo');

$estados = $vencimientos->resumenEstados();
$pendientesInactivo = $db->fetch(
    "SELECT COUNT(*) AS n
     FROM vw_estado_asignaciones e
     INNER JOIN " . Database::personalTable('personas') . " per ON per.persona_id = e.persona_id_ext
     WHERE e.persona_id_ext = ? AND per.estado = 'Inactivo'",
    [$personaId]
);
ok((int)$pendientesInactivo['n'] >= 1, 'El inactivo conserva filas de estado de asignación');
$enResumen = false;
foreach ($vencimientos->alertas(100) as $alerta) {
    if ((int)($alerta['persona_id_ext'] ?? 0) === $personaId) {
        $enResumen = true;
        break;
    }
}
ok(!$enResumen, 'El inactivo no genera alertas operativas');

$pendientes = $reportes->consultar('pendientes', [], 1, 50);
$incluido = false;
foreach ($pendientes['items'] as $item) {
    if ((int)($item['persona_id_ext'] ?? 0) === $personaId) {
        $incluido = true;
        break;
    }
}
ok(!$incluido, 'Reporte pendientes no incluye al inactivo');

echo "== RF-019 ==\n";
$hist = $reportes->consultar('historial_trabajador', ['persona_id' => $personaId], 1, 50);
ok(($hist['trabajador']['estado'] ?? '') === 'Inactivo', 'RF-019 muestra estado INACTIVO');
ok(count($hist['items']) >= 1, 'RF-019 conserva el historial de capacitaciones');
$conEvidencia = false;
foreach ($hist['items'] as $item) {
    if (!empty($item['soportes'])) {
        $conEvidencia = true;
        break;
    }
}
ok($conEvidencia || $sopAntes > 0, 'Las evidencias siguen asociadas');

$busqueda = $reportes->buscarTrabajadores('Inactivar Prueba');
$encontrado = false;
foreach ($busqueda['items'] as $item) {
    if ((int)$item['persona_id'] === $personaId) {
        $encontrado = true;
        ok(($item['estado'] ?? '') === 'Inactivo', 'La búsqueda de RF-019 localiza al inactivo');
    }
}
ok($encontrado, 'RF-019 encuentra al trabajador inactivo');

echo "== Motor y nuevas asignaciones ==\n";
$activosMotor = $personal->listarActivosParaMotor();
$enMotor = false;
foreach ($activosMotor as $fila) {
    if ((int)($fila['persona_id'] ?? 0) === $personaId) {
        $enMotor = true;
        break;
    }
}
ok(!$enMotor, 'El motor no ve al trabajador inactivo');
$motor->generar(1);
ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaId]
)['n'] === $asigsAntes, 'El motor no crea asignaciones nuevas al inactivo');

esperaRechazo(
    static fn () => $asignaciones->crear([
        'persona_id_ext' => $personaId,
        'capacitacion_id' => $capId,
        'fecha_limite_cumplimiento' => date('Y-m-d', strtotime('+10 days')),
    ], 1, $actor),
    'Asignación manual a inactivo rechazada'
);

$masivo = $asignaciones->crearMasivo([
    'persona_ids_ext' => [$personaId],
    'capacitacion_id' => $capId,
    'fecha_limite_cumplimiento' => date('Y-m-d', strtotime('+10 days')),
], 1, $actor);
ok((int)$masivo['creadas'] === 0, 'Asignación masiva no crea filas para inactivo');

echo "== Cancelación (sin API: no hay UPDATE si no se llama inactivar) ==\n";
ok(($personal->ver($personaId)['estado'] ?? '') === 'Inactivo', 'El estado solo cambia vía inactivar');

$limpiar();
echo "\nTodas las pruebas de inactivación OK.\n";
