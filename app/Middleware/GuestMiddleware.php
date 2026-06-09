<?php

namespace App\Middleware;

use Kailyn\Http\Middleware;
use Kailyn\Http\Request;
use Kailyn\Http\Response;

class GuestMiddleware extends Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!session()->has('user_id')) {
            return $next($request);
        }

        return Response::redirect('/dashboard');
    }
}
