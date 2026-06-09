<?php

namespace Kailyn\Database;

class MongoQueryBuilder
{
    protected MongoConnection $connection;
    protected string $table;
    protected array $wheres = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $columns = [];

    public function __construct(MongoConnection $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        if ($value === null && $operator !== null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [$column => match ($operator) {
            '=' => $value,
            '!=' => ['$ne' => $value],
            '>' => ['$gt' => $value],
            '>=' => ['$gte' => $value],
            '<' => ['$lt' => $value],
            '<=' => ['$lte' => $value],
            'like' => ['$regex' => str_replace('%', '.*', preg_quote($value, '/')), '$options' => 'i'],
            'in' => ['$in' => (array) $value],
            'not in' => ['$nin' => (array) $value],
            default => $value,
        }];

        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        return $this->where($column, 'in', $values);
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = [$column => null];
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = [$column => ['$ne' => null]];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[$column] = $direction === 'desc' ? -1 : 1;
        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
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

    public function select(mixed ...$columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function get(): array
    {
        $filter = $this->buildFilter();
        $options = [];

        if (!empty($this->orders)) {
            $options['sort'] = $this->orders;
        }

        if ($this->limit !== null) {
            $options['limit'] = $this->limit;
        }

        if ($this->offset !== null) {
            $options['skip'] = $this->offset;
        }

        if (!empty($this->columns)) {
            $options['projection'] = array_fill_keys($this->columns, 1);
        }

        return $this->connection->find($this->table, $filter, $options);
    }

    public function first(): ?object
    {
        $this->limit = 1;
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function find(mixed $id): ?object
    {
        return $this->connection->findOne($this->table, ['_id' => $this->normalizeId($id)]);
    }

    public function value(string $column): mixed
    {
        $result = $this->first();
        $result = (array) $result;
        return $result[$column] ?? null;
    }

    public function count(): int
    {
        return $this->connection->count($this->table, $this->buildFilter());
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function insert(array $values): bool
    {
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        foreach ($values as $doc) {
            $this->connection->insert($this->table, $doc);
        }

        return true;
    }

    public function update(array $values): int
    {
        $filter = $this->buildFilter();
        return $this->connection->update($this->table, $filter, $values);
    }

    public function delete(): int
    {
        $filter = $this->buildFilter();
        return $this->connection->delete($this->table, $filter, $this->limit !== null);
    }

    public function truncate(): bool
    {
        $this->connection->delete($this->table, [], false);
        return true;
    }

    protected function buildFilter(): array
    {
        if (empty($this->wheres)) {
            return [];
        }

        $filter = [];

        foreach ($this->wheres as $where) {
            foreach ($where as $key => $value) {
                if (isset($filter[$key]) && is_array($filter[$key])) {
                    foreach ((array) $value as $op => $val) {
                        if (str_starts_with($op, '$')) {
                            $filter[$key][$op] = $val;
                        }
                    }
                } else {
                    $filter[$key] = $value;
                }
            }
        }

        return $filter;
    }

    protected function normalizeId(mixed $id): mixed
    {
        if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id)) {
            return new \MongoDB\BSON\ObjectId($id);
        }

        return $id;
    }
}
