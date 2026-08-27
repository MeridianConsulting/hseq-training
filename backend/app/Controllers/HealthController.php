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
            'base_capacitaciones' => $this->estadoBase(env('DB_DATABASE')),
            'base_personal' => $this->estadoBase(config('database.personal_database')),
        ], 'API en linea');
    }

    /**
     * Confirma que la base responde y cuantas tablas tiene, sin exponer credenciales.
     */
    private function estadoBase(mixed $nombre): array
    {
        $nombre = (string)$nombre;

        try {
            $fila = Database::getInstance()->fetch(
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
