<?php

namespace Kailyn\Session;

class SessionManager
{
    private bool $started = false;
    private array $flash = [];

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->started = true;
        $this->loadFlash();
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $this->start();
        session_destroy();
        $this->started = false;
    }

    public function flash(string $key, mixed $value = null): mixed
    {
        $this->start();

        if ($value === null) {
            $val = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return $val;
        }

        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    public function flashNow(string $key, mixed $value = null): mixed
    {
        $this->start();

        if ($value === null) {
            return $this->flash[$key] ?? null;
        }

        $this->flash[$key] = $value;
        return null;
    }

    public function reflash(): void
    {
        $this->start();
        if (isset($_SESSION['_flash'])) {
            foreach ($_SESSION['_flash'] as $key => $value) {
                $_SESSION['_flash'][$key] = $value;
            }
        }
    }

    public function keep(array $keys): void
    {
        $this->start();
        if (!isset($_SESSION['_flash'])) {
            return;
        }
        foreach ($keys as $key) {
            if (isset($_SESSION['_flash'][$key])) {
                $this->flash($key, $_SESSION['_flash'][$key]);
            }
        }
    }

    public function regenerate(): bool
    {
        $this->start();
        return session_regenerate_id(true);
    }

    public function getId(): string
    {
        $this->start();
        return session_id();
    }

    public function setId(string $id): void
    {
        $this->start();
        session_id($id);
    }

    public function all(): array
    {
        $this->start();
        $data = $_SESSION ?? [];
        unset($data['_flash']);
        return $data;
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    public function token(): string
    {
        $this->start();

        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }

    public function validateToken(string $token): bool
    {
        return hash_equals($this->token(), $token);
    }

    private function loadFlash(): void
    {
        if (isset($_SESSION['_flash'])) {
            $this->flash = $_SESSION['_flash'];
            unset($_SESSION['_flash']);
        }
    }
}
