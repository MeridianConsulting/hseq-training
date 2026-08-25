<?php

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;

class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        $preferred = self::isProduction() ? '.env.production' : '.env';
        $file = file_exists(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $preferred)
            ? $preferred
            : '.env';

        $dotenv = Dotenv::createImmutable($path, $file);
        $dotenv->safeLoad();
        self::$loaded = true;
    }

    private static function isProduction(): bool
    {
        if (php_sapi_name() === 'cli') {
            return false;
        }

        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';

        if ($host !== '') {
            if ($host[0] === '[') {
                $end = strpos($host, ']');
                $host = $end !== false ? substr($host, 1, $end - 1) : $host;
            } else {
                $colon = strrpos($host, ':');
                if ($colon !== false) {
                    $host = substr($host, 0, $colon);
                }
            }
        }

        $localPatterns = ['localhost', '127.0.0.1', '::1', ''];

        return !in_array($host, $localPatterns, true)
            && !str_starts_with($host, '127.')
            && !str_starts_with($host, '192.168.');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower((string)$value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
