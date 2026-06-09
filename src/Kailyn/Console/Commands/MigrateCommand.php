<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;
use Kailyn\Database\Migrator;

class MigrateCommand extends Command
{
    protected string $signature = 'migrate {--force}';

    public function handle(): int
    {
        $migrator = Migrator::resolve();

        if (!$migrator->migrationTableExists()) {
            $this->info('Creating migrations table...');
            $migrator->createMigrationTable();
        }

        $pending = $migrator->getPending();

        if (empty($pending)) {
            $this->info('Nothing to migrate.');
            return 0;
        }

        $this->info('Running migrations...');

        foreach ($pending as $migration) {
            $this->line("  <info>MIGRATED:</info> {$migration}");
            $migrator->run($migration);
        }

        $this->info('Done.');

        return 0;
    }
}
