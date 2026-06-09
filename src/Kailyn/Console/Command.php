<?php

namespace Kailyn\Console;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

abstract class Command extends SymfonyCommand
{
    protected string $signature = '';
    protected static string $defaultDescription = '';
    protected ?InputInterface $input = null;
    protected ?OutputInterface $output = null;

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        if (!empty($this->signature)) {
            $sig = new Signature($this->signature);
            $this->setName($sig->getName());

            $definition = $sig->toInputDefinition();
            $this->setDefinition($definition);
        }

        if (!empty($this->description)) {
            $this->setDescription($this->description);
        } elseif (!empty(static::$defaultDescription)) {
            $this->setDescription(static::$defaultDescription);
        }
    }

    abstract public function handle(): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        return $this->handle();
    }

    public function argument(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->input->getArguments();
        }

        return $this->input->getArgument($key);
    }

    public function option(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->input->getOptions();
        }

        return $this->input->getOption($key);
    }

    public function line(string $text, string $style = ''): void
    {
        if ($style) {
            $text = "<{$style}>{$text}</{$style}>";
        }

        $this->output->writeln($text);
    }

    public function info(string $text): void
    {
        $this->line($text, 'info');
    }

    public function comment(string $text): void
    {
        $this->line($text, 'comment');
    }

    public function question(string $text): void
    {
        $this->line($text, 'question');
    }

    public function error(string $text): void
    {
        $this->line($text, 'error');
    }

    public function warn(string $text): void
    {
        $this->line($text, 'warning');
    }

    public function alert(string $text): void
    {
        $length = mb_strlen($text) + 12;
        $this->line(str_repeat('*', $length), 'error');
        $this->line('*     ' . $text . '     *', 'error');
        $this->line(str_repeat('*', $length), 'error');
    }

    public function newLine(int $count = 1): void
    {
        $this->output->writeln(str_repeat("\n", $count));
    }

    public function table(array $headers, array $rows): void
    {
        $table = new Table($this->output);
        $table->setHeaders($headers)->setRows($rows);
        $table->render();
    }

    public function ask(string $question, ?string $default = null): mixed
    {
        $helper = $this->getHelper('question');
        $q = new Question("<question>{$question}</question> ", $default);

        return $helper->ask($this->input, $this->output, $q);
    }

    public function confirm(string $question, bool $default = true): bool
    {
        $helper = $this->getHelper('question');
        $q = new ConfirmationQuestion("<question>{$question}</question> ", $default);

        return (bool) $helper->ask($this->input, $this->output, $q);
    }

    public function secret(string $question): ?string
    {
        $helper = $this->getHelper('question');
        $q = new Question("<question>{$question}</question> ");
        $q->setHidden(true)->setHiddenFallback(false);

        return $helper->ask($this->input, $this->output, $q);
    }

    public function choice(string $question, array $choices, ?string $default = null): mixed
    {
        $helper = $this->getHelper('question');
        $q = new ChoiceQuestion("<question>{$question}</question> ", $choices, $default);

        return $helper->ask($this->input, $this->output, $q);
    }

    public function progressBar(int $max = 0): ProgressBar
    {
        return new ProgressBar($this->output, $max);
    }

    public function call(string $commandName, array $arguments = []): int
    {
        return $this->getApplication()->find($commandName)->execute(
            new \Symfony\Component\Console\Input\ArrayInput($arguments),
            $this->output
        );
    }

    public function callSilent(string $commandName, array $arguments = []): int
    {
        return $this->getApplication()->find($commandName)->execute(
            new \Symfony\Component\Console\Input\ArrayInput($arguments),
            new \Symfony\Component\Console\Output\NullOutput
        );
    }
}
