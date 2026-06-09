<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$realFile = realpath(__DIR__ . $uri);

if ($uri !== '/' && $realFile !== false && str_starts_with($realFile, __DIR__ . DIRECTORY_SEPARATOR)) {
    return false;
}

require __DIR__ . '/index.php';
