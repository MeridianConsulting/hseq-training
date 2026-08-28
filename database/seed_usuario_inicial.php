<?php

declare(strict_types=1);

/**
 * Crea el usuario inicial del modulo HSEQ en meridian_capacitaciones.usuarios.
 * Uso: php seed_usuario_inicial.php
 */

$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=meridian_capacitaciones;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$nombreUsuario = 'admin';
$correo = 'admin@hseq.local';
$password = 'Admin123!';

$existe = $pdo->prepare('SELECT usuario_id FROM usuarios WHERE nombre_usuario = ? OR correo = ? LIMIT 1');
$existe->execute([$nombreUsuario, $correo]);

if ($existe->fetch()) {
    echo "El usuario inicial ya existe.\n";
    exit(0);
}

$pdo->beginTransaction();

$pdo->prepare(
    'INSERT INTO usuarios (nombre_usuario, correo, password_hash, rol, estado)
     VALUES (?, ?, ?, ?, ?)'
)->execute([
    $nombreUsuario,
    $correo,
    password_hash($password, PASSWORD_DEFAULT),
    'admin',
    'Activo',
]);

$usuarioId = (int)$pdo->lastInsertId();

$pdo->exec("INSERT IGNORE INTO roles (nombre) VALUES ('Administrador HSEQ')");
$rol = $pdo->query("SELECT role_id FROM roles WHERE nombre = 'Administrador HSEQ' LIMIT 1")->fetch();

if ($rol) {
    $pdo->prepare('INSERT INTO user_roles (usuario_id, role_id) VALUES (?, ?)')
        ->execute([$usuarioId, (int)$rol['role_id']]);
}

$pdo->commit();

echo "Usuario inicial creado: {$nombreUsuario} / {$correo}\n";
