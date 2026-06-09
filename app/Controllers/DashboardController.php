<?php

namespace App\Controllers;

use Kailyn\Http\Response;

class DashboardController
{
    public function index(): string
    {
        return view('dashboard', [
            'userName' => session()->get('user_name', 'User'),
        ]);
    }
}
