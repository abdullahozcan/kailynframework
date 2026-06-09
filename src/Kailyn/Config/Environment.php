<?php

namespace Kailyn\Config;

class Environment
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenv = \Dotenv\Dotenv::createImmutable($path);
        $dotenv->safeLoad();

        self::$loaded = true;
    }

    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
