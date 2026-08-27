<?php

declare(strict_types=1);

use App\Core\Env;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Lee valores de los archivos en /config usando notacion de punto: config('catalogs.areas.tabla').
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $cargados = [];

        $segmentos = explode('.', $key);
        $archivo = array_shift($segmentos);

        if (!array_key_exists($archivo, $cargados)) {
            $ruta = BASE_PATH . '/config/' . $archivo . '.php';
            $cargados[$archivo] = file_exists($ruta) ? require $ruta : [];
        }

        $valor = $cargados[$archivo];

        foreach ($segmentos as $segmento) {
            if (!is_array($valor) || !array_key_exists($segmento, $valor)) {
                return $default;
            }
            $valor = $valor[$segmento];
        }

        return $valor;
    }
}

if (!function_exists('generateUuid')) {
    function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('nullable_trimmed_string')) {
    function nullable_trimmed_string(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
