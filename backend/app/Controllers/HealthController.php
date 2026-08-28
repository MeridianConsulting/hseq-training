<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use Throwable;

class HealthController extends Controller
{
    public function ping(Request $request): void
    {
        $this->success([
            'ok' => true,
            'app' => env('APP_NAME', 'HSEQ Capacitaciones'),
            'base_capacitaciones' => $this->estadoBase(
                (string)env('DB_DATABASE', 'meridian_capacitaciones'),
                Database::getInstance()
            ),
            'base_personal' => $this->estadoBase(
                Database::personalName(),
                Database::personal()
            ),
        ], 'API en linea');
    }

    /**
     * Confirma que la base responde y cuantas tablas tiene, sin exponer credenciales.
     */
    private function estadoBase(string $nombre, Database $conexion): array
    {
        try {
            $fila = $conexion->fetch(
                'SELECT COUNT(*) AS tablas FROM information_schema.tables WHERE table_schema = ?',
                [$nombre]
            );

            $tablas = (int)($fila['tablas'] ?? 0);

            return [
                'nombre' => $nombre,
                'conectada' => $tablas > 0,
                'tablas' => $tablas,
            ];
        } catch (Throwable $e) {
            return [
                'nombre' => $nombre,
                'conectada' => false,
                'error' => env('APP_DEBUG', false) ? $e->getMessage() : 'Sin conexion',
            ];
        }
    }
}
