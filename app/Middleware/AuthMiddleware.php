<?php

namespace App\Middleware;

use Kailyn\Http\Middleware;
use Kailyn\Http\Request;
use Kailyn\Http\Response;

class AuthMiddleware extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (session()->has('user_id')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return Response::json(['error' => 'Unauthenticated'], 401);
        }

        session()->flash('error', 'Please login first.');
        return Response::redirect('/login');
    }
}
