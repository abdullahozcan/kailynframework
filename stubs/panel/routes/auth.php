<?php

// ============================================================
// [kailyn:auth] START — managed by `php tulpar auth:install`
// Do not edit between the markers; re-run auth:install to reset.
// ============================================================

$router->get('/login', [App\Controllers\AuthController::class, 'showLoginForm']);
$router->middleware(['throttle'])->post('/login', [App\Controllers\AuthController::class, 'login']);
$router->get('/register', [App\Controllers\AuthController::class, 'showRegisterForm']);
$router->middleware(['throttle'])->post('/register', [App\Controllers\AuthController::class, 'register']);

$router->middleware(['auth'])->get('/admin', [App\Controllers\Panel\DashboardController::class, 'index']);
$router->middleware(['auth'])->post('/admin/logout', [App\Controllers\AuthController::class, 'logout']);

// ============================================================
// [kailyn:auth] END
// ============================================================