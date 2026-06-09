<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;

class MakeMigrationCommand extends Command
{
    protected string $signature = 'make:migration {name} {--create=} {--table=}';

    public function handle(): int
    {
        $name = $this->argument('name');
        $create = $this->option('create');
        $table = $this->option('table');

        $path = base_path('database/migrations');
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filePath = "{$path}/{$filename}";

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        if (file_exists($filePath)) {
            $this->error("Migration already exists: {$filename}");
            return 1;
        }

        $stub = $this->buildStub($name, $create, $table);

        file_put_contents($filePath, $stub);

        $this->info("Migration created: {$filename}");

        return 0;
    }

    protected function buildStub(string $name, ?string $create, ?string $table): string
    {
        if ($create !== null) {
            return $this->createStub($create);
        }

        if ($table !== null) {
            return $this->tableStub($table);
        }

        return $this->blankStub($name);
    }

    protected function blankStub(string $name): string
    {
        return <<<PHP
<?php

use Kailyn\Database\Schema;
use Kailyn\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};

PHP;
    }

    protected function createStub(string $table): string
    {
        return <<<PHP
<?php

use Kailyn\Database\Schema;
use Kailyn\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (\$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('{$table}');
    }
};

PHP;
    }

    protected function tableStub(string $table): string
    {
        return <<<PHP
<?php

use Kailyn\Database\Schema;
use Kailyn\Database\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$table}', function (\$table) {
            //
        });
    }

    public function down(): void
    {
        Schema::table('{$table}', function (\$table) {
            //
        });
    }
};

PHP;
    }
}
