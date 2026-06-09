<?php

$app = new Kailyn\Foundation\Application(__DIR__ . '/..');

Kailyn\Config\Environment::load(__DIR__ . '/..');

$app->boot();

return $app;
