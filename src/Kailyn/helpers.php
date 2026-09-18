<?php

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return $value;
    }
}

if (!function_exists('version')) {
    function version(): string
    {
        static $version = null;
        if ($version === null) {
            $versionFile = dirname(__DIR__, 2) . '/VERSION';
            $version = file_exists($versionFile)
                ? trim(file_get_contents($versionFile))
                : config('app.version', '0.0.0');
        }
        return $version;
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
        $referer = str_replace(["\r", "\n"], '', $referer);

        if (!str_starts_with($referer, '/') && !str_starts_with($referer, 'http')) {
            $referer = '/';
        }

        $parsed = parse_url($referer);
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($parsed['host'] ?? '' !== '' && ($parsed['host'] ?? '') !== $host) {
            $referer = '/';
        }

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
        return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_meta')) {
    function csrf_meta(): string
    {
        return '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('cache')) {
    function cache(string $key = null, mixed $value = null, ?int $ttl = null): mixed
    {
        $manager = app(Kailyn\Cache\CacheManager::class);

        if ($key === null) {
            return $manager;
        }

        if ($value !== null) {
            return $manager->set($key, $value, $ttl);
        }

        return $manager->get($key);
    }
}
