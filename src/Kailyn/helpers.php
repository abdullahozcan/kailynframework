<?php

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

if (!function_exists('app')) {
    function app(?string $abstract = null, ?Kailyn\Foundation\Application $instance = null): mixed
    {
        static $application = null;

        if ($instance !== null) {
            $application = $instance;
        }

        if ($application === null) {
            $application = new Kailyn\Foundation\Application(
                dirname(__DIR__, 2)
            );
        }

        if ($abstract === null) {
            return $application;
        }

        return $application->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return app(Kailyn\Config\Config::class);
        }

        return app(Kailyn\Config\Config::class)->get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(?string $path = null): string
    {
        return app()->basePath($path);
    }
}

if (!function_exists('view_path')) {
    function view_path(?string $path = null): string
    {
        return app()->viewPath($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(?string $path = null): string
    {
        return app()->storagePath($path);
    }
}

if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        return app(Kailyn\Template\Engine::class)->render($view, $data);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): Kailyn\Http\Response
    {
        return Kailyn\Http\Response::redirect($url, $status);
    }
}

if (!function_exists('back')) {
    function back(): Kailyn\Http\Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return Kailyn\Http\Response::redirect($referer);
    }
}

if (!function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        $manager = app(Kailyn\Session\SessionManager::class);

        if ($key === null) {
            return $manager;
        }

        return $manager->get($key, $default);
    }
}

if (!function_exists('validator')) {
    function validator(array $data, array $rules, array $messages = []): Kailyn\Validation\Validator
    {
        return Kailyn\Validation\Validator::make($data, $rules, $messages);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return app(Kailyn\Session\SessionManager::class)->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . csrf_token() . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}
