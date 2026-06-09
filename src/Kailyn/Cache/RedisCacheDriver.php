<?php

namespace Kailyn\Cache;

use RuntimeException;

class RedisCacheDriver implements CacheDriver
{
    protected \Redis $redis;
    protected string $prefix;
    protected bool $connected = false;

    public function __construct(array $config)
    {
        $this->prefix = $config['prefix'] ?? '';

        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis extension is required for RedisCacheDriver');
        }

        $this->redis = new \Redis;

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;
        $timeout = $config['timeout'] ?? 0.0;

        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new RuntimeException("Cannot connect to Redis at {$host}:{$port}");
        }

        if ($password !== null && $password !== '') {
            $this->redis->auth($password);
        }

        $this->redis->select($database);
        $this->connected = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix($key));

        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $serialized = serialize($value);

        if ($ttl !== null) {
            return $this->redis->setex($this->prefix($key), $ttl, $serialized);
        }

        return $this->redis->set($this->prefix($key), $serialized);
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($this->prefix($key)) > 0;
    }

    public function clear(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');

        if (!empty($keys)) {
            return $this->redis->del($keys) > 0;
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefix($key));
    }

    public function remember(string $key, ?int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->delete($key);
        return $value;
    }

    protected function prefix(string $key): string
    {
        return $this->prefix . $key;
    }
}
