<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Repositories\UsuarioRepository;

class UsuarioService
{
    private UsuarioRepository $repo;

    public function __construct()
    {
        $this->repo = new UsuarioRepository();
    }

    /**
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function listar(int $pagina, int $porPagina, ?string $buscar, ?string $estado): array
    {
        $pagina = max(1, $pagina);
        $porPagina = min(100, max(1, $porPagina));
        $offset = ($pagina - 1) * $porPagina;
        $estadoNorm = $this->normalizarEstadoFiltro($estado);

        $filas = $this->repo->listar($porPagina, $offset, $buscar, $estadoNorm);
        $items = [];
        foreach ($filas as $fila) {
            $items[] = $this->mapear($fila);
        }

        return [
            'items' => $items,
            'total' => $this->repo->contar($buscar, $estadoNorm),
            'page' => $pagina,
            'per_page' => $porPagina,
        ];
    }

    /** @return array<string,mixed> */
    public function ver(int $usuarioId): array
    {
        $fila = $this->repo->buscarPorId($usuarioId);
        if ($fila === null) {
            throw new HttpException('Usuario no encontrado', 404);
        }

        return $this->mapear($fila);
    }

    /**
     * @param array<string,mixed> $entrada
     * @return array<string,mixed>
     */
    public function crear(array $entrada): array
    {
        $nombre = $this->exigirTexto($entrada['nombre_usuario'] ?? null, 'El nombre de usuario es obligatorio.', 50);
        $correo = $this->exigirCorreo($entrada['correo'] ?? null);
        $password = $this->exigirPassword($entrada['password'] ?? null, true);
        $estado = $this->exigirEstado($entrada['estado'] ?? 'Activo');

        if ($this->repo->existeNombreOCorreo($nombre, $correo)) {
            throw new HttpException('Ya existe un usuario con ese nombre o correo.', 422);
        }

        $roleId = $this->repo->asegurarRolAdministradorHseq();
        $id = $this->repo->crear([
            'nombre_usuario' => $nombre,
            'correo' => $correo,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'rol' => 'admin',
            'estado' => $estado,
        ]);
        $this->repo->sincronizarRolPrincipal($id, $roleId);

        return $this->ver($id);
    }

    /**
     * @param array<string,mixed> $entrada
     * @return array<string,mixed>
     */
    public function actualizar(int $usuarioId, array $entrada, int $actorId): array
    {
        $actual = $this->repo->buscarPorId($usuarioId);
        if ($actual === null) {
            throw new HttpException('Usuario no encontrado', 404);
        }

        $datos = [];
        if (array_key_exists('nombre_usuario', $entrada)) {
            $datos['nombre_usuario'] = $this->exigirTexto(
                $entrada['nombre_usuario'],
                'El nombre de usuario es obligatorio.',
                50
            );
        }
        if (array_key_exists('correo', $entrada)) {
            $datos['correo'] = $this->exigirCorreo($entrada['correo']);
        }
        if (array_key_exists('estado', $entrada)) {
            $nuevoEstado = $this->exigirEstado($entrada['estado']);
            if ($nuevoEstado === 'Inactivo') {
                $this->exigirPuedeInactivar($usuarioId, $actorId, $actual);
            }
            $datos['estado'] = $nuevoEstado;
        }
        if (array_key_exists('password', $entrada)
            && $entrada['password'] !== null
            && trim((string)$entrada['password']) !== ''
        ) {
            $datos['password_hash'] = password_hash(
                $this->exigirPassword($entrada['password'], false),
                PASSWORD_DEFAULT
            );
            $datos['intentos_fallidos'] = 0;
            $datos['bloqueado_hasta'] = null;
        }

        $nombreCheck = $datos['nombre_usuario'] ?? (string)$actual['nombre_usuario'];
        $correoCheck = $datos['correo'] ?? (string)$actual['correo'];
        if ($this->repo->existeNombreOCorreo($nombreCheck, $correoCheck, $usuarioId)) {
            throw new HttpException('Ya existe un usuario con ese nombre o correo.', 422);
        }

        $this->repo->actualizar($usuarioId, $datos);
        $roleId = $this->repo->asegurarRolAdministradorHseq();
        $this->repo->sincronizarRolPrincipal($usuarioId, $roleId);
        if (($actual['rol'] ?? '') !== 'admin') {
            $this->repo->actualizar($usuarioId, ['rol' => 'admin']);
        }

        return $this->ver($usuarioId);
    }

    /** @return array<string,mixed> */
    public function inactivar(int $usuarioId, int $actorId): array
    {
        return $this->actualizar($usuarioId, ['estado' => 'Inactivo'], $actorId);
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function mapear(array $fila): array
    {
        $id = (int)$fila['usuario_id'];

        return [
            'usuario_id' => $id,
            'nombre_usuario' => (string)$fila['nombre_usuario'],
            'correo' => (string)$fila['correo'],
            'rol' => (string)($fila['rol'] ?? 'usuario'),
            'estado' => (string)$fila['estado'],
            'roles' => $this->repo->rolesDeUsuario($id),
            'ultimo_acceso' => $fila['ultimo_acceso'] ?? null,
            'created_at' => $fila['created_at'] ?? null,
            'updated_at' => $fila['updated_at'] ?? null,
        ];
    }

    private function exigirPuedeInactivar(int $usuarioId, int $actorId, array $actual): void
    {
        if ($usuarioId === $actorId) {
            throw new HttpException('No puede inactivar su propio usuario.', 422);
        }
        if ($this->esAdminFila($actual) && $this->repo->contarAdministradoresActivos($usuarioId) < 1) {
            throw new HttpException('Debe quedar al menos un administrador activo.', 422);
        }
    }

    /** @param array<string,mixed> $fila */
    private function esAdminFila(array $fila): bool
    {
        $rol = strtolower(trim((string)($fila['rol'] ?? '')));
        if (in_array($rol, ['admin', 'administrador', 'administrador hseq'], true)) {
            return true;
        }
        foreach ($this->repo->rolesDeUsuario((int)$fila['usuario_id']) as $r) {
            $nombre = strtolower(trim((string)$r['nombre']));
            if ($nombre === 'administrador hseq' || $nombre === 'admin') {
                return true;
            }
        }

        return false;
    }

    private function normalizarEstadoFiltro(?string $estado): ?string
    {
        if ($estado === null || $estado === '' || $estado === 'todos') {
            return null;
        }
        if ($estado === 'activos') {
            return 'Activo';
        }
        if ($estado === 'inactivos') {
            return 'Inactivo';
        }
        if (in_array($estado, ['Activo', 'Inactivo'], true)) {
            return $estado;
        }

        return null;
    }

    private function exigirTexto(mixed $valor, string $mensaje, int $max): string
    {
        $txt = is_string($valor) ? trim($valor) : '';
        if ($txt === '') {
            throw new HttpException($mensaje, 422);
        }
        if (mb_strlen($txt) > $max) {
            throw new HttpException("Máximo {$max} caracteres.", 422);
        }

        return $txt;
    }

    private function exigirCorreo(mixed $valor): string
    {
        $correo = $this->exigirTexto($valor, 'El correo es obligatorio.', 100);
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException('El correo no es válido.', 422);
        }

        return mb_strtolower($correo);
    }

    private function exigirPassword(mixed $valor, bool $obligatorio): string
    {
        $pwd = is_string($valor) ? $valor : '';
        if ($pwd === '') {
            if ($obligatorio) {
                throw new HttpException('La contraseña es obligatoria.', 422);
            }

            return '';
        }
        if (mb_strlen($pwd) < 8) {
            throw new HttpException('La contraseña debe tener al menos 8 caracteres.', 422);
        }

        return $pwd;
    }

    private function exigirEstado(mixed $valor): string
    {
        $estado = is_string($valor) ? trim($valor) : '';
        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            throw new HttpException('El estado no es válido.', 422);
        }

        return $estado;
    }
}
