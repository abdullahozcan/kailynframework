<?php

namespace Kailyn\Config;

class Config
{
    private array $items = [];

    public function __construct(string $configPath)
    {
        $this->loadDirectory($configPath);
    }

    private function loadDirectory(string $path): void
    {
        foreach (glob($path . '/*.php') as $file) {
            $key = basename($file, '.php');
            $this->items[$key] = require $file;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $config = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($config) || !array_key_exists($segment, $config)) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $config = &$this->items;

        foreach ($segments as $segment) {
            if (!isset($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
    }

    public function all(): array
    {
        return $this->items;
    }
}
