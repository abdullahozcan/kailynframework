<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;
use Kailyn\Database\Migrator;

class MigrateFreshCommand extends Command
{
    protected string $signature = 'migrate:fresh {--force}';

    public function handle(): int
    {
        if (!$this->option('force')) {
            $confirmed = $this->confirm(
                'Are you sure you want to drop all tables and re-run all migrations?',
                false
            );

            if (!$confirmed) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        $migrator = Migrator::resolve();

        $migrations = $migrator->getMigrations();

        $this->info('Dropping all tables...');

        foreach (array_reverse($migrations) as $migration) {
            try {
                $file = $migrator->getMigrationPath() . '/' . $migration . '.php';

                if (!file_exists($file)) {
                    continue;
                }

                $instance = require $file;

                if ($instance instanceof \Kailyn\Database\Migration) {
                    $instance->down();
                    $this->line("  <info>ROLLED BACK:</info> {$migration}");
                }
            } catch (\Throwable $e) {
                $this->warn("  Skipped {$migration}: {$e->getMessage()}");
            }
        }

        if ($migrator->migrationTableExists()) {
            \Kailyn\Database\Schema::drop('migrations');
        }

        $this->info('Re-running all migrations...');

        $migrator->createMigrationTable();

        foreach ($migrations as $migration) {
            $this->line("  <info>MIGRATED:</info> {$migration}");
            $migrator->run($migration);
        }

        $this->info('Done.');

        return 0;
    }
}
