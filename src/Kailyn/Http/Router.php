<?php

namespace Kailyn\Http;

use Closure;
use RuntimeException;

class Router
{
    private array $routes = [];
    private array $patterns = [
        '{int}' => '(\d+)',
        '{string}' => '([a-zA-Z]+)',
        '{slug}' => '([a-zA-Z0-9-]+)',
        '{uuid}' => '([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})',
    ];

    public function get(string $pattern, callable|array|string $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array|string $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable|array|string $handler): void
    {
        $this->addRoute('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, callable|array|string $handler): void
    {
        $this->addRoute('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable|array|string $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    public function match(array $methods, string $pattern, callable|array|string $handler): void
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $pattern, $handler);
        }
    }

    public function any(string $pattern, callable|array|string $handler): void
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->addRoute($method, $pattern, $handler);
        }
    }

    public function addRoute(string $method, string $pattern, callable|array|string $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function matchRoute(string $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = $this->compilePattern($route['pattern']);

            if (preg_match($regex, $path, $matches)) {
                $params = array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                ];
            }
        }

        if ($method === 'HEAD') {
            return $this->matchRoute('GET', $path);
        }

        return null;
    }

    public function dispatch(Request $request): array
    {
        $method = $request->method();
        $path = $request->path();

        $result = $this->matchRoute($method, $path);

        if ($result === null) {
            throw new RuntimeException("Route not found: {$method} {$path}", 404);
        }

        return $result;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function compilePattern(string $pattern): string
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);

        foreach ($this->patterns as $placeholder => $replacement) {
            $regex = str_replace($placeholder, $replacement, $regex);
        }

        return '#^' . $regex . '$#';
    }
}
