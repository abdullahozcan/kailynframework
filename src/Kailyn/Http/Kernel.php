<?php

namespace Kailyn\Http;

use Closure;
use Kailyn\Container\Container;
use RuntimeException;

class Kernel
{
    protected array $middleware = [];
    protected array $middlewareGroups = [];
    protected array $routeMiddleware = [];

    public function __construct(
        protected Container $container,
        protected Router $router
    ) {}

    public function setMiddleware(array $middleware): void
    {
        $this->middleware = $middleware;
    }

    public function setMiddlewareGroups(array $groups): void
    {
        $this->middlewareGroups = $groups;
    }

    public function handle(Request $request): Response
    {
        try {
            $request = $this->resolveMethodOverride($request);
            $route = $this->router->dispatch($request);

            $this->routeMiddleware = $route['middleware'] ?? [];

            $response = $this->runMiddleware($request, function (Request $request) use ($route) {
                return $this->resolveHandler($route['handler'], $route['params'], $request);
            });

            return $response;
        } catch (RuntimeException $e) {
            if ($e->getCode() === 404) {
                return new Response('Not Found', 404);
            }

            $debug = $this->container->make(\Kailyn\Config\Config::class)->get('app.debug', false);

            if ($debug) {
                return new Response($e->getMessage() . "\n" . $e->getTraceAsString(), 500);
            }

            return new Response('Server Error', 500);
        } catch (\Throwable $e) {
            $debug = $this->container->make(\Kailyn\Config\Config::class)->get('app.debug', false);

            if ($debug) {
                return new Response($e->getMessage() . "\n" . $e->getTraceAsString(), 500);
            }

            return new Response('Server Error', 500);
        }
    }

    protected function runMiddleware(Request $request, callable $handler): Response
    {
        $pipeline = $this->resolveMiddlewarePipeline($request);

        if (empty($pipeline)) {
            return $handler($request);
        }

        $pipeline = array_reverse($pipeline);
        $next = $handler;

        foreach ($pipeline as $middlewareClass) {
            $middleware = $this->container->make($middlewareClass);
            $current = $next;

            if ($middleware instanceof Middleware) {
                $next = function (Request $request) use ($middleware, $current) {
                    return $middleware->handle($request, $current);
                };
            }
        }

        return $next($request);
    }

    protected function resolveMiddlewarePipeline(Request $request): array
    {
        $pipeline = [];

        foreach ($this->routeMiddleware as $name) {
            if (isset($this->middleware[$name])) {
                $pipeline[] = $this->middleware[$name];
            } elseif (isset($this->middlewareGroups[$name])) {
                foreach ($this->middlewareGroups[$name] as $m) {
                    if (isset($this->middleware[$m])) {
                        $pipeline[] = $this->middleware[$m];
                    }
                }
            }
        }

        return $pipeline;
    }

    protected function resolveMethodOverride(Request $request): Request
    {
        if ($request->method() !== 'POST') {
            return $request;
        }

        $method = $request->input('_method');

        if ($method !== null) {
            $server = $request->server;
            $server['REQUEST_METHOD'] = strtoupper($method);
            return new Request($request->query, $request->all(), $request->headers(), $server);
        }

        return $request;
    }

    public function terminate(Request $request, Response $response): void
    {

    }

    private function resolveHandler(callable|array|string $handler, array $params, Request $request): Response
    {
        $result = null;

        if ($handler instanceof Closure) {
            $result = $this->container->call($handler, array_merge($params, ['request' => $request]));
        } elseif (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = $this->container->make($class);
            $result = $this->container->call([$instance, $method], array_merge($params, ['request' => $request]));
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            $parts = explode('@', $handler);
            $class = $parts[0];
            $method = $parts[1] ?? 'index';
            $instance = $this->container->make($class);
            $result = $this->container->call([$instance, $method], array_merge($params, ['request' => $request]));
        } else {
            throw new RuntimeException('Invalid route handler');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result) || is_numeric($result)) {
            return new Response((string) $result);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        return new Response((string) $result);
    }
}
