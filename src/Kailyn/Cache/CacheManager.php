<?php

namespace Kailyn\Cache;

use RuntimeException;

class CacheManager
{
    protected array $config;
    protected array $stores = [];
    protected array $customDrivers = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function store(?string $name = null): CacheDriver
    {
        $name = $name ?? $this->config['default'] ?? 'file';

        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        return $this->stores[$name] = $this->resolve($name);
    }

    public function extend(string $name, callable $resolver): void
    {
        $this->customDrivers[$name] = $resolver;
    }

    protected function resolve(string $name): CacheDriver
    {
        if (isset($this->customDrivers[$name])) {
            $driver = call_user_func($this->customDrivers[$name], $this->config);
            if (!$driver instanceof CacheDriver) {
                throw new RuntimeException("Custom cache driver [{$name}] must implement CacheDriver");
            }
            return $driver;
        }

        $storeConfig = $this->config['stores'][$name] ?? null;

        if ($storeConfig === null) {
            throw new RuntimeException("Cache store [{$name}] is not defined");
        }

        $driver = $storeConfig['driver'] ?? 'file';

        $driverConfig = array_merge($storeConfig, ['prefix' => $this->config['prefix'] ?? '']);

        return match ($driver) {
            'file' => new FileCacheDriver($driverConfig),
            'redis' => new RedisCacheDriver($driverConfig),
            'database' => new DatabaseCacheDriver($driverConfig),
            default => throw new RuntimeException("Unsupported cache driver [{$driver}]"),
        };
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->store()->$method(...$parameters);
    }
}
