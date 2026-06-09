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

    public function addRoute(string $method, string $pattern, callable|array|string $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function middleware(array $middleware): RouteRegistrar
    {
        return new RouteRegistrar($this, $middleware);
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
                    'middleware' => $route['middleware'] ?? [],
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

    public function addInternalRoutes(): void
    {
        $this->addRoute('POST', '/_kailyn/update', function (\Kailyn\Http\Request $request) {
            $manager = app(\Kailyn\Component\ComponentManager::class);
            return $manager->handleUpdate($request);
        });
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getPublicRoutes(): array
    {
        return array_filter($this->routes, fn($r) => $r['pattern'] !== '/_kailyn/update');
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

class RouteRegistrar
{
    private Router $router;
    private array $middleware;

    public function __construct(Router $router, array $middleware)
    {
        $this->router = $router;
        $this->middleware = $middleware;
    }

    public function get(string $pattern, callable|array|string $handler): void
    {
        $this->router->addRoute('GET', $pattern, $handler, $this->middleware);
    }

    public function post(string $pattern, callable|array|string $handler): void
    {
        $this->router->addRoute('POST', $pattern, $handler, $this->middleware);
    }

    public function put(string $pattern, callable|array|string $handler): void
    {
        $this->router->addRoute('PUT', $pattern, $handler, $this->middleware);
    }

    public function patch(string $pattern, callable|array|string $handler): void
    {
        $this->router->addRoute('PATCH', $pattern, $handler, $this->middleware);
    }

    public function delete(string $pattern, callable|array|string $handler): void
    {
        $this->router->addRoute('DELETE', $pattern, $handler, $this->middleware);
    }

    public function match(array $methods, string $pattern, callable|array|string $handler): void
    {
        foreach ($methods as $method) {
            $this->router->addRoute(strtoupper($method), $pattern, $handler, $this->middleware);
        }
    }
}
