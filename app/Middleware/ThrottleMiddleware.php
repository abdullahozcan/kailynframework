<?php

namespace App\Middleware;

use Kailyn\Http\Middleware;
use Kailyn\Http\Request;
use Kailyn\Http\Response;
use Closure;

class ThrottleMiddleware implements Middleware
{
    protected int $maxAttempts;
    protected int $decayMinutes;

    public function __construct(int $maxAttempts = 5, int $decayMinutes = 15)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->resolveRequestSignature($request);
        $attempts = $this->getAttempts($key);

        if ($attempts >= $this->maxAttempts) {
            $retryAfter = $this->getTimeUntilRetry($key);
            return new Response('Too many attempts. Please try again in ' . $retryAfter . ' seconds.', 429);
        }

        $this->incrementAttempts($key);
        $response = $next($request);

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        return sha1($request->ip() . '|' . $request->path());
    }

    protected function getAttempts(string $key): int
    {
        $session = session()->get('_throttle', []);
        $attempt = $session[$key] ?? null;

        if ($attempt === null) {
            return 0;
        }

        if (time() > $attempt['time'] + ($this->decayMinutes * 60)) {
            $this->resetAttempts($key);
            return 0;
        }

        return $attempt['count'];
    }

    protected function incrementAttempts(string $key): void
    {
        $session = session()->get('_throttle', []);
        $attempt = $session[$key] ?? ['count' => 0, 'time' => time()];
        $attempt['count']++;
        $session[$key] = $attempt;
        session()->set('_throttle', $session);
    }

    protected function resetAttempts(string $key): void
    {
        $session = session()->get('_throttle', []);
        unset($session[$key]);
        session()->set('_throttle', $session);
    }

    protected function getTimeUntilRetry(string $key): int
    {
        $session = session()->get('_throttle', []);
        $attempt = $session[$key] ?? null;

        if ($attempt === null) {
            return 0;
        }

        return max(0, ($attempt['time'] + ($this->decayMinutes * 60)) - time());
    }
}
