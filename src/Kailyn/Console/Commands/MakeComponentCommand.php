<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;

class MakeComponentCommand extends Command
{
    protected string $signature = 'make:component {name}';
    protected string $description = 'Create a new reactive component';

    public function handle(): int
    {
        $name = $this->argument('name');
        $className = ucfirst($name);
        $viewName = strtolower(preg_replace('/(?<!^)[A-Z]/', '.$0', $className));

        $basePath = $this->getApplication()->getKailynApp()->basePath();

        $componentPath = $basePath . '/app/Components/' . $className . '.php';
        $viewPath = $basePath . '/app/Views/components/' . str_replace('.', '/', $viewName) . '.html';

        if (file_exists($componentPath)) {
            $this->error("Component [{$name}] already exists!");
            return self::FAILURE;
        }

        $dir = dirname($componentPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $dir = dirname($viewPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $componentStub = str_replace(
            ['{{ class }}', '{{ name }}', '{{ view }}'],
            [$className, $name, $viewName],
            $this->getComponentStub()
        );

        $viewStub = $this->getViewStub();

        file_put_contents($componentPath, $componentStub);
        file_put_contents($viewPath, $viewStub);

        $this->info("Component [{$name}] created successfully.");
        $this->line("  Class: {$componentPath}");
        $this->line("  View:  {$viewPath}");

        return self::SUCCESS;
    }

    protected function getComponentStub(): string
    {
        return <<<'PHP'
<?php

namespace App\Components;

use Kailyn\Component\Attributes\Reactive;
use Kailyn\Component\Component;

class {{ class }} extends Component
{
    #[Reactive]
    public string $message = 'Hello from {{ name }}!';

    public function update(string $message): void
    {
        $this->message = $message;
    }
}

PHP;
    }

    protected function getViewStub(): string
    {
        return <<<'HTML'
<div>
    <h3>{{ $message }}</h3>
    <input type="text" k-model="message" placeholder="Type something..." />
    <button k-on:click="update('Updated!')">Update</button>
</div>
HTML;
    }
}
