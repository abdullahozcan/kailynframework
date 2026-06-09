<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $signature = 'make:controller {name} {--resource}';
    protected string $description = 'Create a new controller class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $resource = $this->option('resource');
        $path = $this->getApplication()->getKailynApp()->basePath('app/Controllers/' . $name . '.php');

        if (file_exists($path)) {
            $this->error("Controller [{$name}] already exists!");
            return self::FAILURE;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stub = $resource ? $this->getResourceStub() : $this->getStub();
        $content = str_replace('{{ class }}', $name, $stub);

        file_put_contents($path, $content);

        $this->info("Controller [{$name}] created successfully.");
        $this->line("  Type: " . ($resource ? 'Resource' : 'Simple'));
        $this->line("  Path: {$path}");

        return self::SUCCESS;
    }

    protected function getStub(): string
    {
        return <<<'PHP'
<?php

namespace App\Controllers;

use Kailyn\Http\Request;
use Kailyn\Http\Response;

class {{ class }}
{
    public function index(Request $request): Response
    {
        return Response::json(['message' => 'Hello from ' . __CLASS__]);
    }
}

PHP;
    }

    protected function getResourceStub(): string
    {
        return <<<'PHP'
<?php

namespace App\Controllers;

use Kailyn\Http\Request;
use Kailyn\Http\Response;

class {{ class }}
{
    public function index(Request $request): Response
    {
        return Response::json(['data' => []]);
    }

    public function show(Request $request, string $id): Response
    {
        return Response::json(['data' => []]);
    }

    public function store(Request $request): Response
    {
        return Response::json(['message' => 'Created'], 201);
    }

    public function update(Request $request, string $id): Response
    {
        return Response::json(['message' => 'Updated']);
    }

    public function destroy(Request $request, string $id): Response
    {
        return Response::noContent();
    }
}

PHP;
    }
}
