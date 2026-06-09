<?php

namespace Kailyn\Template;

use RuntimeException;

class Engine
{
    protected Compiler $compiler;
    protected array $sections = [];
    protected array $sectionStack = [];
    protected ?string $layout = null;
    protected array $shared = [];
    protected string $cachePath;
    protected string $viewPath;

    public function __construct(string $viewPath, string $cachePath)
    {
        $this->viewPath = rtrim($viewPath, '/');
        $this->cachePath = rtrim($cachePath, '/');
        $this->compiler = new Compiler;
    }

    public function render(string $view, array $data = []): string
    {
        $this->validateViewName($view);
        $this->sections = [];
        $this->sectionStack = [];
        $this->layout = null;

        return $this->renderRaw($view, $data);
    }

    public function renderPartial(string $view, array $data = []): string
    {
        $this->validateViewName($view);
        $sections = $this->sections;
        $stack = $this->sectionStack;
        $layout = $this->layout;

        $result = $this->renderRaw($view, $data);

        $this->sections = $sections;
        $this->sectionStack = $stack;
        $this->layout = $layout;

        return $result;
    }

    protected function renderRaw(string $view, array $data): string
    {
        $compiled = $this->getCompiled($view);
        $__env = $this;

        extract($data, EXTR_SKIP);

        ob_start();
        require $compiled;
        $content = ob_get_clean();

        if ($this->layout !== null) {
            $layoutData = array_merge($data, ['__env' => $this]);
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['@parent'] = $content;
            return $this->renderLayout($layout, $layoutData);
        }

        return $content;
    }

    protected function renderLayout(string $layout, array $data): string
    {
        $this->validateViewName($layout);
        $compiled = $this->getCompiled($layout);
        $__env = $this;

        extract($data, EXTR_SKIP);

        ob_start();
        require $compiled;
        return ob_get_clean();
    }

    public function startLayout(string $name): void
    {
        $this->layout = $name;
    }

    public function startSection(string $name): void
    {
        ob_start();
        $this->sectionStack[] = $name;
    }

    public function setSection(string $name, mixed $value): void
    {
        $this->sections[$name] = $value;
    }

    public function stopSection(bool $overwrite = false): void
    {
        if (empty($this->sectionStack)) {
            throw new RuntimeException('No section started');
        }

        $name = array_pop($this->sectionStack);
        $content = ob_get_clean();

        if ($overwrite || !isset($this->sections[$name])) {
            $this->sections[$name] = $content;
        } else {
            $this->sections[$name] .= $content;
        }
    }

    public function showSection(): void
    {
        if (empty($this->sectionStack)) {
            throw new RuntimeException('No section started');
        }

        $name = end($this->sectionStack);
        $this->stopSection();
        echo $this->sections[$name];
    }

    public function yieldContent(string $name, mixed $default = null): string
    {
        if (isset($this->sections[$name])) {
            return $this->sections[$name];
        }

        if ($default !== null) {
            return $default;
        }

        return '';
    }

    public function include(string $view, array $data = []): string
    {
        $this->validateViewName($view);
        return $this->render($view, array_merge($this->shared, $data));
    }

    public function includeIf(string $view, array $data = []): string
    {
        $this->validateViewName($view);
        $path = $this->findView($view);

        if ($path === null) {
            return '';
        }

        return $this->render($view, array_merge($this->shared, $data));
    }

    public function exists(string $view): bool
    {
        $this->validateViewName($view);
        return $this->findView($view) !== null;
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function getShared(): array
    {
        return $this->shared;
    }

    public function getCompiler(): Compiler
    {
        return $this->compiler;
    }

    protected function getCompiled(string $view): string
    {
        $path = $this->findView($view);

        if ($path === null) {
            throw new RuntimeException("View [{$view}] not found");
        }

        $realRoot = realpath($this->viewPath);
        $realPath = realpath($path);

        if ($realRoot === false || $realPath === false || !str_starts_with($realPath, $realRoot)) {
            throw new RuntimeException("View [{$view}] not accessible");
        }

        $cacheFile = $this->cachePath . '/' . md5($view) . '.php';

        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }

        if (!$this->isExpired($path, $cacheFile)) {
            return $cacheFile;
        }

        $source = file_get_contents($path);
        $compiled = $this->compiler->compile($source);

        file_put_contents($cacheFile, $compiled, LOCK_EX);

        return $cacheFile;
    }

    protected function validateViewName(string $view): void
    {
        if (str_contains($view, '..') || str_contains($view, '/') || str_contains($view, '\\')) {
            throw new RuntimeException("Invalid view name: {$view}");
        }
    }

    protected function findView(string $view): ?string
    {
        $parts = explode('.', $view);
        $path = $this->viewPath . '/' . implode('/', $parts) . '.html';

        if (file_exists($path)) {
            return $path;
        }

        $path = $this->viewPath . '/' . implode('/', $parts) . '.php';

        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    protected function isExpired(string $path, string $compiled): bool
    {
        if (!file_exists($compiled)) {
            return true;
        }

        return filemtime($path) >= filemtime($compiled);
    }
}
