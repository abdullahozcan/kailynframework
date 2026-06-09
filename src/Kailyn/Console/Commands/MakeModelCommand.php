<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;

class MakeModelCommand extends Command
{
    protected string $signature = 'make:model {name} {--force} {-m|--migration}';
    protected string $description = 'Create a new model class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $force = $this->option('force');
        $withMigration = $this->option('migration');
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

        if ($withMigration) {
            $this->createMigration($name);
        }

        return self::SUCCESS;
    }

    protected function createMigration(string $name): void
    {
        $table = $this->tableName($name);
        $migrationName = 'create_' . $table . '_table';
        $path = base_path('database/migrations');
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$migrationName}.php";
        $filePath = "{$path}/{$filename}";

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $stub = $this->getMigrationStub();
        $content = str_replace('{{ table }}', $table, $stub);

        file_put_contents($filePath, $content);

        $this->info("Migration [{$filename}] created successfully.");
        $this->line("  Path: {$filePath}");
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

    protected function getMigrationStub(): string
    {
        return <<<'PHP'
<?php

use Kailyn\Database\Schema;
use Kailyn\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{{ table }}', function ($table) {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('{{ table }}');
    }
};

PHP;
    }
}
