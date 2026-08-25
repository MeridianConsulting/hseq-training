<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Env;
use App\Core\Request;

class CorsMiddleware
{
    public function handle(Request $request): void
    {
        $allowedOrigins = explode(',', Env::get('CORS_ALLOWED_ORIGINS', 'http://localhost:3000'));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }

        header('Access-Control-Allow-Methods: ' . Env::get('CORS_ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS'));
        header('Access-Control-Allow-Headers: ' . Env::get('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With'));
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');

        if ($request->method() === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
