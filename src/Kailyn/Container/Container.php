<?php

namespace Kailyn\Container;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

class Container
{
    private array $bindings = [];
    private array $resolved = [];

    public function bind(string $abstract, Closure|string|null $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton,
        ];
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->resolved[$abstract] = $instance;
    }

    public function make(string $abstract): mixed
    {
        if (isset($this->resolved[$abstract])) {
            return $this->resolved[$abstract];
        }

        if (!isset($this->bindings[$abstract])) {
            return $this->resolve($abstract);
        }

        $binding = $this->bindings[$abstract];
        $concrete = $binding['concrete'];

        $object = $concrete instanceof Closure
            ? $concrete($this)
            : $this->resolve($concrete);

        if ($binding['singleton']) {
            $this->resolved[$abstract] = $object;
        }

        return $object;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->resolved[$abstract]);
    }

    public function call(callable|array $callable, array $parameters = []): mixed
    {
        if (is_array($callable)) {
            $reflection = new ReflectionMethod($callable[0], $callable[1]);
            $dependencies = $this->resolveParameters($reflection, $parameters);
            return $reflection->invokeArgs($callable[0], $dependencies);
        }

        $reflection = new ReflectionFunction($callable);
        $dependencies = $this->resolveParameters($reflection, $parameters);

        return $reflection->invokeArgs($dependencies);
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    private function resolve(string $class): object
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $dependencies = $this->resolveParameters($constructor);

        return $reflection->newInstanceArgs($dependencies);
    }

    private function resolveParameters(ReflectionMethod|ReflectionFunction $reflection, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $parameters)) {
                $dependencies[] = $parameters[$name];
                continue;
            }

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException("Cannot resolve parameter \${$name} in {$reflection->getName()}");
            }
        }

        return $dependencies;
    }
}
