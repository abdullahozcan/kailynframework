<?php

define('KAILYN_START', microtime(true));

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Kailyn\Foundation\Application(__DIR__ . '/..');

$app->run();
