<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use App\Core\Exceptions\AuthenticationException;

class Application
{
    private static ?Application $instance = null;
    private Router $router;

    private function __construct()
    {
        $this->bootstrap();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function bootstrap(): void
    {
        Env::load(BASE_PATH);
        $this->setupErrorHandling();
        $this->router = new Router();
        $this->loadRoutes();
    }

    private function setupErrorHandling(): void
    {
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            Logger::error("PHP Error: {$message}", [
                'file' => $file,
                'line' => $line,
                'severity' => $severity,
            ]);
            if (Env::get('APP_DEBUG', false)) {
                Response::error("Error: {$message} in {$file}:{$line}", 500);
            }
            Response::error('Error interno del servidor', 500);
            return true;
        });

        set_exception_handler(function (\Throwable $e): void {
            if ($e instanceof ValidationException) {
                Response::validationError($e->getErrors(), $e->getMessage());
                return;
            }

            if ($e instanceof AuthenticationException) {
                Response::unauthorized($e->getMessage());
                return;
            }

            Logger::error($e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof HttpException) {
                Response::error($e->getMessage(), $e->getStatusCode());
                return;
            }

            $message = Env::get('APP_DEBUG', false)
                ? $e->getMessage()
                : 'Error interno del servidor';

            Response::error($message, 500);
        });
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        require BASE_PATH . '/routes/api.php';
    }

    public function run(): void
    {
        $request = new Request();

        $corsMiddleware = new \App\Middleware\CorsMiddleware();
        $corsMiddleware->handle($request);

        $this->router->resolve($request);
    }

    public function getRouter(): Router
    {
        return $this->router;
    }
}
