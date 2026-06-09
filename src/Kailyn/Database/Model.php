<?php

namespace Kailyn\Database;

use ArrayAccess;
use JsonSerializable;

abstract class Model implements ArrayAccess, JsonSerializable
{
    protected static Connection $connection;
    protected static array $resolved = [];

    protected string $table = '';
    protected string $primaryKey = 'id';
    protected string $keyType = 'int';
    protected bool $incrementing = true;
    protected bool $timestamps = true;
    protected array $attributes = [];
    protected array $original = [];
    protected array $changes = [];
    protected bool $exists = false;
    protected bool $wasRecentlyCreated = false;
    protected array $fillable = [];
    protected array $guarded = ['*'];
    protected array $appends = [];
    protected array $hidden = [];
    protected array $relations = [];
    protected array $with = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function connection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    public static function resolveConnection(): Connection
    {
        if (!isset(static::$connection)) {
            throw new \RuntimeException('Database connection not set');
        }

        return static::$connection;
    }

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->fillable)) {
            return true;
        }

        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->fillable) && $key !== $this->primaryKey;
    }

    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->guarded);
    }

    public static function find(mixed $id): ?static
    {
        $instance = new static;
        $result = $instance->newQuery()->find($id, $instance->getTable() . '.' . $instance->primaryKey);

        if ($result === null) {
            return null;
        }

        return $instance->newFromBuilder($result);
    }

    public static function where(string $column, mixed $operator = null, mixed $value = null): QueryBuilder
    {
        $instance = new static;
        return $instance->newQuery()->where($column, $operator, $value);
    }

    public static function orWhere(string $column, mixed $operator = null, mixed $value = null): QueryBuilder
    {
        $instance = new static;
        return $instance->newQuery()->orWhere($column, $operator, $value);
    }

    public static function whereIn(string $column, array $values): QueryBuilder
    {
        $instance = new static;
        return $instance->newQuery()->whereIn($column, $values);
    }

    public static function all(): array
    {
        $instance = new static;
        $results = $instance->newQuery()->get();
        $models = [];

        foreach ($results as $result) {
            $model = (new static)->newFromBuilder($result);
            $models[] = $model;
        }

        return $models;
    }

    public static function count(): int
    {
        $instance = new static;
        return $instance->newQuery()->count();
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $deleted = $this->newQuery()
            ->where($this->primaryKey, '=', $this->attributes[$this->primaryKey])
            ->delete();

        $this->exists = false;

        return $deleted > 0;
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public function fresh(): ?static
    {
        if (!$this->exists || !isset($this->attributes[$this->primaryKey])) {
            return null;
        }

        return static::find($this->attributes[$this->primaryKey]);
    }

    public function refresh(): static
    {
        $fresh = $this->fresh();

        if ($fresh !== null) {
            $this->attributes = $fresh->attributes;
            $this->original = $fresh->original;
            $this->relations = $fresh->relations;
        }

        return $this;
    }

    public function load(string ...$relations): static
    {
        if (empty($relations)) {
            $relations = $this->with;
        }

        foreach ($relations as $relation) {
            $this->loadRelation($relation);
        }

        return $this;
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->hidden as $key) {
            unset($attributes[$key]);
        }

        foreach ($this->appends as $append) {
            if (method_exists($this, $append)) {
                $attributes[$append] = $this->$append();
            }
        }

        foreach ($this->relations as $key => $value) {
            if ($value instanceof Model) {
                $attributes[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $attributes[$key] = array_map(fn($m) => $m instanceof Model ? $m->toArray() : $m, $value);
            } else {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __get(string $key): mixed
    {
        if (isset($this->relations[$key])) {
            return $this->relations[$key];
        }

        if (method_exists($this, $key)) {
            return $this->loadRelation($key);
        }

        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        if (isset($this->relations[$key])) {
            return true;
        }

        return isset($this->attributes[$key]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    public function getTable(): string
    {
        if (!empty($this->table)) {
            return $this->table;
        }

        $class = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function wasRecentlyCreated(): bool
    {
        return $this->wasRecentlyCreated;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getOriginal(): array
    {
        return $this->original;
    }

    public function hasGetMutator(string $key): bool
    {
        return method_exists($this, 'get' . str_replace('_', '', ucwords($key, '_')) . 'Attribute');
    }

    public function hasSetMutator(string $key): bool
    {
        return method_exists($this, 'set' . str_replace('_', '', ucwords($key, '_')) . 'Attribute');
    }

    // ---- Relationships ----

    protected function hasOne(string $related, string $foreignKey = null, string $localKey = null): mixed
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->primaryKey;

        return $instance->where($foreignKey, '=', $this->attributes[$localKey] ?? null)->first();
    }

    protected function hasMany(string $related, string $foreignKey = null, string $localKey = null): array
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->primaryKey;

        return $instance->where($foreignKey, '=', $this->attributes[$localKey] ?? null)->get();
    }

    protected function belongsTo(string $related, string $foreignKey = null, string $ownerKey = null): mixed
    {
        $instance = new $related;
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $ownerKey = $ownerKey ?: (new $related)->getPrimaryKey();

        return $instance->where($ownerKey, '=', $this->attributes[$foreignKey] ?? null)->first();
    }

    protected function belongsToMany(string $related, string $table = null, string $foreignPivotKey = null, string $relatedPivotKey = null): array
    {
        $instance = new $related;
        $table = $table ?: $this->getPivotTableName($related);
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: (new $related)->getForeignKey();

        $localKey = $this->attributes[$this->primaryKey] ?? null;

        return (new static)
            ->newQuery()
            ->select($instance->getTable() . '.*')
            ->join($table, "{$table}.{$relatedPivotKey}", '=', $instance->getTable() . '.' . $instance->getPrimaryKey())
            ->where("{$table}.{$foreignPivotKey}", '=', $localKey)
            ->get();
    }

    protected function getForeignKey(): string
    {
        $class = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . '_' . $this->primaryKey;
    }

    protected function getPivotTableName(string $related): string
    {
        $models = [
            (new \ReflectionClass($this))->getShortName(),
            (new \ReflectionClass(new $related))->getShortName(),
        ];

        sort($models);

        return strtolower(implode('_', $models));
    }

    protected function loadRelation(string $relation): mixed
    {
        if (method_exists($this, $relation)) {
            $this->relations[$relation] = $this->$relation();
            return $this->relations[$relation];
        }

        return null;
    }

    // ---- Internals ----

    protected function newFromBuilder(object $record): static
    {
        $model = new static;
        $model->attributes = (array) $record;
        $model->original = $model->attributes;
        $model->exists = true;

        if (!empty($this->with)) {
            $model->load();
        }

        return $model;
    }

    protected function performInsert(): bool
    {
        if ($this->timestamps) {
            $this->attributes['created_at'] = date('Y-m-d H:i:s');
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $query = $this->newQuery();
        $inserted = $query->insert($this->attributes);

        if ($inserted && $this->incrementing) {
            $this->attributes[$this->primaryKey] = static::resolveConnection()->lastInsertId();
        }

        $this->exists = true;
        $this->original = $this->attributes;

        return $inserted;
    }

    protected function performUpdate(): bool
    {
        if ($this->timestamps) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }

        $dirty = $this->getDirty();

        if (empty($dirty)) {
            return true;
        }

        $updated = $this->newQuery()
            ->where($this->primaryKey, '=', $this->original[$this->primaryKey])
            ->update($dirty);

        $this->original = $this->attributes;

        return $updated > 0;
    }

    protected function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    protected function newQuery(): QueryBuilder
    {
        $builder = new QueryBuilder(static::resolveConnection());
        $builder->table($this->getTable());
        return $builder;
    }
}
