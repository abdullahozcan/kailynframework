<?php

define('KAILYN_START', microtime(true));

require_once __DIR__ . '/../vendor/autoload.php';

Kailyn\Config\Environment::load(__DIR__ . '/..');

$app = new Kailyn\Foundation\Application(__DIR__ . '/..');

$app->run();
