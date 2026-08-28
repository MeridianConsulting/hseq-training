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

        if (!in_array($this->permiso, $permisos, true)) {
            Response::forbidden('No tiene permiso para realizar esta acción.');
        }
    }
}
