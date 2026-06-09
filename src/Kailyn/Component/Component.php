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
    protected array $errors = [];

    protected array $watchers = [];
    private array $runtimeWatchers = [];

    public function __construct(Container $container, Engine $engine)
    {
        $this->componentId = str_replace('.', '', uniqid('k', true));
        $this->container = $container;
        $this->engine = $engine;

        $this->bootAttributes();
    }

    // ---- Lifecycle: Initialization ----

    public function mount(array $props = []): void {}

    public function boot(): void {}

    // ---- Lifecycle: Hydration ----

    public function hydrating(): void {}

    public function hydrated(): void {}

    // ---- Lifecycle: Property Updates ----

    public function updating(string $property, mixed $oldValue, mixed $newValue): void {}

    public function updated(string $property, mixed $oldValue, mixed $newValue): void {}

    public function addError(string $key, string $message): void
    {
        $this->errors[$key][] = $message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function resetErrors(): void
    {
        $this->errors = [];
    }

    // ---- Lifecycle: Method Call ----

    public function calling(string $method, array &$params): void {}

    public function called(string $method, mixed $result): void {}

    // ---- Lifecycle: Rendering ----

    public function rendering(): void {}

    public function rendered(string $html): string
    {
        return $html;
    }

    // ---- Watcher System ----

    public function watch(string $property, callable $callback): void
    {
        $this->runtimeWatchers[$property][] = $callback;
    }

    protected function triggerWatchers(string $property, mixed $oldValue, mixed $newValue): void
    {
        // 1. Magic method: updated{Property}
        $magic = 'updated' . ucfirst($property);
        if (method_exists($this, $magic)) {
            $this->$magic($oldValue, $newValue);
        }

        // 2. Declarative watchers from $watchers array
        if (isset($this->watchers[$property])) {
            $callbacks = (array) $this->watchers[$property];
            foreach ($callbacks as $callback) {
                if (method_exists($this, $callback)) {
                    $this->$callback($oldValue, $newValue);
                }
            }
        }

        // 3. Runtime watchers via watch()
        if (isset($this->runtimeWatchers[$property])) {
            foreach ($this->runtimeWatchers[$property] as $callback) {
                $callback($oldValue, $newValue);
            }
        }
    }

    // ---- Core Methods ----

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

    public function setState(array $state): void
    {
        foreach ($state as $key => $value) {
            if (in_array($key, $this->reactiveProps, true)) {
                $this->$key = $value;
            }
        }
    }

    public function hydrate(array $state): void
    {
        $this->hydrating();

        foreach ($state as $key => $value) {
            if (!in_array($key, $this->reactiveProps, true)) {
                continue;
            }

            $oldValue = $this->$key ?? null;

            if ($oldValue === $value) {
                continue;
            }

            // 1. Generic updating hook
            $this->updating($key, $oldValue, $value);

            // 2. Magic method: updating{Property}
            $magicUpdating = 'updating' . ucfirst($key);
            if (method_exists($this, $magicUpdating)) {
                $this->$magicUpdating($oldValue, $value);
            }

            // 3. Apply the value
            $this->$key = $value;

            // 4. Generic updated hook
            $this->updated($key, $oldValue, $value);

            // 5. Watcher chain + magic updated{Property}
            $this->triggerWatchers($key, $oldValue, $value);
        }

        $this->hydrated();
    }

    public function callMethod(string $method, array $params = []): mixed
    {
        if (!method_exists($this, $method)) {
            throw new \RuntimeException("Method {$method} not found on component " . static::class);
        }

        $this->calling($method, $params);

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

        $result = $this->container->call([$this, $method], $namedParams);

        $this->called($method, $result);

        return $result;
    }

    public function render(): string
    {
        $view = $this->resolveView();

        $this->rendering();

        $html = $this->engine->renderPartial($view, $this->getViewData());

        $html = $this->rendered($html);

        $state = htmlspecialchars(json_encode($this->getState()), ENT_QUOTES, 'UTF-8');
        $id = $this->componentId;
        $name = $this->getComponentName();

        return "<div k-component=\"{$name}\" k-id=\"{$id}\" k-state='{$state}'>{$html}</div>";
    }

    public function renderInner(): string
    {
        $view = $this->resolveView();

        $this->rendering();

        $html = $this->engine->renderPartial($view, $this->getViewData());

        return $this->rendered($html);
    }

    public function dispatch(string $event, mixed $payload = null): array
    {
        return [
            'event' => $event,
            'payload' => $payload,
        ];
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

    // ---- Internal ----

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

    protected function getViewData(): array
    {
        $data = [];

        foreach ($this->reactiveProps as $prop) {
            $data[$prop] = $this->$prop;
        }

        foreach ($this->computedMethods as $method) {
            $data[$method] = $this->$method();
        }

        $data['errors'] = $this->errors;

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
