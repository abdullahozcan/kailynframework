<?php

namespace Kailyn\Foundation;

use Kailyn\Component\ComponentManager;
use Kailyn\Config\Config;
use Kailyn\Container\Container;
use Kailyn\Http\Kernel;
use Kailyn\Http\Request;
use Kailyn\Http\Router;
use Kailyn\Session\SessionManager;
use Kailyn\Template\Engine;
use Kailyn\Validation\Validator;

class Application extends Container
{
    private string $basePath;
    private bool $booted = false;
    private array $middleware = [];
    private array $middlewareGroups = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim(realpath($basePath) ?: $basePath, '/');

        $this->instance(Container::class, $this);
        $this->instance(Application::class, $this);

        app(instance: $this);
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        $this->singleton(Config::class, fn() => new Config($this->configPath()));
        $this->singleton(Router::class, function () {
            $router = new Router;
            $router->addInternalRoutes();
            return $router;
        });
        $this->singleton(SessionManager::class);
        $this->singleton(ComponentManager::class, fn() => new ComponentManager(
            $this,
            $this->make(Engine::class)
        ));
        $this->singleton(Engine::class, fn() => new Engine(
            $this->viewPath(),
            $this->storagePath('views')
        ));

        $this->registerMiddleware();
    }

    public function run(): void
    {
        $this->boot();

        $this->loadRoutes();

        $kernel = new Kernel($this, $this->make(Router::class));
        $kernel->setMiddleware($this->middleware);
        $kernel->setMiddlewareGroups($this->middlewareGroups);

        $request = Request::capture();
        $response = $kernel->handle($request);
        $response->send();
        $kernel->terminate($request, $response);
    }

    protected function loadRoutes(): void
    {
        $routesFile = $this->basePath('routes/web.php');

        if (file_exists($routesFile)) {
            $app = $this;
            require $routesFile;
        }
    }

    protected function registerMiddleware(): void
    {
        $this->middleware = [
            'auth' => \App\Middleware\AuthMiddleware::class,
            'guest' => \App\Middleware\GuestMiddleware::class,
            'csrf' => \App\Middleware\CsrfMiddleware::class,
            'security-headers' => \App\Middleware\SecurityHeadersMiddleware::class,
            'throttle' => \App\Middleware\ThrottleMiddleware::class,
        ];

        $this->middlewareGroups = [
            'web' => ['csrf', 'security-headers'],
        ];
    }

    public function addMiddleware(string $name, string $class): void
    {
        $this->middleware[$name] = $class;
    }

    public function getMiddleware(string $name): ?string
    {
        return $this->middleware[$name] ?? null;
    }

    public function getMiddlewareGroup(string $name): array
    {
        return $this->middlewareGroups[$name] ?? [];
    }

    public function basePath(?string $path = null): string
    {
        return $path
            ? $this->basePath . '/' . ltrim($path, '/')
            : $this->basePath;
    }

    public function configPath(): string
    {
        return $this->basePath('config');
    }

    public function viewPath(): string
    {
        return $this->basePath('app/Views');
    }

    public function storagePath(?string $path = null): string
    {
        return $path
            ? $this->basePath('storage/' . ltrim($path, '/'))
            : $this->basePath('storage');
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }
}
