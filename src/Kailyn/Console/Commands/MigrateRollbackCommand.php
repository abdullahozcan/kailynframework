<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;
use Kailyn\Database\Migrator;

class MigrateRollbackCommand extends Command
{
    protected string $signature = 'migrate:rollback {--step=1}';

    public function handle(): int
    {
        $migrator = Migrator::resolve();

        if (!$migrator->migrationTableExists()) {
            $this->error('No migrations table found.');
            return 1;
        }

        $step = (int) $this->option('step');

        $this->info("Rolling back {$step} batch(es)...");

        $lastBatch = $migrator->getLastBatchNumber();

        for ($i = 0; $i < $step; $i++) {
            $batch = $lastBatch - $i;

            if ($batch <= 0) {
                break;
            }

            $migrations = $migrator->getMigrationsByBatch($batch);

            foreach (array_reverse($migrations) as $migration) {
                $this->line("  <info>ROLLED BACK:</info> {$migration}");
                $migrator->rollback($migration);
            }
        }

        $this->info('Done.');

        return 0;
    }
}
