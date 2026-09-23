<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Autorizacion por permiso HSEQ. AuthMiddleware debe ejecutarse antes.
 * Un unico rol administrador recibe todos los codigos en el JWT.
 */
class PermisoMiddleware
{
    public function __construct(private string $permiso)
    {
    }

    public function handle(Request $request): void
    {
        $usuario = $request->user() ?? [];
        $permisos = $usuario['permisos'] ?? [];

        if (!is_array($permisos)) {
            $permisos = [];
        }

        $rol = strtolower(trim((string)($usuario['rol'] ?? '')));
        if (in_array($rol, ['admin', 'administrador', 'administrador hseq'], true)) {
            return;
        }

        $roles = $usuario['roles'] ?? [];
        if (is_array($roles)) {
            foreach ($roles as $r) {
                $nombre = strtolower(trim((string)(is_array($r) ? ($r['nombre'] ?? '') : $r)));
                if ($nombre === 'administrador hseq' || $nombre === 'admin') {
                    return;
                }
            }
        }

        if (!in_array($this->permiso, $permisos, true)) {
            Response::forbidden('No tiene permiso para realizar esta acción.');
        }
    }
}
