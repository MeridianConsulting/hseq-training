<?php

declare(strict_types=1);

/**
 * Consulta de Personal Corporativo: listado, filtros, perfil y cargo nuevo sin perder historial.
 * Uso: php database/probar_personal_consulta.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\AlertaRepository;
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

/**
 * @param list<array<string,mixed>> $items
 */
function buscarItem(array $items, int $personaId): ?array
{
    foreach ($items as $item) {
        if ((int)($item['persona_id'] ?? 0) === $personaId) {
            return $item;
        }
    }

    return null;
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$alertasRepo = new AlertaRepository();
$actor = ['usuario_id' => 1, 'nombre' => 'admin.hseq', 'ip' => '127.0.0.1'];

$stamp = date('YmdHis');
$doc = '90012' . substr($stamp, -6);
$capCodigo = 'HSEQ-PC-' . substr($stamp, -6);
$correo = 'consulta.prueba.' . substr($stamp, -6) . '@hseq.test';
$capId = 0;
$personaId = 0;
$asigId = 0;
$cumpId = 0;
$matrizId = 0;

$limpiar = static function () use ($db, $personalDb, $personasT, $contratosT, $doc, &$capId, &$matrizId): void {
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
    if ($matrizId > 0) {
        $db->query('DELETE FROM matriz_aplicabilidad WHERE matriz_aplicabilidad_id = ?', [$matrizId]);
        $matrizId = 0;
    }
    if ($capId > 0) {
        $db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id = ?', [$capId]);
        $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$capId]);
    }
};

$limpiar();
register_shutdown_function($limpiar);

$cargos = $personal->cargos();
ok(count($cargos) >= 2, 'Hay al menos dos cargos corporativos');
$cargoA = (int)$cargos[0]['cargo_id'];
$cargoB = (int)$cargos[1]['cargo_id'];

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

$persona = $personal->crear([
    'numero_documento' => $doc,
    'nombre_completo' => 'Consulta Prueba Trabajador',
    'correo' => $correo,
    'cargo_id' => $cargoA,
    'proyecto' => 'FRONTERA',
    'fecha_ingreso' => '2025-03-01',
], false);
$personaId = (int)$persona['persona_id'];
ok($personaId > 0, 'Trabajador creado en corporativa');
ok(($persona['estado'] ?? '') === 'Activo', 'Alta queda Activo');

$capId = (int)$db->insert('capacitaciones', [
    'codigo' => $capCodigo,
    'nombre' => 'Curso consulta personal',
    'objetivo' => 'Probar perfil integrado',
    'duracion_estimada_horas' => 4,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
]);
ok($capId > 0, 'Capacitación de prueba creada');

$matrizId = (int)$db->insert('matriz_aplicabilidad', [
    'capacitacion_id' => $capId,
    'cargo_id_ext' => $cargoA,
    'area_id' => null,
    'proceso_id' => $procesoId,
    'ambito' => $esGP ? 'PROYECTO' : 'ADMINISTRACION',
    'proyecto' => $esGP ? 'FRONTERA' : null,
    'periodicidad_id' => null,
    'obligatoria' => 1,
    'activa' => 1,
]);
ok($matrizId > 0, 'Regla de matriz para el cargo');

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
    'fecha_vencimiento' => date('Y-m-d', strtotime('+180 days')),
]);
ok($cumpId > 0, 'Cumplimiento histórico creado');
$db->insert('soportes_cumplimiento', [
    'cumplimiento_id' => $cumpId,
    'tipo_soporte' => 'CERTIFICADO',
    'nombre_archivo' => 'certificado-consulta.pdf',
    'ruta_archivo' => 'soportes/prueba/certificado-consulta.pdf',
    'mime_type' => 'application/pdf',
]);

echo "== Mensajes de consulta ==\n";
esperaRechazo(
    static fn () => $personal->ver(2147483647),
    '404 ficha: ' . PersonalService::MENSAJE_404,
    404
);
esperaRechazo(
    static fn () => $personal->perfil(2147483647),
    '404 perfil: ' . PersonalService::MENSAJE_404,
    404
);
esperaRechazo(
    static fn () => $personal->listar(1, 10, null, 'Activo', null, 'PROYECTO-INVENTADO', null),
    'Proyecto inválido',
    422
);

echo "== Listado y búsqueda ==\n";
$opciones = $personal->opciones();
ok(isset($opciones['procesos'], $opciones['proyectos'], $opciones['cargos']), 'Opciones de consulta');
ok(in_array('FRONTERA', $opciones['proyectos'], true), 'Catálogo de proyecto FRONTERA');

$porDoc = $personal->listar(1, 20, $doc, 'Activo', null);
ok(buscarItem($porDoc['items'], $personaId) !== null, 'Listar trabajador existente por documento');

$porNombre = $personal->listar(1, 20, 'Consulta Prueba', 'Activo', null);
ok(buscarItem($porNombre['items'], $personaId) !== null, 'Buscar por nombre');

$porCorreo = $personal->listar(1, 20, $correo, 'Activo', null);
ok(buscarItem($porCorreo['items'], $personaId) !== null, 'Buscar por correo');

$item = buscarItem($porDoc['items'], $personaId);
ok($item !== null && ($item['cargo_id'] ?? null) === $cargoA, 'Listado refleja el cargo corporativo');
ok($item !== null && ($item['proyecto'] ?? null) === 'FRONTERA', 'Listado refleja el proyecto del contrato');
$nombresProceso = array_map(
    static fn (array $p): string => (string)$p['nombre'],
    $item['procesos'] ?? []
);
ok(in_array((string)$proceso['nombre'], $nombresProceso, true), 'El ítem incluye el proceso derivado de la matriz');

echo "== Filtros ==\n";
$porCargo = $personal->listar(1, 20, $doc, 'Activo', $cargoA);
ok(buscarItem($porCargo['items'], $personaId) !== null, 'Filtro por cargo coincide');
$otroCargo = $personal->listar(1, 20, $doc, 'Activo', $cargoB);
ok(buscarItem($otroCargo['items'], $personaId) === null, 'Filtro por otro cargo no lo incluye');

$porProceso = $personal->listar(1, 20, $doc, 'Activo', null, null, $procesoId);
ok(buscarItem($porProceso['items'], $personaId) !== null, 'Filtro por proceso (matriz) coincide');

$porProyecto = $personal->listar(1, 20, $doc, 'Activo', null, 'FRONTERA', $esGP ? $procesoId : null);
ok(buscarItem($porProyecto['items'], $personaId) !== null, 'Filtro por proyecto del contrato');

echo "== Perfil ==\n";
$perfil = $personal->perfil($personaId);
ok((int)($perfil['ficha']['persona_id'] ?? 0) === $personaId, 'Perfil trae ficha corporativa');
ok(($perfil['ficha']['correo_corporativo'] ?? null) === $correo, 'Ficha incluye correo');

$capIdsAplicables = array_map(
    static fn (array $f): int => (int)($f['capacitacion_id'] ?? 0),
    $perfil['aplicables'] ?? []
);
ok(in_array($capId, $capIdsAplicables, true), 'Perfil incluye capacitación aplicable');

$asigIds = [];
foreach (array_merge($perfil['pendientes'] ?? [], $perfil['ejecutadas'] ?? []) as $fila) {
    $asigIds[] = (int)($fila['asignacion_id'] ?? 0);
}
ok(in_array($asigId, $asigIds, true), 'La asignación histórica aparece en el perfil');

$cumpIds = array_map(
    static fn (array $f): int => (int)($f['cumplimiento_id'] ?? 0),
    $perfil['cumplimientos'] ?? []
);
ok(in_array($cumpId, $cumpIds, true), 'El cumplimiento histórico aparece en el perfil');

$evalIds = array_map(
    static fn (array $f): int => (int)($f['cumplimiento_id'] ?? 0),
    $perfil['evaluaciones'] ?? []
);
ok(in_array($cumpId, $evalIds, true), 'La evaluación aparece en el perfil');
ok(count($perfil['soportes'] ?? []) >= 1, 'Los soportes aparecen en el perfil');
ok(is_array($perfil['historial_sesiones'] ?? null), 'Historial de sesiones presente');
ok(is_array($perfil['alertas'] ?? null), 'Alertas presentes (pueden estar vacías)');

$ejecutada = false;
foreach ($perfil['ejecutadas'] ?? [] as $fila) {
    if ((int)($fila['asignacion_id'] ?? 0) === $asigId) {
        $ejecutada = true;
    }
}
ok($ejecutada, 'La asignación con cumplimiento APROBADO queda en ejecutadas');

echo "== Cambio de cargo (simula corporativo) ==\n";
$asigsAntes = (int)$db->fetch(
    'SELECT COUNT(*) AS n FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaId]
)['n'];
$cumpAntes = (int)$db->fetch(
    'SELECT COUNT(*) AS n FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$asigId]
)['n'];

$editado = $personal->editar($personaId, [
    'cargo_id' => $cargoB,
    'correo_corporativo' => $correo,
    'proyecto' => 'FRONTERA',
]);
ok((int)($editado['cargo_id'] ?? 0) === $cargoB, 'La ficha refleja el cargo nuevo');

$trasCambio = $personal->listar(1, 20, $doc, 'Activo', null);
$itemNuevo = buscarItem($trasCambio['items'], $personaId);
ok($itemNuevo !== null && (int)($itemNuevo['cargo_id'] ?? 0) === $cargoB, 'El listado refleja el cargo nuevo');

$perfilNuevo = $personal->perfil($personaId);
ok((int)($perfilNuevo['ficha']['cargo_id'] ?? 0) === $cargoB, 'El perfil refleja el cargo nuevo');

$asigIdsNuevo = [];
foreach (array_merge($perfilNuevo['pendientes'] ?? [], $perfilNuevo['ejecutadas'] ?? []) as $fila) {
    $asigIdsNuevo[] = (int)($fila['asignacion_id'] ?? 0);
}
ok(in_array($asigId, $asigIdsNuevo, true), 'La asignación previa sigue en el perfil');

$cumpIdsNuevo = array_map(
    static fn (array $f): int => (int)($f['cumplimiento_id'] ?? 0),
    $perfilNuevo['cumplimientos'] ?? []
);
ok(in_array($cumpId, $cumpIdsNuevo, true), 'El cumplimiento previo sigue en el perfil');

ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM asignaciones_capacitacion WHERE persona_id_ext = ?',
    [$personaId]
)['n'] >= $asigsAntes, 'No se pierden asignaciones al cambiar cargo');
ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$asigId]
)['n'] === $cumpAntes, 'El cumplimiento previo permanece');
ok((int)$db->fetch(
    'SELECT COUNT(*) AS n FROM historial_contexto_trabajador WHERE persona_id_ext = ?',
    [$personaId]
)['n'] >= 1, 'Queda registro de cambio de cargo en historial laboral');

echo "\nListo: consulta de personal corporativo.\n";
