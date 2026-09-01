<?php

declare(strict_types=1);

/**
 * Pruebas de evidencias / soportes de cumplimiento.
 * Uso: php database/probar_soportes.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\AsignacionService;
use App\Services\CumplimientoService;
use App\Services\MatrizService;
use App\Services\PersonalService;
use App\Services\PlanAnualService;
use App\Services\SesionService;
use App\Services\SoporteService;
use App\Services\ReporteService;

Env::load(BASE_PATH);

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

function archivoTmp(string $nombre, string $contenido): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'sop');
    file_put_contents($tmp, $contenido);

    return [
        'name' => $nombre,
        'type' => 'application/octet-stream',
        'tmp_name' => $tmp,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($contenido),
    ];
}

$db = Database::getInstance();
$personalDb = Database::personal();
$personal = new PersonalService();
$asignaciones = new AsignacionService();
$matriz = new MatrizService();
$planes = new PlanAnualService();
$sesiones = new SesionService();
$cumplimientos = new CumplimientoService();
$soportes = new SoporteService();
$reportes = new ReporteService();

$anioPrueba = 2032;
$personasT = Database::personalTable('personas');
$contratosT = Database::personalTable('contratos');
$docs = ['9000990101', '9000990102'];

echo "== Limpieza previa año {$anioPrueba} ==\n";
borrarPlanYSesiones($db, $soportes, $anioPrueba);
limpiarPersonas($db, $personalDb, $soportes, $personasT, $contratosT, $docs);

$presencial = $db->fetch("SELECT modalidad_id FROM modalidades WHERE nombre = 'PRESENCIAL' AND activo = 1 LIMIT 1");
$ubicacion = $db->fetch('SELECT ubicacion_id FROM ubicaciones WHERE activo = 1 ORDER BY ubicacion_id ASC LIMIT 1');
$proveedor = $db->fetch('SELECT proveedor_id FROM proveedores_capacitadores WHERE activo = 1 ORDER BY proveedor_id ASC LIMIT 1');
ok($presencial !== null && $ubicacion !== null && $proveedor !== null, 'Catálogos de sesión disponibles');

$cargos = $personal->cargos();
ok(count($cargos) >= 1, 'Hay cargos corporativos');
$cargoId = (int)$cargos[0]['cargo_id'];
$per12 = periodicidadId($db, 12, 'MESES', 'SOP-PRU-12M');

$stamp = date('YmdHis');
$codigoAlt = 'SOP-ALT-' . $stamp;
$capCert = (int)$db->insert('capacitaciones', [
    'codigo' => $codigoAlt,
    'nombre' => 'Trabajo Seguro en Alturas',
    'objetivo' => 'Prueba de certificado obligatorio',
    'duracion_estimada_horas' => 8,
    'criticidad' => 'ALTA',
    'estado' => 'ACTIVA',
    'certificado' => 1,
    'periodicidad_default_id' => $per12,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
ok($capCert > 0, 'Capacitación con certificado=1 creada');

$capLibre = (int)$db->insert('capacitaciones', [
    'codigo' => 'SOP-LIB-' . $stamp,
    'nombre' => 'Inducción general (sin certificado)',
    'objetivo' => 'Prueba sin evidencia',
    'duracion_estimada_horas' => 4,
    'criticidad' => 'MEDIA',
    'estado' => 'ACTIVA',
    'certificado' => 0,
    'periodicidad_default_id' => $per12,
    'modalidad_default_id' => (int)$presencial['modalidad_id'],
    'proveedor_default_id' => (int)$proveedor['proveedor_id'],
]);
ok($capLibre > 0, 'Capacitación con certificado=0 creada');

$proyecto = 'HSEQ-SOP-PRU';
$p1 = $personal->crear([
    'numero_documento' => $docs[0],
    'nombre_completo' => 'Juan Perez Soporte',
    'correo' => 'soporte1@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => $proyecto,
    'fecha_ingreso' => '2026-01-15',
]);
$p2 = $personal->crear([
    'numero_documento' => $docs[1],
    'nombre_completo' => 'Ana Lopez Soporte',
    'correo' => 'soporte2@hseq.test',
    'cargo_id' => $cargoId,
    'proyecto' => $proyecto,
    'fecha_ingreso' => '2026-01-15',
]);
$persona1 = (int)$p1['persona_id'];
$persona2 = (int)$p2['persona_id'];

$matriz->crear([
    'capacitacion_id' => $capCert,
    'cargo_id_ext' => $cargoId,
    'proyecto' => $proyecto,
    'periodicidad_id' => $per12,
    'obligatoria' => 1,
], 1);

$asig1 = $asignaciones->crear([
    'persona_id_ext' => $persona1,
    'capacitacion_id' => $capCert,
    'fecha_limite_cumplimiento' => '2032-12-31',
], 1);
$asigLibre = $asignaciones->crear([
    'persona_id_ext' => $persona2,
    'capacitacion_id' => $capLibre,
    'fecha_limite_cumplimiento' => '2032-12-31',
], 1);
$asignacionId = (int)$asig1['asignacion_id'];
$asignacionLibre = (int)$asigLibre['asignacion_id'];

$plan = $planes->crear(['anio' => $anioPrueba], 1);
$planId = (int)$plan['plan_anual_id'];
$incluir = $planes->incluirAsignaciones($planId, [
    'asignacion_ids' => [$asignacionId, $asignacionLibre],
    'mes_programado' => 9,
]);
$detalleId = 0;
$detalleLibre = 0;
foreach ($incluir['items'] as $det) {
    $cid = (int)$det['capacitacion_id'];
    if ($cid === $capCert) {
        $detalleId = (int)$det['plan_detalle_id'];
    }
    if ($cid === $capLibre) {
        $detalleLibre = (int)$det['plan_detalle_id'];
    }
}
ok($detalleId > 0 && $detalleLibre > 0, 'Detalles de plan para ambas capacitaciones');
$planes->enviarRevision($planId);
$planes->aprobar($planId, 1);

$sesion = $sesiones->crear([
    'plan_detalle_id' => $detalleId,
    'fecha' => '2032-09-15',
    'hora' => '08:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 4,
    'asignacion_ids' => [$asignacionId],
], 1);
$sesionId = (int)$sesion['sesion_id'];
ok($sesion['requiere_certificado'] === true, 'Sesión marca requiere_certificado');

$sesiones->guardarAsistencia($sesionId, [
    'items' => [['asignacion_id' => $asignacionId, 'estado_asistencia' => 'ASISTIO']],
], 1);

$borrador = $db->fetch(
    'SELECT cumplimiento_id, resultado, fecha_vencimiento FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$asignacionId]
);
ok($borrador !== null, 'Borrador de asistencia creado');
$cumplimientoId = (int)$borrador['cumplimiento_id'];
$venceBorrador = $borrador['fecha_vencimiento'];

echo "\n== 1. APROBADO sin archivo → 422 ==\n";
$msg = esperaRechazo(function () use ($cumplimientos, $asignacionId, $sesionId) {
    $cumplimientos->registrar([
        'asignacion_id' => $asignacionId,
        'sesion_id' => $sesionId,
        'fecha_realizacion' => '2032-09-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
    ], 1);
}, 'APROBADO sin archivo rechazado');
ok($msg === SoporteService::MENSAJE_REQUIERE_CERTIFICADO, 'Mensaje de certificado obligatorio');
$sigue = $db->fetch('SELECT resultado FROM cumplimientos_capacitacion WHERE cumplimiento_id = ?', [$cumplimientoId]);
ok(strtoupper((string)$sigue['resultado']) === 'ASISTIO', 'Resultado sigue ASISTIO');

echo "\n== 8. Masivo con certificado=1 → 422 ==\n";
esperaRechazo(function () use ($cumplimientos, $asignacionId, $sesionId) {
    $cumplimientos->registrarMasivo([
        'sesion_id' => $sesionId,
        'asignacion_ids' => [$asignacionId],
        'fecha_realizacion' => '2032-09-15',
        'resultado' => 'APROBADO',
        'horas_efectivas' => 8,
    ], 1);
}, 'Masivo con certificado bloqueado');

echo "\n== 6. Extensión y MIME inválidos ==\n";
$antesDisco = glob($soportes->directorioBase() . '/soportes/' . $cumplimientoId . '/*') ?: [];
esperaRechazo(function () use ($soportes, $cumplimientoId) {
    $soportes->cargar($cumplimientoId, archivoTmp('malware.exe', "MZ\x90\x00fake"), 'CERTIFICADO', 1);
}, 'EXE rechazado');
$falsoPdf = archivoTmp('falso.pdf', 'esto no es un pdf');
esperaRechazo(function () use ($soportes, $cumplimientoId, $falsoPdf) {
    $soportes->cargar($cumplimientoId, $falsoPdf, 'CERTIFICADO', 1);
}, 'PDF falso rechazado');
$despuesDisco = glob($soportes->directorioBase() . '/soportes/' . $cumplimientoId . '/*') ?: [];
ok(count($despuesDisco) === count($antesDisco), 'Nada quedó en disco tras rechazos');

echo "\n== 7. Tamaño excesivo ==\n";
$grande = archivoTmp('grande.pdf', "%PDF-1.4\n%test\n");
$grande['size'] = $soportes->tamanoMaximo() + 1;
esperaRechazo(function () use ($soportes, $cumplimientoId, $grande) {
    $soportes->cargar($cumplimientoId, $grande, 'CERTIFICADO', 1);
}, 'Archivo que supera UPLOAD_MAX_SIZE rechazado');

echo "\n== 9. Evidencia faltante lista el borrador ==\n";
$faltantes = $cumplimientos->listar(1, 20, ['evidencia_faltante' => 1]);
$idsFaltantes = array_map(static fn ($i) => (int)$i['cumplimiento_id'], $faltantes['items']);
ok(in_array($cumplimientoId, $idsFaltantes, true), 'Borrador aparece en evidencia_faltante');

$rep = $reportes->evidenciasFaltantes(1, 20, []);
$idsRep = array_map(static fn ($i) => (int)$i['cumplimiento_id'], $rep['items']);
ok(in_array($cumplimientoId, $idsRep, true), 'Reporte incluye el borrador sin archivo');
$filaRep = null;
foreach ($rep['items'] as $item) {
    if ((int)$item['cumplimiento_id'] === $cumplimientoId) {
        $filaRep = $item;
        break;
    }
}
ok($filaRep !== null && $filaRep['estado'] === 'Pendiente', 'Reporte marca estado Pendiente');
ok($filaRep !== null && $filaRep['requiere_certificado'] === true, 'Reporte marca requiere certificado');
ok($filaRep !== null && (int)$filaRep['soportes_count'] === 0, 'Reporte cantidad de evidencias 0');

echo "\n== 2. Cargar PDF y APROBADO ==\n";
$pdfBytes = "%PDF-1.4\n%Certificado Juan Perez\n";
$cargado = $soportes->cargar(
    $cumplimientoId,
    archivoTmp('Certificado_Juan_Perez.pdf', $pdfBytes),
    'CERTIFICADO',
    1
);
ok((int)$cargado['soporte_id'] > 0, 'PDF cargado');
ok($cargado['nombre_archivo'] === 'Certificado_Juan_Perez.pdf', 'Nombre original conservado');

$registrado = $cumplimientos->registrar([
    'asignacion_id' => $asignacionId,
    'sesion_id' => $sesionId,
    'fecha_realizacion' => '2032-09-15',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 8,
], 1);
ok($registrado['resultado'] === 'APROBADO', 'APROBADO con evidencia');
ok(($registrado['fecha_vencimiento'] ?? null) === $venceBorrador, 'Vencimiento de matriz intacto');

echo "\n== 3. Listar y descargar ==\n";
$lista = $soportes->listar($cumplimientoId);
ok(count($lista) === 1, 'Un soporte listado');
$desc = $soportes->descargar((int)$cargado['soporte_id']);
ok($desc['nombre'] === 'Certificado_Juan_Perez.pdf', 'Descarga usa nombre original');
ok($desc['contenido'] === $pdfBytes, 'Contenido descargado correcto');
ok($desc['mime'] === 'application/pdf', 'MIME PDF');

echo "\n== 5. Segundo archivo en el mismo cumplimiento ==\n";
$pdf2 = "%PDF-1.4\n%constancia extra\n";
$pngCargado = $soportes->cargar($cumplimientoId, archivoTmp('constancia.pdf', $pdf2), 'OTRO', 1);
ok((int)$pngCargado['soporte_id'] > 0, 'Segundo archivo cargado');
ok(count($soportes->listar($cumplimientoId)) === 2, 'Dos soportes en el cumplimiento');

$faltantesDespues = $cumplimientos->listar(1, 20, ['evidencia_faltante' => 1]);
$idsDespues = array_map(static fn ($i) => (int)$i['cumplimiento_id'], $faltantesDespues['items']);
ok(!in_array($cumplimientoId, $idsDespues, true), 'Tras cargar, sale de evidencia_faltante');
$repDespues = $reportes->evidenciasFaltantes(1, 20, []);
$idsRepDespues = array_map(static fn ($i) => (int)$i['cumplimiento_id'], $repDespues['items']);
ok(!in_array($cumplimientoId, $idsRepDespues, true), 'Tras cargar, sale del reporte');

echo "\n== 4. Cap certificado=0 permite APROBADO sin archivo ==\n";
$sesionLibre = $sesiones->crear([
    'plan_detalle_id' => $detalleLibre,
    'fecha' => '2032-10-01',
    'hora' => '09:00',
    'modalidad_id' => (int)$presencial['modalidad_id'],
    'ubicacion_id' => (int)$ubicacion['ubicacion_id'],
    'proveedor_id' => (int)$proveedor['proveedor_id'],
    'cupo_maximo' => 4,
    'asignacion_ids' => [$asignacionLibre],
], 1);
$sesiones->guardarAsistencia((int)$sesionLibre['sesion_id'], [
    'items' => [['asignacion_id' => $asignacionLibre, 'estado_asistencia' => 'ASISTIO']],
], 1);
$sinArchivo = $cumplimientos->registrar([
    'asignacion_id' => $asignacionLibre,
    'sesion_id' => (int)$sesionLibre['sesion_id'],
    'fecha_realizacion' => '2032-10-01',
    'resultado' => 'APROBADO',
    'horas_efectivas' => 4,
], 1);
ok($sinArchivo['resultado'] === 'APROBADO', 'APROBADO sin archivo cuando certificado=0');
ok((int)($sinArchivo['soportes_count'] ?? 0) === 0, 'soportes_count 0');

echo "\n== 10. Soporte en BD sin archivo físico ==\n";
$huerfanoId = (int)$db->insert('soportes_cumplimiento', [
    'cumplimiento_id' => $cumplimientoId,
    'tipo_soporte' => 'OTRO',
    'nombre_archivo' => 'perdido.pdf',
    'ruta_archivo' => 'soportes/' . $cumplimientoId . '/no-existe.pdf',
    'mime_type' => 'application/pdf',
    'tamano_bytes' => 10,
    'cargado_por_usuario_id_ext' => 1,
]);
$msg404 = esperaRechazo(function () use ($soportes, $huerfanoId) {
    $soportes->descargar($huerfanoId);
}, 'Descarga de archivo inexistente', 404);
ok($msg404 === SoporteService::MENSAJE_NO_ENCONTRADO, 'Mensaje funcional sin ruta interna');

echo "\n== 11. Renovación no hereda soportes ==\n";
$asigNueva = $asignaciones->crear([
    'persona_id_ext' => $persona1,
    'capacitacion_id' => $capCert,
    'fecha_limite_cumplimiento' => '2033-12-31',
], 1);
$nuevaId = (int)$asigNueva['asignacion_id'];
ok($nuevaId !== $asignacionId, 'Nueva asignación de renovación');
$cumpNueva = $db->fetch(
    'SELECT cumplimiento_id FROM cumplimientos_capacitacion WHERE asignacion_id = ?',
    [$nuevaId]
);
ok($cumpNueva === null, 'La nueva asignación no trae cumplimiento ni soportes');

echo "\n== Limpieza ==\n";
borrarPlanYSesiones($db, $soportes, $anioPrueba);
limpiarPersonas($db, $personalDb, $soportes, $personasT, $contratosT, $docs);
$db->query('DELETE FROM matriz_aplicabilidad WHERE capacitacion_id IN (?, ?)', [$capCert, $capLibre]);
$db->query('DELETE FROM capacitaciones WHERE capacitacion_id IN (?, ?)', [$capCert, $capLibre]);
ok(true, 'Limpieza de prueba de soportes');

echo "\nTodas las pruebas de soportes pasaron.\n";
