<?php

namespace App\Controllers;

use App\Models\User;
use Kailyn\Http\Request;
use Kailyn\Http\Response;

class AuthController
{
    public function showLoginForm(): string
    {
        return view('auth.login');
    }

    public function login(Request $request): Response
    {
        $data = $request->only(['email', 'password']);

        $validator = validator($data, [
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email.',
            'password.required' => 'Password is required.',
        ]);

        if ($validator->fails()) {
            session()->flash('errors', $validator->errors());
            session()->flash('old', $data);
            return Response::redirect('/login');
        }

        $user = User::where('email', '=', $data['email'])->first();

        if (!$user || !$user->verifyPassword($data['password'])) {
            session()->flash('error', 'Invalid email or password.');
            session()->flash('old', $data);
            return Response::redirect('/login');
        }

        session()->set('user_id', $user->getKey());
        session()->set('user_name', $user->name);
        session()->flash('success', 'Welcome back, ' . $user->name . '!');
        session()->regenerate();

        return Response::redirect('/dashboard');
    }

    public function showRegisterForm(): string
    {
        return view('auth.register');
    }

    public function register(Request $request): Response
    {
        $data = $request->only(['name', 'email', 'password', 'password_confirmation']);

        $validator = validator($data, [
            'name' => 'required|min:3|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ], [
            'name.required' => 'Name is required.',
            'name.min' => 'Name must be at least 3 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        if ($validator->fails()) {
            session()->flash('errors', $validator->errors());
            session()->flash('old', $data);
            return Response::redirect('/register');
        }

        $existing = User::where('email', '=', $data['email'])->first();

        if ($existing) {
            session()->flash('error', 'Registration failed. Please check your information.');
            session()->flash('old', $data);
            return Response::redirect('/register');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        session()->set('user_id', $user->getKey());
        session()->set('user_name', $user->name);
        session()->flash('success', 'Account created successfully!');
        session()->regenerate();

        return Response::redirect('/dashboard');
    }

    public function logout(): Response
    {
        session()->destroy();
        session()->flash('success', 'You have been logged out.');

        return Response::redirect('/');
    }
}
