<?php

declare(strict_types=1);

/**
 * Pruebas de inducción / reinducción independientes de la matriz.
 * Uso: php database/probar_induccion.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\CapacitacionRepository;
use App\Services\AsignacionService;
use App\Services\MatrizService;
use App\Services\MotorAsignacionService;
use App\Services\PersonalService;
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

function tipoPorNombre(Database $db, string $esperado): ?array
{
    foreach ($db->fetchAll('SELECT tipo_capacitacion_id, nombre FROM tipos_capacitacion') as $fila) {
        if (CapacitacionRepository::normalizarTipoNombre((string)$fila['nombre']) === $esperado) {
            return $fila;
        }
    }

    return null;
}

function periodicidadPorNombre(Database $db, string $nombre): ?array
{
    $buscar = strtoupper($nombre);
    foreach ($db->fetchAll(
        'SELECT periodicidad_id, nombre, cantidad, unidad FROM periodicidades WHERE activo = 1'
    ) as $fila) {
        $n = CapacitacionRepository::normalizarTipoNombre((string)$fila['nombre']);
        if ($n === $buscar || ($buscar === 'ANUAL' && (int)$fila['cantidad'] === 1 && strtoupper((string)$fila['unidad']) === 'ANIOS')) {
            return $fila;
        }
        if ($buscar === 'UNICA VEZ' && ((int)$fila['cantidad'] <= 0 || str_contains($n, 'UNICA'))) {
            return $fila;
        }
    }

    return null;
}

function contarAsig(Database $db, int $personaId, int $capId, ?string $origen = null): int
{
    $sql = 'SELECT COUNT(*) AS t FROM asignaciones_capacitacion WHERE persona_id_ext = ? AND capacitacion_id = ?';
    $params = [$personaId, $capId];
    if ($origen !== null) {
        $sql .= ' AND origen = ?';
        $params[] = $origen;
    }
    $fila = $db->fetch($sql, $params);

    return (int)($fila['t'] ?? 0);
}

function pendientesCap(Database $db, int $personaId, int $capId): int
{
    $fila = $db->fetch(
        'SELECT COUNT(*) AS t
         FROM asignaciones_capacitacion a
         LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id
         WHERE a.persona_id_ext = ? AND a.capacitacion_id = ? AND c.cumplimiento_id IS NULL',
        [$personaId, $capId]
    );

    return (int)($fila['t'] ?? 0);
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
$matriz = new MatrizService();
$motor = new MotorAsignacionService();
$asignaciones = new AsignacionService();

$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$stamp = date('YmdHis');
$prefijoDoc = '900044' . substr($stamp, -4);
$docs = [];
for ($i = 1; $i <= 12; $i++) {
    $docs[] = $prefijoDoc . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
}

$capIds = [];
$idsMatriz = [];
$proyecto = 'HSEQ-IND-' . $stamp;

echo "== Limpieza previa ==\n";
limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);

$tipoInd = tipoPorNombre($db, 'INDUCCION');
$tipoRei = tipoPorNombre($db, 'REINDUCCION');
ok($tipoInd !== null, 'Existe tipo INDUCCION en el catálogo');
ok($tipoRei !== null, 'Existe tipo REINDUCCION en el catálogo');

$unica = periodicidadPorNombre($db, 'UNICA VEZ');
$anual = periodicidadPorNombre($db, 'ANUAL');
ok($unica !== null, 'Existe periodicidad UNICA VEZ (o cantidad 0)');
ok($anual !== null, 'Existe periodicidad ANUAL');

$cargos = $personal->cargos();
ok(count($cargos) >= 2, 'Hay al menos 2 cargos');
$cargoId = (int)$cargos[0]['cargo_id'];
$cargoAltId = (int)$cargos[1]['cargo_id'];
foreach ($cargos as $c) {
    if (strcasecmp(trim((string)$c['nombre_cargo']), 'Supervisor HSE') === 0) {
        $cargoId = (int)$c['cargo_id'];
        break;
    }
}
foreach ($cargos as $c) {
    if ((int)$c['cargo_id'] !== $cargoId) {
        $cargoAltId = (int)$c['cargo_id'];
        break;
    }
}

$insertCap = static function (
    Database $db,
    string $codigo,
    string $nombre,
    int $tipoId,
    int $periodoId
): int {
    return (int)$db->insert('capacitaciones', [
        'codigo' => $codigo,
        'nombre' => $nombre,
        'objetivo' => 'Prueba de induccion / reinduccion independiente de matriz',
        'duracion_estimada_horas' => 2,
        'criticidad' => 'MEDIA',
        'estado' => 'ACTIVA',
        'tipo_capacitacion_id' => $tipoId,
        'periodicidad_default_id' => $periodoId,
        'evaluacion' => 0,
        'certificado' => 0,
    ]);
};

try {
    echo "\n== Semilla de capacitaciones ==\n";
    $capIndActiva = $insertCap(
        $db,
        'IND-A' . substr($stamp, -8),
        'Induccion Corporativa HSEQ ' . $stamp,
        (int)$tipoInd['tipo_capacitacion_id'],
        (int)$unica['periodicidad_id']
    );
    $capIds[] = $capIndActiva;
    $capIndInact = $insertCap(
        $db,
        'IND-I' . substr($stamp, -8),
        'Induccion Inactiva ' . $stamp,
        (int)$tipoInd['tipo_capacitacion_id'],
        (int)$unica['periodicidad_id']
    );
    $capIds[] = $capIndInact;
    $db->query("UPDATE capacitaciones SET estado = 'INACTIVA' WHERE capacitacion_id = ?", [$capIndInact]);
    $capRei = $insertCap(
        $db,
        'REI-A' . substr($stamp, -8),
        'Reinduccion anual SG-SST ' . $stamp,
        (int)$tipoRei['tipo_capacitacion_id'],
        (int)$anual['periodicidad_id']
    );
    $capIds[] = $capRei;

    $capsMatriz = [];
    for ($i = 1; $i <= 3; $i++) {
        $id = (int)$db->insert('capacitaciones', [
            'codigo' => 'MX' . $i . substr($stamp, -8),
            'nombre' => "Cap matriz {$i} {$stamp}",
            'objetivo' => 'Capacitacion de matriz para prueba de induccion',
            'duracion_estimada_horas' => 1,
            'criticidad' => 'BAJA',
            'estado' => 'ACTIVA',
            'periodicidad_default_id' => (int)$anual['periodicidad_id'],
        ]);
        $capIds[] = $id;
        $capsMatriz[] = $id;
        $fila = $matriz->crear([
            'capacitacion_id' => $id,
            'cargo_id_ext' => $cargoId,
            'proyecto' => $proyecto,
            'periodicidad_id' => (int)$anual['periodicidad_id'],
            'obligatoria' => 1,
        ], 0);
        $idsMatriz[] = (int)$fila['matriz_aplicabilidad_id'];
    }

    $filaIndMatriz = $matriz->crear([
        'capacitacion_id' => $capIndActiva,
        'cargo_id_ext' => $cargoId,
        'proyecto' => $proyecto,
        'periodicidad_id' => (int)$unica['periodicidad_id'],
        'obligatoria' => 1,
    ], 0);
    $idsMatriz[] = (int)$filaIndMatriz['matriz_aplicabilidad_id'];
    ok($capIndActiva > 0 && $capRei > 0, 'Capacitaciones de prueba creadas');

    $personal = new PersonalService();
    $motor = new MotorAsignacionService();

    echo "\n== Sin fecha de ingreso ==\n";
    esperaRechazo(function () use ($personal, $docs, $cargoId, $proyecto): void {
        $personal->crear([
            'numero_documento' => $docs[0],
            'nombre_completo' => 'Sin Fecha Induccion',
            'correo' => 'sinfecind@hseq.test',
            'cargo_id' => $cargoId,
            'proyecto' => $proyecto,
        ]);
    }, 'Sin fecha de ingreso se rechaza el alta');
    $fantasma = $personalDb->fetch(
        "SELECT persona_id FROM {$personasT} WHERE numero_documento = ?",
        [$docs[0]]
    );
    ok($fantasma === null, 'No quedó trabajador ni asignación sin fecha de ingreso');

    echo "\n== Cargo sin matriz (proyecto distinto) ==\n";
    $creadoSinMatriz = $personal->crear([
        'numero_documento' => $docs[1],
        'nombre_completo' => 'Juan Perez Induccion',
        'correo' => 'juan.ind@hseq.test',
        'cargo_id' => $cargoId,
        'proyecto' => 'SIN-MATRIZ-' . $stamp,
        'fecha_ingreso' => '2026-09-01',
    ]);
    $pSin = (int)$creadoSinMatriz['persona_id'];
    ok($creadoSinMatriz['sincronizacion']['error'] === null, 'Motor sin error en alta sin matriz');
    ok(contarAsig($db, $pSin, $capIndActiva, 'INDUCCION') === 1, 'Inducción ACTIVA asignada sin depender de la matriz');
    ok(contarAsig($db, $pSin, $capIndInact) === 0, 'Inducción INACTIVA no se asigna');
    ok(contarAsig($db, $pSin, $capRei, 'REINDUCCION') === 1, 'Reinducción ACTIVA asignada al alta');
    ok(contarAsig($db, $pSin, $capsMatriz[0]) === 0, 'Cap de matriz de otro proyecto no se asignó');
    $especiales = $creadoSinMatriz['sincronizacion']['creadas_especiales'] ?? [];
    $nombresEsp = implode(' | ', is_array($especiales) ? $especiales : []);
    ok(
        is_array($especiales) && $nombresEsp !== '',
        'Respuesta incluye creadas_especiales: ' . $nombresEsp
    );

    $histInd = $asignaciones->listar(1, 50, $pSin, $capIndActiva, null, null, null, 'INDUCCION');
    ok($histInd['total'] === 1, 'Filtro origen INDUCCION en historial');
    ok(($histInd['items'][0]['origen'] ?? '') === 'INDUCCION', 'Historial muestra origen INDUCCION');

    echo "\n== Inducción + matriz (sin duplicar la inducción) ==\n";
    $creadoMatriz = $personal->crear([
        'numero_documento' => $docs[2],
        'nombre_completo' => 'Ana Matriz Induccion',
        'correo' => 'ana.ind@hseq.test',
        'cargo_id' => $cargoId,
        'proyecto' => $proyecto,
        'fecha_ingreso' => '2026-09-01',
    ]);
    $pMat = (int)$creadoMatriz['persona_id'];
    ok(contarAsig($db, $pMat, $capIndActiva) === 1, 'Una sola fila de la inducción aunque también está en matriz');
    ok(contarAsig($db, $pMat, $capIndActiva, 'INDUCCION') === 1, 'La fila única es origen INDUCCION');
    ok(contarAsig($db, $pMat, $capsMatriz[0], 'AUTOMATICA') === 1, 'Matriz cap 1');
    ok(contarAsig($db, $pMat, $capsMatriz[1], 'AUTOMATICA') === 1, 'Matriz cap 2');
    ok(contarAsig($db, $pMat, $capsMatriz[2], 'AUTOMATICA') === 1, 'Matriz cap 3');
    ok(contarAsig($db, $pMat, $capRei, 'REINDUCCION') === 1, 'Reinducción además de la matriz');

    echo "\n== Duplicados / re-sync ==\n";
    $resync = $motor->sincronizarPersona($personal->ver($pSin), null);
    ok((int)$resync['creadas'] === 0, 'Re-sync no crea duplicados got=' . $resync['creadas']);
    ok(contarAsig($db, $pSin, $capIndActiva) === 1, 'Sigue una sola inducción');
    ok(contarAsig($db, $pSin, $capRei) === 1, 'Sigue una sola reinducción pendiente');

    echo "\n== Cambio de cargo ==\n";
    $editado = $personal->editar($pSin, [
        'cargo_id' => $cargoAltId,
        'proyecto' => 'SIN-MATRIZ-' . $stamp,
        'correo' => 'juan.ind@hseq.test',
    ]);
    ok(contarAsig($db, $pSin, $capIndActiva) === 1, 'Cambio de cargo no crea segunda inducción');
    ok(contarAsig($db, (int)$editado['persona_id'], $capRei) === 1, 'Cambio de cargo no duplica reinducción pendiente');

    echo "\n== Reinducción: vencimiento RF-014 y renovación ==\n";
    $asigRei = $db->fetch(
        'SELECT asignacion_id FROM asignaciones_capacitacion
         WHERE persona_id_ext = ? AND capacitacion_id = ? AND origen = ? LIMIT 1',
        [$pMat, $capRei, 'REINDUCCION']
    );
    ok($asigRei !== null, 'Hay asignación de reinducción para completar');
    $asigReiId = (int)$asigRei['asignacion_id'];
    $realizacion = '2026-08-01';
    $vence = VencimientoService::calcularFechaVencimiento(
        $realizacion,
        (int)$anual['cantidad'],
        (string)$anual['unidad']
    );
    ok(is_string($vence) && $vence !== '', 'Periodicidad ANUAL produce fecha de vencimiento');
    $db->insert('cumplimientos_capacitacion', [
        'asignacion_id' => $asigReiId,
        'fecha_realizacion' => $realizacion,
        'resultado' => 'APROBADO',
        'horas_efectivas' => 3,
        'fecha_vencimiento' => $vence,
    ]);
    $motor->sincronizarPersona($personal->ver($pMat), null);
    ok(pendientesCap($db, $pMat, $capRei) === 0, 'Con vigencia vigente no se crea otra reinducción');
    ok(contarAsig($db, $pMat, $capRei) === 1, 'Sigue una sola reinducción completada');

    $db->query(
        'UPDATE cumplimientos_capacitacion SET fecha_vencimiento = ? WHERE asignacion_id = ?',
        ['2020-01-01', $asigReiId]
    );
    $renov = $motor->sincronizarPersona($personal->ver($pMat), null);
    ok((int)$renov['creadas'] >= 1, 'Al vencer se crea nueva obligación got=' . $renov['creadas']);
    ok(pendientesCap($db, $pMat, $capRei) === 1, 'Nueva reinducción pendiente');
    ok(contarAsig($db, $pMat, $capRei) === 2, 'Histórica + nueva, sin borrar la completada');
    $nueva = $db->fetch(
        'SELECT origen FROM asignaciones_capacitacion a
         LEFT JOIN cumplimientos_capacitacion c ON c.asignacion_id = a.asignacion_id
         WHERE a.persona_id_ext = ? AND a.capacitacion_id = ? AND c.cumplimiento_id IS NULL',
        [$pMat, $capRei]
    );
    ok(($nueva['origen'] ?? '') === 'REINDUCCION', 'La renovación conserva origen REINDUCCION');

    echo "\n== Carga masiva (misma regla que importación) ==\n";
    $importador = new PersonalService();
    $importados = 0;
    for ($i = 3; $i <= 7; $i++) {
        $prep = $importador->prepararEntrada([
            'numero_documento' => $docs[$i],
            'nombre_completo' => "Importado Induccion {$i}",
            'correo' => "imp{$i}.ind@hseq.test",
            'cargo_id' => $cargoAltId,
            'proyecto' => 'IMP-' . $stamp,
            'fecha_ingreso' => '2026-09-01',
        ], null);
        ok($prep['ok'] === true, "Fila importable {$i}");
        $pid = $importador->persistirAlta($prep['datos']);
        $sync = $importador->sincronizarAsignaciones($importador->ver($pid));
        ok($sync['error'] === null, "Sync importado {$i}");
        ok(contarAsig($db, $pid, $capIndActiva, 'INDUCCION') === 1, "Inducción en importado {$i}");
        ok(contarAsig($db, $pid, $capRei, 'REINDUCCION') === 1, "Reinducción en importado {$i}");
        $importados++;
    }
    ok($importados === 5, '5 trabajadores válidos con inducción y reinducción');

    echo "\n== Limpieza final ==\n";
} finally {
    limpiarPersonas($db, $personalDb, $personasT, $contratosT, $docs);
    foreach ($idsMatriz as $idMatriz) {
        $db->query('UPDATE matriz_aplicabilidad SET activa = 0 WHERE matriz_aplicabilidad_id = ?', [$idMatriz]);
        $db->query('DELETE FROM matriz_aplicabilidad WHERE matriz_aplicabilidad_id = ?', [$idMatriz]);
    }
    foreach ($capIds as $cid) {
        $ref = $db->fetch(
            'SELECT matriz_aplicabilidad_id FROM matriz_aplicabilidad WHERE capacitacion_id = ? LIMIT 1',
            [$cid]
        );
        $asig = $db->fetch(
            'SELECT asignacion_id FROM asignaciones_capacitacion WHERE capacitacion_id = ? LIMIT 1',
            [$cid]
        );
        if ($ref === null && $asig === null) {
            $db->query('DELETE FROM capacitaciones WHERE capacitacion_id = ?', [$cid]);
        } else {
            $db->query("UPDATE capacitaciones SET estado = 'INACTIVA' WHERE capacitacion_id = ?", [$cid]);
        }
    }
    echo "OK: Limpieza final\n";
}

echo "\nTodas las pruebas de inducción / reinducción OK.\n";
