<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;

class MakeModelCommand extends Command
{
    protected string $signature = 'make:model {name} {--force}';
    protected string $description = 'Create a new model class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $force = $this->option('force');
        $path = $this->getApplication()->getKailynApp()->basePath('app/Models/' . $name . '.php');

        if (file_exists($path) && !$force) {
            $this->error("Model [{$name}] already exists! Use --force to overwrite.");
            return self::FAILURE;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stub = $this->getStub();
        $content = str_replace(['{{ class }}', '{{ table }}'], [$name, $this->tableName($name)], $stub);

        file_put_contents($path, $content);

        $this->info("Model [{$name}] created successfully.");
        $this->line("  Path: {$path}");

        return self::SUCCESS;
    }

    protected function tableName(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
    }

    protected function getStub(): string
    {
        return <<<'PHP'
<?php

namespace App\Models;

use Kailyn\Database\Model;

class {{ class }} extends Model
{
    protected string $table = '{{ table }}';
    protected array $fillable = [];
}

PHP;
    }
}
