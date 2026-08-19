<?php

namespace App\Controllers\Panel;

use App\Models\User;

class DashboardController
{
    public function index(): string
    {
        return view('panel.dashboard', [
            'userName' => session()->get('user_name', 'User'),
            'userCount' => User::count(),
            'users' => User::all(),
        ]);
    }
}