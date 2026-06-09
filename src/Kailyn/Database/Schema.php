<?php

namespace Kailyn\Database;

class Schema
{
    protected Connection $connection;
    protected string $table;
    protected array $columns = [];
    protected array $commands = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public static function create(string $table, callable $callback, string $connection = 'default'): void
    {
        $schema = new static(static::resolveConnection($connection));
        $schema->table = $table;
        $callback($schema);
        $schema->executeCreate();
    }

    public static function table(string $table, callable $callback, string $connection = 'default'): void
    {
        $schema = new static(static::resolveConnection($connection));
        $schema->table = $table;
        $callback($schema);
        $schema->executeAlter();
    }

    public static function drop(string $table, string $connection = 'default'): void
    {
        $db = static::resolveConnection($connection);
        $db->statement("DROP TABLE IF EXISTS {$table}");
    }

    public static function dropIfExists(string $table, string $connection = 'default'): void
    {
        static::drop($table, $connection);
    }

    public static function rename(string $from, string $to, string $connection = 'default'): void
    {
        $db = static::resolveConnection($connection);
        $db->statement("RENAME TABLE {$from} TO {$to}");
    }

    public static function hasTable(string $table, string $connection = 'default'): bool
    {
        $db = static::resolveConnection($connection);

        try {
            $db->select("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ---- Column Types ----

    public function id(string $name = 'id'): static
    {
        return $this->addColumn('bigIncrements', $name);
    }

    public function increments(string $name): static
    {
        return $this->addColumn('increments', $name);
    }

    public function bigIncrements(string $name): static
    {
        return $this->addColumn('bigIncrements', $name);
    }

    public function string(string $name, int $length = 255): static
    {
        return $this->addColumn('string', $name, compact('length'));
    }

    public function text(string $name): static
    {
        return $this->addColumn('text', $name);
    }

    public function longText(string $name): static
    {
        return $this->addColumn('longText', $name);
    }

    public function integer(string $name, bool $unsigned = false): static
    {
        return $this->addColumn('integer', $name, compact('unsigned'));
    }

    public function tinyInteger(string $name, bool $unsigned = false): static
    {
        return $this->addColumn('tinyInteger', $name, compact('unsigned'));
    }

    public function bigInteger(string $name, bool $unsigned = false): static
    {
        return $this->addColumn('bigInteger', $name, compact('unsigned'));
    }

    public function boolean(string $name): static
    {
        return $this->addColumn('boolean', $name);
    }

    public function date(string $name): static
    {
        return $this->addColumn('date', $name);
    }

    public function dateTime(string $name): static
    {
        return $this->addColumn('dateTime', $name);
    }

    public function timestamp(string $name): static
    {
        return $this->addColumn('timestamp', $name);
    }

    public function timestamps(): static
    {
        $this->nullableTimestamp('created_at');
        $this->nullableTimestamp('updated_at');
        return $this;
    }

    public function nullableTimestamp(string $name): static
    {
        return $this->addColumn('timestamp', $name, ['nullable' => true]);
    }

    public function float(string $name, int $precision = 8, int $scale = 2): static
    {
        return $this->addColumn('float', $name, compact('precision', 'scale'));
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): static
    {
        return $this->addColumn('decimal', $name, compact('precision', 'scale'));
    }

    public function json(string $name): static
    {
        return $this->addColumn('json', $name);
    }

    public function jsonb(string $name): static
    {
        return $this->addColumn('jsonb', $name);
    }

    public function softDeletes(string $column = 'deleted_at'): static
    {
        return $this->addColumn('timestamp', $column, ['nullable' => true]);
    }

    public function rememberToken(): static
    {
        return $this->addColumn('string', 'remember_token', ['length' => 100, 'nullable' => true]);
    }

    public function foreignId(string $name): static
    {
        return $this->addColumn('bigInteger', $name, ['unsigned' => true]);
    }

    public function foreignIdFor(string $related, string $column = null): static
    {
        $instance = new $related;
        $column = $column ?: $instance->getForeignKey();
        return $this->foreignId($column);
    }

    // ---- Modifiers ----

    protected array $currentColumn = [];

    public function nullable(): static
    {
        $this->applyModifier('nullable', true);
        return $this;
    }

    public function unique(): static
    {
        $this->applyModifier('unique', true);
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->applyModifier('default', $value);
        return $this;
    }

    public function unsigned(): static
    {
        $this->applyModifier('unsigned', true);
        return $this;
    }

    public function primary(): static
    {
        $this->applyModifier('primary', true);
        return $this;
    }

    public function after(string $column): static
    {
        $this->applyModifier('after', $column);
        return $this;
    }

    // ---- Constraints ----

    protected array $foreignKeys = [];

    public function foreign(string $column): ForeignKeyConstraint
    {
        $constraint = new ForeignKeyConstraint($column, $this);
        $this->foreignKeys[] = $constraint;
        return $constraint;
    }

    public function indexed(array $columns = null): void
    {
        $columns ??= [$this->currentColumn['name'] ?? '_'];
        $this->commands[] = ['type' => 'index', 'columns' => $columns];
    }

    // ---- Internals ----

    protected function addColumn(string $type, string $name, array $modifiers = []): static
    {
        $this->currentColumn = array_merge(['name' => $name, 'type' => $type], $modifiers);
        $this->columns[] = $this->currentColumn;
        return $this;
    }

    protected function applyModifier(string $key, mixed $value): void
    {
        if (!empty($this->columns)) {
            $idx = count($this->columns) - 1;
            $this->columns[$idx][$key] = $value;
            $this->currentColumn[$key] = $value;
        }
    }

    protected function executeCreate(): void
    {
        $sql = $this->compileCreate();

        try {
            $this->connection->statement($sql);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Schema create failed: " . $e->getMessage());
        }
    }

    protected function executeAlter(): void
    {
        $statements = $this->compileAlter();

        foreach ($statements as $sql) {
            try {
                $this->connection->statement($sql);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Schema alter failed: " . $e->getMessage());
            }
        }
    }

    protected function compileCreate(): string
    {
        $columns = [];
        $primaryKey = null;

        foreach ($this->columns as $column) {
            $columns[] = $this->compileColumn($column);

            if (in_array($column['type'], ['increments', 'bigIncrements'])) {
                $primaryKey = $column['name'];
            }
        }

        $columnSql = implode(",\n    ", $columns);

        if ($primaryKey !== null) {
            $columnSql .= ",\n    PRIMARY KEY ({$primaryKey})";
        }

        $table = $this->connection->getTablePrefix() . $this->table;

        return "CREATE TABLE {$table} (\n    {$columnSql}\n)";
    }

    protected function compileAlter(): array
    {
        $statements = [];
        $table = $this->connection->getTablePrefix() . $this->table;

        foreach ($this->columns as $column) {
            $colDef = $this->compileColumn($column);
            $statements[] = "ALTER TABLE {$table} ADD {$colDef}";
        }

        foreach ($this->commands as $command) {
            if ($command['type'] === 'index') {
                $cols = implode(', ', $command['columns']);
                $statements[] = "ALTER TABLE {$table} ADD INDEX ({$cols})";
            }
        }

        foreach ($this->foreignKeys as $fk) {
            $statements[] = $fk->compile($table);
        }

        return $statements;
    }

    protected function compileColumn(array $column): string
    {
        $typeMap = [
            'increments' => 'INT AUTO_INCREMENT',
            'bigIncrements' => 'BIGINT AUTO_INCREMENT',
            'string' => 'VARCHAR(' . ($column['length'] ?? 255) . ')',
            'text' => 'TEXT',
            'longText' => 'LONGTEXT',
            'integer' => 'INT' . ($column['unsigned'] ?? false ? ' UNSIGNED' : ''),
            'tinyInteger' => 'TINYINT' . ($column['unsigned'] ?? false ? ' UNSIGNED' : ''),
            'bigInteger' => 'BIGINT' . ($column['unsigned'] ?? false ? ' UNSIGNED' : ''),
            'boolean' => 'TINYINT(1)',
            'date' => 'DATE',
            'dateTime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'float' => "FLOAT({$column['precision']}, {$column['scale']})",
            'decimal' => "DECIMAL({$column['precision']}, {$column['scale']})",
            'json' => 'JSON',
            'jsonb' => 'JSON',
        ];

        $type = $typeMap[$column['type']] ?? 'VARCHAR(255)';

        if ($column['type'] === 'string' && isset($column['length'])) {
            $type = "VARCHAR({$column['length']})";
        }

        $sql = "{$column['name']} {$type}";

        if (($column['nullable'] ?? false) || in_array($column['type'], ['timestamp'])) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if (array_key_exists('default', $column)) {
            $default = $column['default'];
            if (is_string($default)) {
                $default = "'" . addslashes($default) . "'";
            } elseif ($default === null) {
                $default = 'NULL';
            }
            $sql .= " DEFAULT {$default}";
        }

        if ($column['unsigned'] ?? false) {
            $sql .= ' UNSIGNED';
        }

        if ($column['unique'] ?? false) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    protected static function resolveConnection(string $name): Connection
    {
        $config = \Kailyn\Config\Config::class;

        if (class_exists($config)) {
            $app = \Kailyn\Foundation\Application::class;
            if (class_exists($app)) {
                $container = app();
                if ($container && $container instanceof \Kailyn\Container\Container) {
                    $cnf = $container->make(\Kailyn\Config\Config::class);
                    $dbConfig = $cnf->get("database.connections.{$name}", $cnf->get('database.connections.' . $cnf->get('database.default')));
                    return new Connection($dbConfig, $name);
                }
            }
        }

        throw new \RuntimeException("Cannot resolve database connection '{$name}'");
    }
}

class ForeignKeyConstraint
{
    protected string $column;
    protected Schema $schema;
    protected ?string $references = null;
    protected ?string $on = null;
    protected ?string $onDelete = null;
    protected ?string $onUpdate = null;

    public function __construct(string $column, Schema $schema)
    {
        $this->column = $column;
        $this->schema = $schema;
    }

    public function references(string $column): static
    {
        $this->references = $column;
        return $this;
    }

    public function on(string $table): static
    {
        $this->on = $table;
        return $this;
    }

    public function onDelete(string $action): static
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    public function onUpdate(string $action): static
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    public function cascadeOnDelete(): static
    {
        return $this->onDelete('CASCADE');
    }

    public function cascadeOnUpdate(): static
    {
        return $this->onUpdate('CASCADE');
    }

    public function restrictOnDelete(): static
    {
        return $this->onDelete('RESTRICT');
    }

    public function compile(string $table): string
    {
        $sql = "ALTER TABLE {$table} ADD CONSTRAINT fk_{$table}_{$this->column}";
        $sql .= " FOREIGN KEY ({$this->column}) REFERENCES {$this->on}({$this->references})";

        if ($this->onDelete) {
            $sql .= " ON DELETE {$this->onDelete}";
        }

        if ($this->onUpdate) {
            $sql .= " ON UPDATE {$this->onUpdate}";
        }

        return $sql;
    }
}
