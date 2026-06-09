<?php

namespace Kailyn\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class Signature
{
    private string $name;
    private array $arguments = [];
    private array $options = [];

    public function __construct(string $signature)
    {
        $this->parse($signature);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function toInputDefinition(): InputDefinition
    {
        return new InputDefinition(array_merge($this->arguments, $this->options));
    }

    private function parse(string $signature): void
    {
        $pattern = '/\{([^}]+)\}/';
        $parts = preg_split($pattern, $signature);
        $matches = [];

        preg_match_all($pattern, $signature, $matches);

        $this->name = trim($parts[0] ?? '');

        foreach ($matches[1] as $token) {
            $token = trim($token);

            if (str_starts_with($token, '--')) {
                $this->parseOption($token);
            } else {
                $this->parseArgument($token);
            }
        }
    }

    private function parseArgument(string $token): void
    {
        $name = $token;
        $default = null;
        $mode = InputArgument::REQUIRED;

        if (str_contains($token, '=')) {
            [$name, $default] = explode('=', $token, 2);
            $default = $this->castDefault($default);
            $mode = InputArgument::OPTIONAL;
        } elseif (str_ends_with($token, '?')) {
            $name = rtrim($token, '?');
            $mode = InputArgument::OPTIONAL;
        } elseif (str_ends_with($token, '*')) {
            $name = rtrim($token, '*');
            $mode = InputArgument::IS_ARRAY;
        }

        $description = '';

        if (str_contains($name, ' : ')) {
            [$name, $description] = explode(' : ', $name, 2);
            $name = trim($name);
            $description = trim($description);
        }

        $this->arguments[] = new InputArgument(
            trim($name),
            $mode,
            $description,
            $default
        );
    }

    private function parseOption(string $token): void
    {
        $token = ltrim($token, '-');
        $name = $token;
        $shortcut = null;
        $default = null;
        $mode = InputOption::VALUE_NONE;

        if (str_contains($token, '=')) {
            [$name, $default] = explode('=', $token, 2);
            $default = $this->castDefault($default);
            $mode = $default !== null
                ? InputOption::VALUE_OPTIONAL
                : InputOption::VALUE_REQUIRED;
        }

        if (str_contains($name, '|')) {
            [$shortcut, $name] = explode('|', $name, 2);
        }

        $description = '';

        if (str_contains($name, ' : ')) {
            [$name, $description] = explode(' : ', $name, 2);
            $name = trim($name);
            $description = trim($description);
        }

        $this->options[] = new InputOption(
            trim($name),
            $shortcut,
            $mode,
            $description,
            $default
        );
    }

    private function castDefault(mixed $value): mixed
    {
        if ($value === 'null') return null;
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }
        return $value;
    }
}
