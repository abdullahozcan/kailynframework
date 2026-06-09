<?php

namespace Kailyn\Component;

use Kailyn\Container\Container;
use Kailyn\Http\Request;
use Kailyn\Http\Response;
use Kailyn\Template\Engine;
use RuntimeException;

class ComponentManager
{
    private array $components = [];

    public function __construct(
        protected Container $container,
        protected Engine $engine
    ) {}

    public function component(string $name, array $props = []): string
    {
        $instance = $this->make($name, $props);
        $html = $instance->render();

        $this->components[$instance->getComponentId()] = $instance;

        return $html;
    }

    public function make(string $name, array $props = []): Component
    {
        $class = $this->resolveClass($name);
        $instance = $this->container->make($class);

        foreach ($props as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->$key = $value;
            }
        }

        $instance->boot();

        return $instance;
    }

    public function handleUpdate(Request $request): Response
    {
        $body = $request->all();

        $componentName = $body['component'] ?? '';
        $method = $body['method'] ?? '';
        $state = $body['state'] ?? [];

        if (empty($componentName) || empty($method)) {
            return Response::json(['error' => 'Invalid request'], 400);
        }

        $instance = $this->make($componentName);
        $instance->hydrate($state);
        $result = $instance->callMethod($method, $body['params'] ?? []);

        return Response::json([
            'html' => $instance->renderInner(),
            'state' => $instance->getState(),
            'result' => $result,
        ]);
    }

    public function handleInitial(Request $request): Response
    {
        $body = $request->all();
        $componentName = $body['component'] ?? '';

        if (empty($componentName)) {
            return Response::json(['error' => 'Component name required'], 400);
        }

        $instance = $this->make($componentName, $body['props'] ?? []);

        return Response::json([
            'html' => $instance->render(),
            'state' => $instance->getState(),
        ]);
    }

    public function getComponent(string $id): ?Component
    {
        return $this->components[$id] ?? null;
    }

    private function resolveClass(string $name): string
    {
        $class = 'App\\Components\\' . ucfirst($name);

        if (!class_exists($class)) {
            throw new RuntimeException("Component [{$name}] not found");
        }

        return $class;
    }
}
