<?php
// Normalizamos SCRIPT_NAME para que el router del backend
// no stripee "/api" del REQUEST_URI. Las rutas están definidas
// con el prefijo /api, así que debe llegar el path completo.
$_SERVER['SCRIPT_NAME'] = '/index.php';

require_once dirname(__DIR__, 3) . '/backend/public/index.php';
