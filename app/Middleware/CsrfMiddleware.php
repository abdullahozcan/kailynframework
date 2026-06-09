<?php

namespace App\Middleware;

use Kailyn\Http\Middleware;
use Kailyn\Http\Request;
use Kailyn\Http\Response;
use Closure;

class CsrfMiddleware implements Middleware
{
    protected array $except = [
        '/_kailyn/update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isReading($request)) {
            return $next($request);
        }

        if ($this->inExceptArray($request)) {
            return $next($request);
        }

        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN') ?: '';

        if (!session()->validateToken($token)) {
            return new Response('CSRF token mismatch', 419);
        }

        return $next($request);
    }

    protected function isReading(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS']);
    }

    protected function inExceptArray(Request $request): bool
    {
        foreach ($this->except as $except) {
            if ($request->path() === $except) {
                return true;
            }
        }

        return false;
    }
}
