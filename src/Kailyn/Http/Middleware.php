<?php

namespace Kailyn\Http;

abstract class Middleware
{
    protected array $except = [];

    abstract public function handle(Request $request, callable $next): Response;

    protected function shouldSkip(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if (fnmatch($pattern, $request->path())) {
                return true;
            }
        }

        return false;
    }
}
