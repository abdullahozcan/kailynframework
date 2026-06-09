<?php

namespace Kailyn\Database;

use ArrayAccess;
use JsonSerializable;

abstract class MongoModel implements ArrayAccess, JsonSerializable
{
    protected static MongoConnection $connection;
    protected string $collection = '';
    protected string $primaryKey = '_id';
    protected bool $timestamps = true;
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;
    protected bool $wasRecentlyCreated = false;
    protected array $fillable = [];
    protected array $guarded = ['*'];
    protected array $hidden = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function connection(MongoConnection $connection): void
    {
        static::$connection = $connection;
    }

    public static function resolveConnection(): MongoConnection
    {
        if (!isset(static::$connection)) {
            throw new \RuntimeException('MongoDB connection not set');
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
        $result = $instance->newQuery()->find($id);

        if ($result === null) {
            return null;
        }

        return $instance->newFromBuilder($result);
    }

    public static function where(string $column, mixed $operator = null, mixed $value = null): MongoQueryBuilder
    {
        $instance = new static;
        return $instance->newQuery()->where($column, $operator, $value);
    }

    public static function all(): array
    {
        $instance = new static;
        $results = $instance->newQuery()->get();
        return array_map(fn($r) => (new static)->newFromBuilder($r), $results);
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

        $id = $this->attributes[$this->primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $deleted = $this->newQuery()->where($this->primaryKey, '=', $id)->delete();
        $this->exists = false;
        return $deleted > 0;
    }

    public function update(array $attributes): bool
    {
        $this->fill($attributes);
        return $this->save();
    }

    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->hidden as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
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

    public function getCollection(): string
    {
        if (!empty($this->collection)) {
            return $this->collection;
        }

        $class = (new \ReflectionClass($this))->getShortName();
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class)) . 's';
    }

    public function getKey(): mixed
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    protected function newFromBuilder(object $record): static
    {
        $model = new static;
        $model->attributes = (array) $record;
        $model->original = $model->attributes;
        $model->exists = true;
        return $model;
    }

    protected function performInsert(): bool
    {
        $now = new \MongoDB\BSON\UTCDateTime(time() * 1000);

        if ($this->timestamps) {
            $this->attributes['created_at'] = $now;
            $this->attributes['updated_at'] = $now;
        }

        $id = static::resolveConnection()->insert($this->getCollection(), $this->attributes);
        $this->attributes[$this->primaryKey] = $id;
        $this->exists = true;
        $this->original = $this->attributes;

        return true;
    }

    protected function performUpdate(): bool
    {
        if ($this->timestamps) {
            $this->attributes['updated_at'] = new \MongoDB\BSON\UTCDateTime(time() * 1000);
        }

        $id = $this->original[$this->primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $dirty = $this->getDirty();
        unset($dirty[$this->primaryKey]);

        if (empty($dirty)) {
            return true;
        }

        static::resolveConnection()->update(
            $this->getCollection(),
            [$this->primaryKey => $this->normalizeId($id)],
            $dirty
        );

        $this->original = $this->attributes;
        return true;
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

    protected function newQuery(): MongoQueryBuilder
    {
        return static::resolveConnection()->table($this->getCollection());
    }

    protected function normalizeId(mixed $id): mixed
    {
        if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id)) {
            return new \MongoDB\BSON\ObjectId($id);
        }

        return $id;
    }
}
