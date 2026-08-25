<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function json(mixed $data = null, string $message = '', int $status = 200, bool $success = true, mixed $errors = null): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ];

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Operación exitosa', int $status = 200): void
    {
        self::json($data, $message, $status, true);
    }

    public static function created(mixed $data = null, string $message = 'Recurso creado exitosamente'): void
    {
        self::json($data, $message, 201, true);
    }

    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    public static function error(string $message = 'Error interno del servidor', int $status = 500, mixed $errors = null): void
    {
        self::json(null, $message, $status, false, $errors);
    }

    public static function notFound(string $message = 'Recurso no encontrado'): void
    {
        self::error($message, 404);
    }

    public static function unauthorized(string $message = 'No autorizado'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acceso denegado'): void
    {
        self::error($message, 403);
    }

    public static function validationError(mixed $errors, string $message = 'Error de validación'): void
    {
        self::json(null, $message, 422, false, $errors);
    }
}
