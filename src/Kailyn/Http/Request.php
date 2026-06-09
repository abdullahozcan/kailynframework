<?php

namespace Kailyn\Http;

class Request
{
    private array $query;
    private array $body;
    private array $headers;
    public array $server;

    public function __construct(array $query = [], array $body = [], array $headers = [], array $server = [])
    {
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->server = $server;
    }

    public static function capture(): static
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$header] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }

        $body = $_POST;
        $contentType = $headers['content-type'] ?? '';

        if (empty($body) && str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true) ?? [];
        }

        return new static($_GET, $body, $headers, $_SERVER);
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function path(): string
    {
        $uri = parse_url($this->uri(), PHP_URL_PATH);
        return rtrim($uri ?? '', '/') ?: '/';
    }

    public function scheme(): string
    {
        if (isset($this->server['HTTPS']) && $this->server['HTTPS'] === 'on') {
            return 'https';
        }

        return 'http';
    }

    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? 'localhost';
    }

    public function fullUrl(): string
    {
        return $this->scheme() . '://' . $this->host() . $this->uri();
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function only(array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }

        return $result;
    }

    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }

    public function expectsJson(): bool
    {
        $accept = $this->header('Accept');
        return str_contains($accept ?? '', 'application/json') || $this->isAjax();
    }

    public function __get(string $key): mixed
    {
        return $this->input($key);
    }
}
