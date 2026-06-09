<?php

namespace Kailyn\Database;

use MongoDB\Driver\Manager;
use MongoDB\Driver\Command;
use MongoDB\Driver\Query;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\ReadPreference;
use RuntimeException;

class MongoConnection
{
    protected Manager $manager;
    protected string $database;
    protected string $name;

    public function __construct(array $config, string $name = 'mongodb')
    {
        if (!extension_loaded('mongodb')) {
            throw new RuntimeException('MongoDB PHP extension (ext-mongodb) is required.');
        }

        $this->name = $name;
        $this->database = $config['database'] ?? 'kailyn';
        $uri = $config['uri'] ?? 'mongodb://localhost:27017';
        $options = $config['options'] ?? [];
        $driverOptions = $config['driverOptions'] ?? [];

        $this->manager = new Manager($uri, $options, $driverOptions);
    }

    public function table(string $table): MongoQueryBuilder
    {
        return new MongoQueryBuilder($this, $table);
    }

    public function find(string $table, array $filter = [], array $options = []): array
    {
        $query = new Query($filter, $options);
        $cursor = $this->manager->executeQuery("{$this->database}.{$table}", $query);
        $results = [];

        foreach ($cursor as $document) {
            $results[] = $document;
        }

        return $results;
    }

    public function findOne(string $table, array $filter = [], array $options = []): ?object
    {
        $options['limit'] = 1;
        $results = $this->find($table, $filter, $options);
        return $results[0] ?? null;
    }

    public function insert(string $table, array $document): string
    {
        $bulk = new BulkWrite;
        $id = $bulk->insert($document);
        $this->manager->executeBulkWrite("{$this->database}.{$table}", $bulk);
        return (string) $id;
    }

    public function update(string $table, array $filter, array $update, bool $multi = false): int
    {
        $bulk = new BulkWrite;
        $bulk->update($filter, ['$set' => $update], ['multi' => $multi]);
        $result = $this->manager->executeBulkWrite("{$this->database}.{$table}", $bulk);
        return $result->getModifiedCount();
    }

    public function delete(string $table, array $filter, bool $limit = true): int
    {
        $bulk = new BulkWrite;
        $bulk->delete($filter, ['limit' => $limit]);
        $result = $this->manager->executeBulkWrite("{$this->database}.{$table}", $bulk);
        return $result->getDeletedCount();
    }

    public function command(array $command): object
    {
        $cursor = $this->manager->executeCommand($this->database, new Command($command));
        return $cursor->toArray()[0] ?? (object) [];
    }

    public function count(string $table, array $filter = []): int
    {
        $result = $this->command([
            'count' => $table,
            'query' => $filter,
        ]);

        return (int) ($result->n ?? 0);
    }

    public function getManager(): Manager
    {
        return $this->manager;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
