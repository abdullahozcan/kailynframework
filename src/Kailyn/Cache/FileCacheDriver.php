<?php

namespace Kailyn\Cache;

use RuntimeException;

class FileCacheDriver implements CacheDriver
{
    protected string $path;
    protected string $prefix;

    public function __construct(array $config)
    {
        $this->path = rtrim($config['path'] ?? sys_get_temp_dir() . '/kailyn-cache', '/');
        $this->prefix = $config['prefix'] ?? '';

        if (!is_dir($this->path)) {
            if (!mkdir($this->path, 0755, true) && !is_dir($this->path)) {
                throw new RuntimeException("Cannot create cache directory: {$this->path}");
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->read($key);

        if ($data === null) {
            return $default;
        }

        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return $default;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expires = $ttl !== null ? time() + $ttl : null;
        $data = ['value' => $value, 'expires' => $expires];

        return $this->write($key, $data);
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->path . '/' . $this->prefix . '*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        $data = $this->read($key);

        if ($data === null) {
            return false;
        }

        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->delete($key);
            return false;
        }

        return true;
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

    protected function path(string $key): string
    {
        return $this->path . '/' . $this->prefix . sha1($key) . '.cache';
    }

    protected function read(string $key): ?array
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);

        if (!is_array($data) || !array_key_exists('value', $data)) {
            return null;
        }

        return $data;
    }

    protected function write(string $key, array $data): bool
    {
        $path = $this->path($key);

        return file_put_contents($path, json_encode($data), LOCK_EX) !== false;
    }
}
