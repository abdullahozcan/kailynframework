<?php

namespace App\Middleware;

use Kailyn\Http\Middleware;
use Kailyn\Http\Request;
use Kailyn\Http\Response;


class ThrottleMiddleware extends Middleware
{
    protected int $maxAttempts;
    protected int $decayMinutes;

    public function __construct(int $maxAttempts = 5, int $decayMinutes = 15)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public function handle(Request $request, callable $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        $attempts = $this->getAttempts($key);

        if ($attempts >= $this->maxAttempts) {
            $retryAfter = $this->getTimeUntilRetry($key);
            $response = new Response('Too many attempts. Please try again in ' . $retryAfter . ' seconds.', 429);
            $response->setHeader('Retry-After', (string) $retryAfter);
            return $response;
        }

        $response = $next($request);

        if ($response->getStatus() >= 400) {
            $this->incrementAttempts($key);
        }

        $remaining = max(0, $this->maxAttempts - $this->getAttempts($key));
        $response->setHeader('X-RateLimit-Limit', (string) $this->maxAttempts);
        $response->setHeader('X-RateLimit-Remaining', (string) $remaining);

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        return 'throttle:' . sha1($request->ip() . '|' . $request->path());
    }

    protected function getAttempts(string $key): int
    {
        $data = cache()->get($key);

        if ($data === null) {
            return 0;
        }

        if (time() > $data['time'] + ($this->decayMinutes * 60)) {
            $this->resetAttempts($key);
            return 0;
        }

        return $data['count'];
    }

    protected function incrementAttempts(string $key): void
    {
        $data = cache()->get($key);
        $attempt = $data ?? ['count' => 0, 'time' => time()];
        $attempt['count']++;
        cache()->set($key, $attempt, $this->decayMinutes * 60);
    }

    protected function resetAttempts(string $key): void
    {
        cache()->delete($key);
    }

    protected function getTimeUntilRetry(string $key): int
    {
        $data = cache()->get($key);

        if ($data === null) {
            return 0;
        }

        return max(0, ($data['time'] + ($this->decayMinutes * 60)) - time());
    }
}
