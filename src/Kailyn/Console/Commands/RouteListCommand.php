<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;
use Kailyn\Http\Router;

class RouteListCommand extends Command
{
    protected string $signature = 'route:list';
    protected string $description = 'List all registered routes';

    public function handle(): int
    {
        $app = $this->getApplication()->getKailynApp();
        $routesFile = $app->basePath('routes/web.php');

        if (file_exists($routesFile)) {
            $router = $app->make(Router::class);
            $app->instance(Router::class, $router);
            require $routesFile;
            $routes = $router->getPublicRoutes();
        } else {
            $routes = [];
        }

        if (empty($routes)) {
            $this->warn('No routes registered.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($routes as $route) {
            $handler = $route['handler'];

            if ($handler instanceof \Closure) {
                $handlerStr = 'Closure';
            } elseif (is_array($handler)) {
                $handlerStr = implode('@', $handler);
            } elseif (is_string($handler)) {
                $handlerStr = $handler;
            } else {
                $handlerStr = gettype($handler);
            }

            $rows[] = [
                $route['method'],
                $route['pattern'],
                $handlerStr,
            ];
        }

        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

        $this->table(['Method', 'URI', 'Handler'], $rows);

        return self::SUCCESS;
    }
}
