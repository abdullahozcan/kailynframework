<?php

namespace Kailyn\Console;

use Kailyn\Console\Commands\MakeComponentCommand;
use Kailyn\Console\Commands\MakeControllerCommand;
use Kailyn\Console\Commands\MakeMigrationCommand;
use Kailyn\Console\Commands\MakeModelCommand;
use Kailyn\Console\Commands\MigrateCommand;
use Kailyn\Console\Commands\MigrateFreshCommand;
use Kailyn\Console\Commands\MigrateRollbackCommand;
use Kailyn\Console\Commands\RouteListCommand;
use Kailyn\Console\Commands\ServeCommand;
use Kailyn\Foundation\Application;
use Kailyn\Console\Application as TulparApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel
{
    protected Application $app;
    protected TulparApplication $symfonyApp;
    protected array $commands = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->symfonyApp = new TulparApplication($app);
    }

    public function registerBuiltinCommands(): void
    {
        $this->commands = [
            ServeCommand::class,
            MakeModelCommand::class,
            MakeControllerCommand::class,
            MakeComponentCommand::class,
            MakeMigrationCommand::class,
            MigrateCommand::class,
            MigrateRollbackCommand::class,
            MigrateFreshCommand::class,
            RouteListCommand::class,
        ];
    }

    public function addCommand(Command|string $command): void
    {
        if (is_string($command)) {
            $command = $this->app->make($command);
        }

        $this->symfonyApp->add($command);
    }

    public function handle(
        ?InputInterface $input = null,
        ?OutputInterface $output = null
    ): int {
        $this->registerBuiltinCommands();

        $userKernel = $this->app->basePath('app/Console/Kernel.php');

        if (file_exists($userKernel)) {
            require $userKernel;
        }

        foreach ($this->commands as $command) {
            $this->addCommand($command);
        }

        $input ??= new ArgvInput;
        $output ??= new ConsoleOutput;

        return $this->symfonyApp->run($input, $output);
    }

    public function getSymfonyApp(): SymfonyApplication
    {
        return $this->symfonyApp;
    }
}
