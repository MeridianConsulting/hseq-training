<?php

declare(strict_types=1);

/**
 * Crea los cargos de la hoja MATRIZ POR CARGO (Gestión de Proyectos / Frontera)
 * si aún no existen. No borra historial ni cargos con gente asignada.
 * Uso: php database/asegurar_cargos_frontera.php
 */

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backend');
require BASE_PATH . '/vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Repositories\PersonalRepository;

Env::load(BASE_PATH);
date_default_timezone_set('America/Bogota');

$nombres = [
    'ASISTENTE DE COMPANY MAN D1',
    'ASISTENTE DE COMPANY MAN D2',
    'ASISTENTE DE COMPANY MAN D3',
    'COMPANY MAN B1',
    'COMPANY MAN B2',
    'COMPANY MAN B3',
];

$aliasDeExcel = [
    'ASISTENTE DE COMPANY MAN D1' => ['ING COMPANY D1', 'ASISTENTE COMPANY D1'],
    'ASISTENTE DE COMPANY MAN D2' => ['ASISTENTE COMPANY D2'],
    'ASISTENTE DE COMPANY MAN D3' => ['ASISTENTE COMPANY D3', 'SOPORTE OPERATIVO D3'],
    'COMPANY MAN B1' => ['COMPANYMAN B1'],
    'COMPANY MAN B2' => ['COMPANYMAN B2'],
    'COMPANY MAN B3' => ['COMPANYMAN B3'],
];

$repo = new PersonalRepository();
$mapa = $repo->mapaCargos();

echo "== Cargos Excel Gestión de Proyectos (Frontera) ==\n";
foreach ($nombres as $nombre) {
    $candidatos = array_merge([$nombre], $aliasDeExcel[$nombre] ?? []);
    $encontrado = null;
    foreach ($candidatos as $candidato) {
        $clave = $repo->claveCargo($candidato);
        if ($clave !== '' && isset($mapa['por_nombre'][$clave])) {
            $encontrado = (int)$mapa['por_nombre'][$clave];
            break;
        }
    }
    if ($encontrado !== null) {
        echo "YA EXISTE: {$nombre} (id={$encontrado})\n";
        continue;
    }
    $id = $repo->insertarCargo($nombre);
    $mapa['por_nombre'][$repo->claveCargo($nombre)] = $id;
    $mapa['por_id'][$id] = $nombre;
    echo "CREADO: {$nombre} (id={$id})\n";
}

$personalDb = Database::personal();
$tablaCargos = Database::personalTable('cargos');
$tablaPersonas = Database::personalTable('personas');
$obsoleto = $personalDb->fetch(
    "SELECT cargo_id, nombre_cargo FROM {$tablaCargos} WHERE nombre_cargo = ? LIMIT 1",
    ['COMPANY MAN D1']
);
if ($obsoleto !== null) {
    $idObsoleto = (int)$obsoleto['cargo_id'];
    $enUso = $personalDb->fetch(
        "SELECT persona_id FROM {$tablaPersonas} WHERE cargo_id = ? LIMIT 1",
        [$idObsoleto]
    );
    if ($enUso === null) {
        $personalDb->delete($tablaCargos, 'cargo_id = ?', [$idObsoleto]);
        echo "ELIMINADO (sin gente): COMPANY MAN D1 (id={$idObsoleto})\n";
    } else {
        echo "NO SE ELIMINA COMPANY MAN D1: hay personal con ese cargo.\n";
    }
}

echo "Listo.\n";
