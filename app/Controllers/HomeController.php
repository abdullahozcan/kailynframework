<?php

namespace App\Controllers;

use Kailyn\Http\Request;
use Kailyn\Http\Response;

class HomeController
{
    public function index(Request $request): Response
    {
        return Response::json(['message' => 'Hello from ' . __CLASS__]);
    }
}
