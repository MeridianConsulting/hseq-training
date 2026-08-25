<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Env;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $token = $request->bearerToken();

        if (!$token) {
            Response::unauthorized('Token de autenticación requerido');
        }

        try {
            $secret = Env::get('JWT_SECRET', '');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $userData = (array)$decoded->data;

            $request->setAttribute('user', $userData);
            $request->setAttribute('user_id', $userData['id'] ?? null);
        } catch (ExpiredException $e) {
            Response::unauthorized('Token expirado');
        } catch (\Exception $e) {
            Response::unauthorized('Token inválido');
        }
    }
}
