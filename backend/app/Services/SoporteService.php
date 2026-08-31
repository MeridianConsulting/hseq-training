<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Exceptions\HttpException;
use App\Repositories\CumplimientoRepository;
use App\Repositories\SoporteRepository;

class SoporteService
{
    public const MENSAJE_REQUIERE_CERTIFICADO =
        'Este cumplimiento requiere certificado. Adjunte al menos un archivo antes de marcarlo como completado.';
    public const MENSAJE_MASIVO_CERTIFICADO =
        'Esta capacitación requiere certificado. Complete cada trabajador de forma individual y adjunte su archivo.';
    public const MENSAJE_NO_ENCONTRADO = 'No fue posible encontrar el archivo solicitado.';
    public const MENSAJE_FORMATO = 'El formato del archivo no está permitido.';
    public const MENSAJE_TAMANO = 'El archivo supera el tamaño máximo permitido.';
    public const MENSAJE_CARGA = 'No fue posible cargar el archivo.';

    private const TIPOS = ['CERTIFICADO', 'LISTADO_ASISTENCIA', 'OTRO'];
    private const MIME = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    private SoporteRepository $repo;
    private CumplimientoRepository $cumplimientos;

    public function __construct()
    {
        $this->repo = new SoporteRepository();
        $this->cumplimientos = new CumplimientoRepository();
    }

    public function tamanoMaximo(): int
    {
        $valor = Env::get('UPLOAD_MAX_SIZE', 10485760);

        return is_numeric($valor) ? max(1, (int)$valor) : 10485760;
    }

    public function directorioBase(): string
    {
        return rtrim(str_replace('\\', '/', BASE_PATH), '/') . '/storage/uploads';
    }

    public function requiereCertificadoPorCapacitacion(int $capacitacionId): bool
    {
        return $this->repo->capacitacionRequiereCertificado($capacitacionId);
    }

    public function contar(int $cumplimientoId): int
    {
        return $this->repo->contarPorCumplimiento($cumplimientoId);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listar(int $cumplimientoId): array
    {
        $this->exigirCumplimiento($cumplimientoId);

        return array_map([$this, 'normalizar'], $this->repo->listarPorCumplimiento($cumplimientoId));
    }

    /**
     * @param list<int> $cumplimientoIds
     * @return array<int,list<array<string,mixed>>>
     */
    public function porCumplimientos(array $cumplimientoIds): array
    {
        $agrupados = [];
        foreach ($this->repo->listarPorCumplimientos($cumplimientoIds) as $fila) {
            $cid = (int)$fila['cumplimiento_id'];
            $agrupados[$cid][] = $this->normalizar($fila);
        }

        return $agrupados;
    }

    /**
     * @param array<string,mixed> $archivo $_FILES item
     * @return array<string,mixed>
     */
    public function cargar(int $cumplimientoId, array $archivo, ?string $tipoSoporte, ?int $usuarioId): array
    {
        $cump = $this->exigirCumplimiento($cumplimientoId);
        $tipo = $this->normalizarTipo($tipoSoporte);
        $validado = $this->validarArchivo($archivo);

        $relativo = 'soportes/' . $cumplimientoId;
        $dir = $this->directorioBase() . '/' . $relativo;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new HttpException(self::MENSAJE_CARGA, 500);
        }

        $nombreDisco = bin2hex(random_bytes(8)) . '.' . $validado['extension'];
        $destino = $dir . '/' . $nombreDisco;
        $tmp = $validado['tmp'];

        $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $destino) : copy($tmp, $destino);
        if (!$ok || !is_file($destino)) {
            throw new HttpException(self::MENSAJE_CARGA, 500);
        }

        $id = $this->repo->crear([
            'cumplimiento_id' => $cumplimientoId,
            'tipo_soporte' => $tipo,
            'nombre_archivo' => $validado['nombre_original'],
            'ruta_archivo' => $relativo . '/' . $nombreDisco,
            'mime_type' => $validado['mime'],
            'tamano_bytes' => $validado['tamano'],
            'cargado_por_usuario_id_ext' => $usuarioId,
        ]);

        $fila = $this->repo->buscarPorId($id);

        return $this->normalizar($fila ?? ['soporte_id' => $id, 'cumplimiento_id' => $cumplimientoId] + $validado);
    }

    /**
     * @return array{contenido:string,nombre:string,mime:string}
     */
    public function descargar(int $soporteId): array
    {
        $fila = $this->repo->buscarPorId($soporteId);
        if ($fila === null) {
            throw new HttpException(self::MENSAJE_NO_ENCONTRADO, 404);
        }

        $ruta = $this->rutaAbsoluta((string)$fila['ruta_archivo']);
        if ($ruta === null || !is_file($ruta)) {
            throw new HttpException(self::MENSAJE_NO_ENCONTRADO, 404);
        }

        $contenido = file_get_contents($ruta);
        if ($contenido === false) {
            throw new HttpException(self::MENSAJE_NO_ENCONTRADO, 404);
        }

        return [
            'contenido' => $contenido,
            'nombre' => (string)$fila['nombre_archivo'],
            'mime' => (string)($fila['mime_type'] ?: 'application/octet-stream'),
        ];
    }

    /**
     * @return array{eliminado:true,resultado:?string}
     */
    public function eliminar(int $soporteId): array
    {
        $fila = $this->repo->buscarPorId($soporteId);
        if ($fila === null) {
            throw new HttpException('El soporte no existe.', 404);
        }

        $cumplimientoId = (int)$fila['cumplimiento_id'];
        $ruta = $this->rutaAbsoluta((string)$fila['ruta_archivo']);
        $this->repo->eliminar($soporteId);
        $this->borrarArchivo($ruta);

        $resultado = null;
        $requiere = (int)($fila['capacitacion_certificado'] ?? 0) === 1;
        $eraAprobado = strtoupper((string)($fila['resultado'] ?? '')) === CumplimientoService::RESULTADO_APROBADO;
        if ($requiere && $eraAprobado && $this->repo->contarPorCumplimiento($cumplimientoId) === 0) {
            $resultado = $this->revertirAprobado($cumplimientoId, $fila);
        }

        return ['eliminado' => true, 'resultado' => $resultado];
    }

    public function eliminarArchivosDeCumplimiento(int $cumplimientoId): void
    {
        $filas = $this->repo->rutasPorCumplimiento($cumplimientoId);
        foreach ($filas as $fila) {
            $this->borrarArchivo($this->rutaAbsoluta((string)$fila['ruta_archivo']));
        }
        $this->repo->eliminarPorCumplimiento($cumplimientoId);
        $dir = $this->directorioBase() . '/soportes/' . $cumplimientoId;
        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }

    /** @return array<string,mixed> */
    private function exigirCumplimiento(int $cumplimientoId): array
    {
        $fila = $this->cumplimientos->buscarPorId($cumplimientoId);
        if ($fila === null) {
            throw new HttpException('El cumplimiento no existe.', 404);
        }

        return $fila;
    }

    /**
     * @param array<string,mixed> $archivo
     * @return array{tmp:string,nombre_original:string,extension:string,mime:string,tamano:int}
     */
    private function validarArchivo(array $archivo): array
    {
        $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || ($archivo['tmp_name'] ?? '') === '') {
            throw new HttpException('Debe seleccionar un archivo.', 422);
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new HttpException(self::MENSAJE_TAMANO, 422);
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException(self::MENSAJE_CARGA, 422);
        }

        $tamano = (int)($archivo['size'] ?? 0);
        if ($tamano <= 0) {
            throw new HttpException('El archivo está vacío.', 422);
        }
        if ($tamano > $this->tamanoMaximo()) {
            throw new HttpException(self::MENSAJE_TAMANO, 422);
        }

        $original = $this->nombreVisible((string)($archivo['name'] ?? 'archivo'));
        $extension = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::MIME[$extension])) {
            throw new HttpException(self::MENSAJE_FORMATO, 422);
        }

        $tmp = (string)$archivo['tmp_name'];
        if (!is_uploaded_file($tmp) && !(PHP_SAPI === 'cli' && is_file($tmp))) {
            throw new HttpException(self::MENSAJE_CARGA, 422);
        }

        $mime = $this->mimeReal($tmp);
        $esperado = self::MIME[$extension];
        if ($mime !== $esperado) {
            throw new HttpException(self::MENSAJE_FORMATO, 422);
        }

        return [
            'tmp' => $tmp,
            'nombre_original' => $original,
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'mime' => $mime,
            'tamano' => $tamano,
        ];
    }

    private function mimeReal(string $tmp): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);

        return is_string($mime) ? strtolower($mime) : '';
    }

    private function nombreVisible(string $nombre): string
    {
        $base = basename(str_replace(['\\', "\0"], '/', $nombre));
        $base = preg_replace('/[^A-Za-z0-9._-]/', '_', $base) ?: 'archivo';

        return substr($base, 0, 255);
    }

    private function normalizarTipo(?string $tipo): string
    {
        $valor = strtoupper(trim((string)$tipo));
        if ($valor === '') {
            return 'CERTIFICADO';
        }
        if (!in_array($valor, self::TIPOS, true)) {
            throw new HttpException('El tipo de soporte no es válido.', 422);
        }

        return $valor;
    }

    private function rutaAbsoluta(string $relativa): ?string
    {
        $relativa = str_replace('\\', '/', ltrim($relativa, '/'));
        if ($relativa === '' || str_contains($relativa, '..')) {
            return null;
        }
        $base = $this->directorioBase();
        $full = $base . '/' . $relativa;
        $realBase = realpath($base);
        $realFull = realpath($full);
        if ($realBase === false) {
            return is_file($full) ? $full : $full;
        }
        if ($realFull !== false && str_starts_with($realFull, $realBase)) {
            return $realFull;
        }

        return is_file($full) ? $full : null;
    }

    private function borrarArchivo(?string $ruta): void
    {
        if ($ruta !== null && is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /**
     * @param array<string,mixed> $soporte
     */
    private function revertirAprobado(int $cumplimientoId, array $soporte): string
    {
        $sesionId = $soporte['sesion_id'] !== null ? (int)$soporte['sesion_id'] : 0;
        $asignacionId = (int)$soporte['asignacion_id'];
        $estado = 'ASISTIO';
        if ($sesionId > 0) {
            $part = $this->cumplimientos->participanteEnSesion($sesionId, $asignacionId);
            $asis = strtoupper((string)($part['estado_asistencia'] ?? ''));
            if ($asis === 'TARDE' || $asis === 'ASISTIO') {
                $estado = $asis;
            }
        }
        $this->cumplimientos->actualizar($cumplimientoId, ['resultado' => $estado]);

        return $estado;
    }

    /**
     * @param array<string,mixed> $fila
     * @return array<string,mixed>
     */
    private function normalizar(array $fila): array
    {
        return [
            'soporte_id' => (int)$fila['soporte_id'],
            'cumplimiento_id' => isset($fila['cumplimiento_id']) ? (int)$fila['cumplimiento_id'] : null,
            'tipo_soporte' => (string)($fila['tipo_soporte'] ?? 'CERTIFICADO'),
            'nombre_archivo' => (string)($fila['nombre_archivo'] ?? $fila['nombre_original'] ?? ''),
            'mime_type' => $fila['mime_type'] ?? null,
            'tamano_bytes' => isset($fila['tamano_bytes']) && $fila['tamano_bytes'] !== null
                ? (int)$fila['tamano_bytes']
                : null,
            'cargado_por_usuario_id_ext' => $fila['cargado_por_usuario_id_ext'] !== null
                ? (int)$fila['cargado_por_usuario_id_ext']
                : null,
            'created_at' => $fila['created_at'] ?? null,
        ];
    }
}
