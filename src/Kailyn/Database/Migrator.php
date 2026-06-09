<?php

namespace Kailyn\Database;

use Kailyn\Config\Config;
use Kailyn\Foundation\Application;
use RuntimeException;

class Migrator
{
    protected Connection $connection;
    protected string $migrationPath;
    protected string $table = 'migrations';

    public function __construct(Connection $connection, string $migrationPath)
    {
        $this->connection = $connection;
        $this->migrationPath = rtrim($migrationPath, '/');
    }

    public function createMigrationTable(): void
    {
        Schema::create($this->table, function ($table) {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
            $table->timestamp('executed_at')->nullable();
        });
    }

    public function migrationTableExists(): bool
    {
        return Schema::hasTable($this->table);
    }

    public function getRan(): array
    {
        if (!$this->migrationTableExists()) {
            return [];
        }

        $results = $this->connection->select(
            "SELECT migration FROM {$this->table} ORDER BY id ASC"
        );

        return array_map(fn($row) => $row->migration, $results);
    }

    public function getMigrations(): array
    {
        if (!is_dir($this->migrationPath)) {
            return [];
        }

        $files = glob($this->migrationPath . '/*.php');
        sort($files);

        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = pathinfo($file, PATHINFO_FILENAME);
        }

        return $migrations;
    }

    public function getPending(): array
    {
        $ran = $this->getRan();
        $all = $this->getMigrations();

        return array_values(array_diff($all, $ran));
    }

    public function run(string $migration): void
    {
        $file = $this->migrationPath . '/' . $migration . '.php';

        if (!file_exists($file)) {
            throw new RuntimeException("Migration file not found: {$file}");
        }

        $instance = require $file;

        if (!$instance instanceof Migration) {
            throw new RuntimeException("Migration [{$migration}] must return an instance of Migration");
        }

        $instance->up();

        $this->log($migration);
    }

    public function rollback(string $migration): void
    {
        $file = $this->migrationPath . '/' . $migration . '.php';

        if (!file_exists($file)) {
            throw new RuntimeException("Migration file not found: {$file}");
        }

        $instance = require $file;

        if (!$instance instanceof Migration) {
            throw new RuntimeException("Migration [{$migration}] must return an instance of Migration");
        }

        $instance->down();

        $this->connection->delete(
            "DELETE FROM {$this->table} WHERE migration = ?",
            [$migration]
        );
    }

    public function getLastBatchNumber(): int
    {
        if (!$this->migrationTableExists()) {
            return 0;
        }

        $result = $this->connection->selectOne(
            "SELECT MAX(batch) as max_batch FROM {$this->table}"
        );

        return (int) ($result->max_batch ?? 0);
    }

    public function getMigrationsByBatch(int $batch): array
    {
        if (!$this->migrationTableExists()) {
            return [];
        }

        $results = $this->connection->select(
            "SELECT migration FROM {$this->table} WHERE batch = ? ORDER BY id ASC",
            [$batch]
        );

        return array_map(fn($row) => $row->migration, $results);
    }

    public function getMigrationPath(): string
    {
        return $this->migrationPath;
    }

    protected function log(string $migration): void
    {
        $batch = $this->getLastBatchNumber() + 1;

        $this->connection->insert(
            "INSERT INTO {$this->table} (migration, batch, executed_at) VALUES (?, ?, ?)",
            [$migration, $batch, date('Y-m-d H:i:s')]
        );
    }

    public static function resolve(): Migrator
    {
        $container = app();
        $config = $container->make(Config::class);
        $dbConfig = $config->get('database.connections.' . $config->get('database.default'));
        $connection = new Connection($dbConfig);
        $basePath = $container->make(Application::class)->basePath();

        return new static($connection, $basePath . '/database/migrations');
    }
}
