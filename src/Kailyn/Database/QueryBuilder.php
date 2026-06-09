<?php

namespace Kailyn\Database;

class QueryBuilder
{
    protected Connection $connection;
    protected string $table;
    protected array $columns = ['*'];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $joins = [];
    protected array $orders = [];
    protected array $havings = [];
    protected array $havingBindings = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected ?string $lock = null;
    protected array $groups = [];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function select(mixed ...$columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function addSelect(mixed ...$columns): static
    {
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => $not ? 'not-in' : 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    public function whereNull(string $column, string $boolean = 'and'): static
    {
        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        $this->wheres[] = [
            'type' => 'notnull',
            'column' => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $this->wheres[] = [
            'type' => $not ? 'not-between' : 'between',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }

    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    public function whereColumn(string $first, string $operator, ?string $second = null, string $boolean = 'and'): static
    {
        if ($second === null) {
            $second = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'column',
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => $boolean,
        ];

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'inner'): static
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => strtoupper($direction),
        ];

        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function inRandomOrder(): static
    {
        $this->orders[] = [
            'type' => 'raw',
            'sql' => 'RANDOM()',
        ];

        return $this;
    }

    public function groupBy(mixed ...$groups): static
    {
        $this->groups = array_merge($this->groups, $groups);
        return $this;
    }

    public function having(string $column, string $operator, mixed $value, string $boolean = 'and'): static
    {
        $this->havings[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->havingBindings[] = $value;

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function take(int $limit): static
    {
        return $this->limit($limit);
    }

    public function skip(int $offset): static
    {
        return $this->offset($offset);
    }

    public function lock(bool $lock = true): static
    {
        $this->lock = $lock ? 'FOR UPDATE' : null;
        return $this;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->table;

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $type = strtoupper($join['type']);
                $sql .= " {$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
            }
        }

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        if (!empty($this->groups)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }

        if (!empty($this->havings)) {
            $sql .= ' HAVING ' . $this->buildHavingClause();
        }

        if (!empty($this->orders)) {
            $orders = [];
            foreach ($this->orders as $order) {
                if (isset($order['type']) && $order['type'] === 'raw') {
                    $orders[] = $order['sql'];
                } else {
                    $orders[] = "{$order['column']} {$order['direction']}";
                }
            }
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        if ($this->lock !== null) {
            $sql .= ' ' . $this->lock;
        }

        return $sql;
    }

    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->bindings);
    }

    public function first(): ?object
    {
        $this->limit = 1;
        $result = $this->get();
        return $result[0] ?? null;
    }

    public function find(mixed $id, string $column = 'id'): ?object
    {
        return $this->where($column, '=', $id)->first();
    }

    public function value(string $column): mixed
    {
        $result = $this->first();
        return $result ? $result->$column : null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get();
        $plucked = [];

        foreach ($results as $result) {
            if ($key !== null) {
                $plucked[$result->$key] = $result->$column;
            } else {
                $plucked[] = $result->$column;
            }
        }

        return $plucked;
    }

    public function count(string $column = '*'): int
    {
        $col = $this->validateColumn($column);
        $this->columns = ["COUNT({$col}) as aggregate"];
        $result = $this->first();
        return (int) ($result->aggregate ?? 0);
    }

    public function avg(string $column): float
    {
        $col = $this->validateColumn($column);
        $this->columns = ["AVG({$col}) as aggregate"];
        $result = $this->first();
        return (float) ($result->aggregate ?? 0);
    }

    public function sum(string $column): float
    {
        $col = $this->validateColumn($column);
        $this->columns = ["SUM({$col}) as aggregate"];
        $result = $this->first();
        return (float) ($result->aggregate ?? 0);
    }

    public function min(string $column): float
    {
        $col = $this->validateColumn($column);
        $this->columns = ["MIN({$col}) as aggregate"];
        $result = $this->first();
        return (float) ($result->aggregate ?? 0);
    }

    public function max(string $column): float
    {
        $col = $this->validateColumn($column);
        $this->columns = ["MAX({$col}) as aggregate"];
        $result = $this->first();
        return (float) ($result->aggregate ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = array_keys(reset($values));
        $columnList = implode(', ', $columns);
        $placeholders = '(:' . implode(', :', $columns) . ')';
        $sql = "INSERT INTO {$this->table} ({$columnList}) VALUES {$placeholders}";

        $bindings = [];
        foreach ($values as $row) {
            foreach ($row as $key => $value) {
                $bindings[$key] = $value;
            }
        }

        return $this->connection->insert($sql, $bindings);
    }

    public function insertGetId(array $values, string $sequence = null): string
    {
        $this->insert($values);
        return $this->connection->lastInsertId();
    }

    public function update(array $values): int
    {
        $sets = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $sets[] = "{$column} = :{$column}";
            $bindings[$column] = $value;
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets);

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
            $bindings = array_merge($bindings, $this->getWhereBindings());
        }

        return $this->connection->update($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table;

        if (!empty($this->wheres)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        return $this->connection->delete($sql, $this->bindings);
    }

    public function truncate(): bool
    {
        return $this->connection->statement("TRUNCATE TABLE {$this->table}");
    }

    public function chunk(int $size, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->forPage($page, $size)->get();

            if (count($results) === 0) {
                return true;
            }

            $callback($results, $page);

            $page++;
        } while (count($results) === $size);

        return true;
    }

    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    public function paginate(int $perPage = 15, string $pageName = 'page'): array
    {
        $page = max(1, (int) ($_GET[$pageName] ?? 1));
        $total = $this->count();

        $this->forPage($page, $perPage);
        $items = $this->get();

        return [
            'items' => $items,
            'total' => $total,
            'perPage' => $perPage,
            'currentPage' => $page,
            'lastPage' => (int) ceil($total / $perPage),
            'from' => (($page - 1) * $perPage) + 1,
            'to' => min($page * $perPage, $total),
        ];
    }

    public function dd(): void
    {
        $sql = $this->toSql();
        $bindings = $this->bindings;

        dump(compact('sql', 'bindings'));

        die();
    }

    public function dump(): static
    {
        $sql = $this->toSql();
        $bindings = $this->bindings;

        dump(compact('sql', 'bindings'));

        return $this;
    }

    public function newQuery(): static
    {
        $builder = new static($this->connection);
        if (!empty($this->table)) {
            $builder->table($this->table);
        }
        return $builder;
    }

    protected function buildWhereClause(): string
    {
        $parts = [];
        $first = true;

        foreach ($this->wheres as $where) {
            $boolean = $first ? '' : strtoupper($where['boolean']) . ' ';

            $part = match ($where['type']) {
                'basic' => "{$where['column']} {$where['operator']} ?",
                'in' => "{$where['column']} IN (" . implode(', ', array_fill(0, count($where['values']), '?')) . ')',
                'not-in' => "{$where['column']} NOT IN (" . implode(', ', array_fill(0, count($where['values']), '?')) . ')',
                'null' => "{$where['column']} IS NULL",
                'notnull' => "{$where['column']} IS NOT NULL",
                'between' => "{$where['column']} BETWEEN ? AND ?",
                'not-between' => "{$where['column']} NOT BETWEEN ? AND ?",
                'column' => "{$where['first']} {$where['operator']} {$where['second']}",
                default => '',
            };

            $parts[] = $boolean . $part;
            $first = false;
        }

        return implode(' ', $parts);
    }

    protected function buildHavingClause(): string
    {
        $parts = [];
        $first = true;

        foreach ($this->havings as $having) {
            $boolean = $first ? '' : strtoupper($having['boolean']) . ' ';
            $parts[] = $boolean . "{$having['column']} {$having['operator']} ?";
            $first = false;
        }

        return implode(' ', $parts);
    }

    protected function validateColumn(string $column): string
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_.*]*$/', $column)) {
            return $column;
        }
        throw new \RuntimeException("Invalid column name: {$column}");
    }

    protected function getWhereBindings(): array
    {
        $bindings = [];

        foreach ($this->wheres as $where) {
            if (in_array($where['type'], ['basic', 'column']) && isset($where['value'])) {
                $bindings[] = $where['value'];
            } elseif (in_array($where['type'], ['in', 'not-in', 'between', 'not-between'])) {
                $bindings = array_merge($bindings, $where['values']);
            }
        }

        return $bindings;
    }
}
