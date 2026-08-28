<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private string $method;
    private string $uri;
    private array $queryParams;
    private array $body;
    private array $headers;
    private array $files;
    private array $attributes = [];

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $this->parseUri();
        $this->queryParams = $_GET;
        $this->body = $this->parseBody();
        $this->headers = $this->parseHeaders();
        $this->files = $_FILES;
    }

    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        $basePath = dirname($_SERVER['SCRIPT_NAME']);

        if ($basePath !== '/' && $basePath !== '\\' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        return '/' . trim($uri, '/');
    }

    private function parseBody(): array
    {
        if ($this->method === 'GET') {
            return [];
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $rawBody = file_get_contents('php://input');
            return json_decode($rawBody, true) ?? [];
        }

        return $_POST;
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        return $headers;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->queryParams;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->body, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return isset($this->body[$key]);
    }

    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization', '');

        if (!is_string($auth) || $auth === '') {
            $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if ((!is_string($auth) || $auth === '') && function_exists('apache_request_headers')) {
            $apache = apache_request_headers();
            $auth = $apache['Authorization'] ?? $apache['authorization'] ?? '';
        }

        if (is_string($auth) && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function user(): ?array
    {
        return $this->getAttribute('user');
    }

    public function userId(): int
    {
        return (int)$this->getAttribute('user_id', 0);
    }

    public function ip(): ?string
    {
        $reenviada = $this->header('x-forwarded-for', '');
        if (is_string($reenviada) && $reenviada !== '') {
            $primera = trim(explode(',', $reenviada)[0]);
            return $primera !== '' ? $primera : null;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }
}
