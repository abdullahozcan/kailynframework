<?php

namespace Kailyn\Component;

use Kailyn\Component\Attributes\Computed;
use Kailyn\Component\Attributes\Reactive;
use Kailyn\Container\Container;
use Kailyn\Template\Engine;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

abstract class Component
{
    private string $componentId;
    private array $reactiveProps = [];
    private array $computedMethods = [];
    private array $computedCache = [];
    protected Container $container;
    protected Engine $engine;

    public function __construct(Container $container, Engine $engine)
    {
        $this->componentId = str_replace('.', '', uniqid('k', true));
        $this->container = $container;
        $this->engine = $engine;

        $this->bootAttributes();
    }

    public function boot(): void {}

    protected function bootAttributes(): void
    {
        $ref = new ReflectionClass($this);

        foreach ($ref->getProperties() as $prop) {
            if (!empty($prop->getAttributes(Reactive::class))) {
                $this->reactiveProps[] = $prop->getName();
            }
        }

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!empty($method->getAttributes(Computed::class))) {
                $this->computedMethods[] = $method->getName();
            }
        }
    }

    public function getComponentId(): string
    {
        return $this->componentId;
    }

    public function getState(): array
    {
        $state = [];

        foreach ($this->reactiveProps as $name) {
            $state[$name] = $this->$name;
        }

        return $state;
    }

    public function hydrate(array $state): void
    {
        foreach ($state as $key => $value) {
            if (in_array($key, $this->reactiveProps, true)) {
                $this->$key = $value;
            }
        }
    }

    public function callMethod(string $method, array $params = []): mixed
    {
        if (!method_exists($this, $method)) {
            throw new \RuntimeException("Method {$method} not found on component " . static::class);
        }

        $ref = new \ReflectionMethod($this, $method);
        $namedParams = [];
        $paramIndex = 0;

        foreach ($ref->getParameters() as $param) {
            if (isset($params[$param->getName()])) {
                $namedParams[$param->getName()] = $params[$param->getName()];
            } elseif (isset($params[$paramIndex])) {
                $namedParams[$param->getName()] = $params[$paramIndex];
            }
            $paramIndex++;
        }

        return $this->container->call([$this, $method], $namedParams);
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (in_array($name, $this->computedMethods, true)) {
            if (!isset($this->computedCache[$name])) {
                $this->computedCache[$name] = $this->$name(...$arguments);
            }
            return $this->computedCache[$name];
        }

        throw new \RuntimeException("Method {$name} not found on component " . static::class);
    }

    public function computed(string $name): mixed
    {
        return $this->$name();
    }

    public function render(): string
    {
        $view = $this->resolveView();
        $html = $this->engine->renderPartial($view, $this->getViewData());

        $state = htmlspecialchars(json_encode($this->getState()), ENT_QUOTES, 'UTF-8');
        $id = $this->componentId;
        $name = $this->getComponentName();

        return "<div k-component=\"{$name}\" k-id=\"{$id}\" k-state='{$state}'>{$html}</div>";
    }

    public function renderInner(): string
    {
        $view = $this->resolveView();
        return $this->engine->renderPartial($view, $this->getViewData());
    }

    protected function getViewData(): array
    {
        $data = [];

        foreach ($this->reactiveProps as $prop) {
            $data[$prop] = $this->$prop;
        }

        foreach ($this->computedMethods as $method) {
            $data[$method] = $this->$method();
        }

        return $data;
    }

    protected function resolveView(): string
    {
        $ref = new ReflectionClass($this);
        $shortName = $ref->getShortName();
        $name = ltrim(preg_replace('/([A-Z])/', '.$1', lcfirst($shortName)), '.');

        return 'components.' . strtolower($name);
    }

    protected function getComponentName(): string
    {
        $ref = new ReflectionClass($this);
        return lcfirst($ref->getShortName());
    }
}
