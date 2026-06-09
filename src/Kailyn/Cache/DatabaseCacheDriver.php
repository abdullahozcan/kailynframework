<?php

namespace Kailyn\Cache;

use Kailyn\Database\Connection;

class DatabaseCacheDriver implements CacheDriver
{
    protected Connection $connection;
    protected string $table;
    protected string $prefix;

    public function __construct(array $config)
    {
        $this->table = $config['table'] ?? 'cache';
        $this->prefix = $config['prefix'] ?? '';

        $connectionName = $config['connection'] ?? 'default';
        $dbConfig = \Kailyn\Config\Config::class;

        if (class_exists($dbConfig)) {
            $app = \Kailyn\Foundation\Application::class;
            if (class_exists($app)) {
                $container = app();
                if ($container && $container instanceof \Kailyn\Container\Container) {
                    $cnf = $container->make(\Kailyn\Config\Config::class);
                    $dbCfg = $cnf->get("database.connections.{$connectionName}", $cnf->get('database.connections.' . $cnf->get('database.default')));
                    $this->connection = new Connection($dbCfg, $connectionName);
                    return;
                }
            }
        }

        throw new \RuntimeException("Cannot resolve database connection for cache store");
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $row = $this->connection->selectOne(
            "SELECT value, expires FROM {$this->table} WHERE `key` = ?",
            [$this->prefix($key)]
        );

        if ($row === null) {
            return $default;
        }

        if ($row->expires !== null && strtotime($row->expires) < time()) {
            $this->delete($key);
            return $default;
        }

        $data = json_decode($row->value, true);
        return $data !== null ? $data : $default;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $expires = $ttl !== null ? date('Y-m-d H:i:s', time() + $ttl) : null;
        $serialized = json_encode($value);

        $driver = $this->connection->getDriverName();

        if (in_array($driver, ['mysql'])) {
            $this->connection->statement(
                "INSERT INTO {$this->table} (`key`, value, expires) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value), expires = VALUES(expires)",
                [$this->prefix($key), $serialized, $expires]
            );
        } else {
            $this->connection->statement(
                "INSERT INTO {$this->table} (`key`, value, expires) VALUES (?, ?, ?)
                 ON CONFLICT(`key`) DO UPDATE SET value = ?, expires = ?",
                [$this->prefix($key), $serialized, $expires, $serialized, $expires]
            );
        }

        return true;
    }

    public function delete(string $key): bool
    {
        $this->connection->delete(
            "DELETE FROM {$this->table} WHERE `key` = ?",
            [$this->prefix($key)]
        );

        return true;
    }

    public function clear(): bool
    {
        $this->connection->statement("DELETE FROM {$this->table}");
        return true;
    }

    public function has(string $key): bool
    {
        $row = $this->connection->selectOne(
            "SELECT expires FROM {$this->table} WHERE `key` = ?",
            [$this->prefix($key)]
        );

        if ($row === null) {
            return false;
        }

        if ($row->expires !== null && strtotime($row->expires) < time()) {
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

    protected function prefix(string $key): string
    {
        return $this->prefix . $key;
    }
}
