<?php

namespace Kailyn\Console\Commands;

use Kailyn\Console\Command;

class ServeCommand extends Command
{
    protected string $signature = 'serve {host=127.0.0.1} {--port=8000}';
    protected string $description = 'Start the Kailyn development server';

    public function handle(): int
    {
        $host = $this->argument('host');
        $port = $this->option('port');
        $public = $this->getApplication()->getKailynApp()->basePath('public');
        $router = $public . '/router.php';

        $this->info("Kailyn development server started:");
        $this->line("  <info>http://{$host}:{$port}</info>");
        $this->line("  Listening on http://{$host}:{$port}");
        $this->line("  Document root: {$public}");
        $this->line("  Press Ctrl+C to stop");
        $this->newLine();

        $command = sprintf(
        'php -S %s:%s -t %s %s',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($public),
            escapeshellarg($router)
        );

        passthru($command);

        return self::SUCCESS;
    }
}
