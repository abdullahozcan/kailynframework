<?php

namespace Kailyn\Console\Commands;

use App\Models\User;
use Kailyn\Console\Command;

class AuthUserCommand extends Command
{
    protected string $signature = 'auth:user {--create} {--list} {--delete=} {--name=} {--email=} {--password=}';
    protected string $description = 'Manage application users (create, list, delete)';

    public function handle(): int
    {
        if (!class_exists(User::class)) {
            $this->error('User model not found. Run `php bin/tulpar auth:install` first.');
            return self::FAILURE;
        }

        if ($this->option('delete') !== null) {
            return $this->deleteUser((int) $this->option('delete'));
        }

        if ($this->option('create')) {
            return $this->createUser();
        }

        return $this->listUsers();
    }

    protected function createUser(): int
    {
        $name = $this->option('name');
        $email = $this->option('email');
        $password = $this->option('password');

        if ($name === null) {
            $name = $this->ask('Name:');
        }

        if ($email === null) {
            $email = $this->ask('Email:');
        }

        if ($password === null) {
            $password = $this->secret('Password:');
        }

        $name = trim((string) $name);
        $email = trim((string) $email);
        $password = (string) $password;

        if ($name === '' || $email === '' || $password === '') {
            $this->error('Name, email and password are required.');
            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Please enter a valid email address.');
            return self::FAILURE;
        }

        if (User::where('email', '=', $email)->first() !== null) {
            $this->error("A user with email [{$email}] already exists.");
            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("User created successfully.");
        $this->line("  ID:    {$user->getKey()}");
        $this->line("  Name:  {$user->name}");
        $this->line("  Email: {$user->email}");

        return self::SUCCESS;
    }

    protected function listUsers(): int
    {
        $users = User::all();

        if (empty($users)) {
            $this->warn('No users found. Create one with: php bin/tulpar auth:user --create');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($users as $user) {
            $rows[] = [$user->getKey(), $user->name, $user->email, $user->created_at ?? ''];
        }

        $this->table(['ID', 'Name', 'Email', 'Created At'], $rows);

        return self::SUCCESS;
    }

    protected function deleteUser(int $id): int
    {
        $user = User::find($id);

        if ($user === null) {
            $this->error("User [{$id}] not found.");
            return self::FAILURE;
        }

        if (!$this->confirm("Delete user [{$user->name} <{$user->email}>]? This cannot be undone.", false)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        $user->delete();

        $this->info("User [{$id}] deleted.");

        return self::SUCCESS;
    }
}