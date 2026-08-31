<?php

declare(strict_types=1);

/**
 * Prueba de aceptacion de alta y carga masiva de personal.
 * Uso: php database/probar_carga_personal.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');

require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Services\PersonalImportacionService;
use App\Services\PersonalService;

Env::load(BASE_PATH);

function assertTrue(bool $ok, string $mensaje): void
{
    if (!$ok) {
        fwrite(STDERR, "FALLO: {$mensaje}\n");
        exit(1);
    }
    echo "OK: {$mensaje}\n";
}

$db = Database::personal();
$personas = Database::personalTable('personas');

echo "== Diagnostico duplicados de numero_documento ==\n";
$dups = $db->fetchAll(
    "SELECT numero_documento, COUNT(*) AS cantidad
     FROM {$personas}
     GROUP BY numero_documento
     HAVING COUNT(*) > 1
     ORDER BY cantidad DESC"
);
echo $dups === []
    ? "Sin duplicados de numero_documento.\n"
    : ('Encontrados ' . count($dups) . " grupos duplicados (no se eliminan).\n");

$prefijo = '9000%';
$limpiar = static function () use ($db, $personas, $prefijo): void {
    $contratos = Database::personalTable('contratos');
    $ids = $db->fetchAll("SELECT persona_id FROM {$personas} WHERE numero_documento LIKE ?", [$prefijo]);
    $lista = array_map(static fn (array $f): int => (int)$f['persona_id'], $ids);
    if ($lista === []) {
        return;
    }
    $ph = implode(',', array_fill(0, count($lista), '?'));
    $db->query("DELETE FROM {$contratos} WHERE persona_id IN ({$ph})", $lista);
    $db->query("DELETE FROM {$personas} WHERE persona_id IN ({$ph})", $lista);
};

$limpiar();

$servicio = new PersonalService();
$importacion = new PersonalImportacionService();

echo "\n== Alta individual ==\n";
$creado = $servicio->crear([
    'numero_documento' => '9000999999',
    'nombre_completo' => 'Ana Prueba Individual',
    'correo' => 'ana.individual@hseq.test',
    'cargo_id' => 1,
    'proyecto' => 'HSEQ TEST',
    'fecha_ingreso' => '2026-08-01',
]);
assertTrue(($creado['persona_id'] ?? 0) > 0, 'Trabajador individual creado');
assertTrue($creado['numero_documento'] === '9000999999', 'Documento individual persistido');

echo "\n== Edicion solo correo, cargo y proyecto ==\n";
$nombreAntes = $creado['nombre_completo'];
$fechaAntes = $creado['contrato_fecha_inicio'];
$editado = $servicio->editar((int)$creado['persona_id'], [
    'correo' => 'ana.editada@hseq.test',
    'cargo_id' => $creado['cargo_id'],
    'proyecto' => 'HSEQ EDIT',
    'numero_documento' => '1111111111',
    'nombre_completo' => 'Nombre Falso Editado',
    'fecha_ingreso' => '2010-01-01',
]);
assertTrue($editado['correo_corporativo'] === 'ana.editada@hseq.test', 'Correo actualizado');
assertTrue($editado['proyecto'] === 'HSEQ EDIT', 'Proyecto actualizado');
assertTrue($editado['numero_documento'] === '9000999999', 'Documento no cambia en edicion');
assertTrue($editado['nombre_completo'] === $nombreAntes, 'Nombre no cambia en edicion');
assertTrue(
    substr((string)$editado['contrato_fecha_inicio'], 0, 10) === substr((string)$fechaAntes, 0, 10),
    'Fecha de ingreso no cambia en edicion'
);

echo "\n== Documento duplicado en formulario ==\n";
try {
    $servicio->crear([
        'numero_documento' => '9000999999',
        'nombre_completo' => 'Ana Otra',
        'cargo_id' => 1,
        'fecha_ingreso' => '2026-08-01',
    ]);
    assertTrue(false, 'Debio rechazar documento duplicado');
} catch (HttpException $e) {
    assertTrue($e->getMessage() === 'El documento ya se encuentra registrado.', $e->getMessage());
}

echo "\n== Carga 50 (45 validos / 5 invalidos) ==\n";
$csv = dirname(__DIR__) . '/docs/fixtures/carga_personal_50.csv';
$resultado = $importacion->importar([
    'name' => 'carga_personal_50.csv',
    'type' => 'text/csv',
    'tmp_name' => $csv,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($csv),
]);

assertTrue($resultado['total_procesados'] === 50, 'Procesados=' . $resultado['total_procesados']);
assertTrue($resultado['total_importados'] === 45, 'Importados=' . $resultado['total_importados']);
assertTrue($resultado['total_rechazados'] === 5, 'Rechazados=' . $resultado['total_rechazados']);

$motivos = array_map(static fn (array $r): string => $r['motivo'], $resultado['rechazados']);
echo 'Motivos: ' . implode(' | ', $motivos) . "\n";

$enBd = $db->fetch(
    "SELECT COUNT(*) AS total FROM {$personas} WHERE numero_documento LIKE '90000000%'",
    []
);
assertTrue((int)$enBd['total'] === 45, 'En BD hay 45 documentos 90000000xx');

$rechazadosDocs = ['9000999901', '9000999902', '9000999903'];
foreach ($rechazadosDocs as $doc) {
    $fila = $db->fetch("SELECT persona_id FROM {$personas} WHERE numero_documento = ? LIMIT 1", [$doc]);
    assertTrue($fila === null, "No se inserto el rechazado {$doc}");
}

echo "\n== Archivo sin columna Documento ==\n";
$sinDoc = dirname(__DIR__) . '/docs/fixtures/carga_personal_sin_documento.csv';
try {
    $importacion->importar([
        'name' => 'carga_personal_sin_documento.csv',
        'type' => 'text/csv',
        'tmp_name' => $sinDoc,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($sinDoc),
    ]);
    assertTrue(false, 'Debio rechazar archivo sin Documento');
} catch (HttpException $e) {
    assertTrue(
        str_contains($e->getMessage(), 'Documento'),
        $e->getMessage()
    );
}

echo "\n== Documento ya registrado en carga masiva ==\n";
$tmpDup = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'carga_dup_bd.csv';
file_put_contents(
    $tmpDup,
    "Documento;Nombre;Correo;Cargo;Proyecto;Fecha de ingreso\n" .
    "9000999999;Ana Otra Carga;otra@hseq.test;PRACTICANTE;HSEQ TEST;01/08/2026\n" .
    "9000777001;Nuevo Tras Dup;nuevo@hseq.test;PRACTICANTE;HSEQ TEST;01/08/2026\n"
);
$dupBd = $importacion->importar([
    'name' => 'dup_bd.csv',
    'type' => 'text/csv',
    'tmp_name' => $tmpDup,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpDup),
]);
assertTrue($dupBd['total_procesados'] === 2, 'Dup BD procesados=' . $dupBd['total_procesados']);
assertTrue($dupBd['total_importados'] === 1, 'Dup BD importados=' . $dupBd['total_importados']);
assertTrue($dupBd['total_rechazados'] === 1, 'Dup BD rechazados=' . $dupBd['total_rechazados']);
assertTrue(
    ($dupBd['rechazados'][0]['motivo'] ?? '') === 'El documento ya se encuentra registrado.',
    'Motivo dup BD: ' . ($dupBd['rechazados'][0]['motivo'] ?? '')
);
@unlink($tmpDup);

echo "\n== Continuidad: valida, valida, invalida, valida, valida ==\n";
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'carga_continuidad.csv';
file_put_contents(
    $tmp,
    "Documento;Nombre;Correo;Cargo;Proyecto;Fecha de ingreso\n" .
    "9000888001;Uno Continuidad;a@hseq.test;PRACTICANTE;HSEQ TEST;01/08/2026\n" .
    "9000888002;Dos Continuidad;b@hseq.test;PRACTICANTE;HSEQ TEST;01/08/2026\n" .
    "9000888003;;c@hseq.test;PRACTICANTE;HSEQ TEST;01/08/2026\n" .
    "9000888004;Cuatro Continuidad;d@hseq.test;PRACTICANTE;HSEQ TEST;01/08/2026\n" .
    "9000888005;Cinco Continuidad;e@hseq.test;PRACTICANTE;HSEQ TEST;01/08/2026\n"
);
$cont = $importacion->importar([
    'name' => 'continuidad.csv',
    'type' => 'text/csv',
    'tmp_name' => $tmp,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmp),
]);
assertTrue($cont['total_procesados'] === 5, 'Continuidad procesados=' . $cont['total_procesados']);
assertTrue($cont['total_importados'] === 4, 'Continuidad importados=' . $cont['total_importados']);
assertTrue($cont['total_rechazados'] === 1, 'Continuidad rechazados=' . $cont['total_rechazados']);
@unlink($tmp);

echo "\n== Plantilla Excel ==\n";
$xlsx = $importacion->generarPlantilla();
assertTrue(strlen($xlsx) > 1000, 'Plantilla xlsx generada (' . strlen($xlsx) . ' bytes)');

echo "\n== Asignaciones siguen resolviendo personas ==\n";
$asignaciones = Database::getInstance()->fetch(
    'SELECT a.asignacion_id, a.persona_id_ext, p.numero_documento
     FROM asignaciones_capacitacion a
     LEFT JOIN ' . Database::personalTable('personas') . ' p ON p.persona_id = a.persona_id_ext
     LIMIT 1'
);
if ($asignaciones === null) {
    echo "Sin asignaciones de prueba; se omite el cruce.\n";
} else {
    assertTrue(
        $asignaciones['numero_documento'] !== null && $asignaciones['numero_documento'] !== '',
        'Asignacion ' . $asignaciones['asignacion_id'] . ' resuelve documento ' . $asignaciones['numero_documento']
    );
}

$limpiar();
echo "\nPruebas de aceptacion OK. Datos de prueba 9000* eliminados.\n";
