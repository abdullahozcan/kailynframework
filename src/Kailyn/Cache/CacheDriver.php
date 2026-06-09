<?php

namespace Kailyn\Cache;

interface CacheDriver
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function has(string $key): bool;

    public function remember(string $key, ?int $ttl, callable $callback): mixed;

    public function rememberForever(string $key, callable $callback): mixed;

    public function pull(string $key, mixed $default = null): mixed;
}
