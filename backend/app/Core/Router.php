<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $groupStack = [];

    public function get(string $path, array $action, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $action, $middleware);
    }

    public function post(string $path, array $action, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $action, $middleware);
    }

    public function put(string $path, array $action, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $action, $middleware);
    }

    public function patch(string $path, array $action, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $path, $action, $middleware);
    }

    public function delete(string $path, array $action, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $action, $middleware);
    }

    public function group(array $attributes, callable $callback): self
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
        return $this;
    }

    private function addRoute(string $method, string $path, array $action, array $middleware = []): self
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMiddleware = array_merge($groupMiddleware, (array)$group['middleware']);
            }
        }

        $fullPath = $prefix . '/' . trim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'action' => $action,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'pattern' => $this->buildPattern($fullPath),
        ];

        return $this;
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function resolve(Request $request): void
    {
        $method = $request->method();
        $uri = $request->uri();

        if ($method === 'OPTIONS') {
            Response::json(null, 'OK', 200);
            return;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $middleware) {
                    if (is_array($middleware)) {
                        $clase = $middleware[0];
                        $argumentos = array_slice($middleware, 1);
                        $middlewareInstance = new $clase(...$argumentos);
                    } else {
                        $middlewareInstance = new $middleware();
                    }

                    $middlewareInstance->handle($request);
                }

                [$controllerClass, $methodName] = $route['action'];
                $controller = new $controllerClass();
                $controller->$methodName($request, ...array_values($params));
                return;
            }
        }

        Response::notFound('Ruta no encontrada: ' . $method . ' ' . $uri);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
