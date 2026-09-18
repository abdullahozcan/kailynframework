<?php

namespace Kailyn\Console;

use Kailyn\Foundation\Application as KailynApplication;
use Symfony\Component\Console\Application as SymfonyApplication;

class Application extends SymfonyApplication
{
    private KailynApplication $kailynApp;

    public function __construct(KailynApplication $kailynApp, string $name = 'Tulpar - Kailyn Framework', ?string $version = null)
    {
        $this->kailynApp = $kailynApp;
        $version = $version ?? (function_exists('version') ? version() : '0.0.0');
        parent::__construct($name, $version);
    }

    public function getKailynApp(): KailynApplication
    {
        return $this->kailynApp;
    }
}
