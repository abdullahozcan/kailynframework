<?php

namespace Kailyn\Database;

use PDO;
use PDOStatement;
use RuntimeException;

class Connection
{
    protected PDO $pdo;
    protected string $name;
    protected int $fetchMode = PDO::FETCH_OBJ;

    public function __construct(array $config, string $name = 'default')
    {
        $this->name = $name;
        $this->connect($config);
    }

    protected function connect(array $config): void
    {
        $driver = $config['driver'] ?? 'sqlite';

        $this->pdo = match ($driver) {
            'sqlite' => $this->connectSqlite($config),
            'mysql' => $this->connectMysql($config),
            'pgsql' => $this->connectPgsql($config),
            default => throw new RuntimeException("Unsupported driver: {$driver}"),
        };

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $this->fetchMode);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    protected function connectSqlite(array $config): PDO
    {
        $database = $config['database'] ?? ':memory:';

        return new PDO("sqlite:{$database}");
    }

    protected function connectMysql(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '3306';
        $database = $config['database'] ?? '';
        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '');
    }

    protected function connectPgsql(array $config): PDO
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $database = $config['database'] ?? '';
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

        return new PDO($dsn, $config['username'] ?? 'postgres', $config['password'] ?? '');
    }

    public function query(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll();
    }

    public function selectOne(string $sql, array $bindings = []): object|false
    {
        return $this->query($sql, $bindings)->fetch();
    }

    public function insert(string $sql, array $bindings = []): bool
    {
        return $this->query($sql, $bindings)->rowCount() > 0;
    }

    public function update(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    public function delete(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->query($sql, $bindings)->rowCount() >= 0;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setFetchMode(int $mode): void
    {
        $this->fetchMode = $mode;
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, $mode);
    }
}
