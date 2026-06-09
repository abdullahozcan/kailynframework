<?php

namespace App\Middleware;

use Kailyn\Http\Middleware;
use Kailyn\Http\Request;
use Kailyn\Http\Response;


class SecurityHeadersMiddleware extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('X-XSS-Protection', '1; mode=block');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
