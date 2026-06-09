<?php

/** @var Kailyn\Foundation\Application $app */
$router = $app->make(Kailyn\Http\Router::class);

// ---- Guest Routes ----

$router->get('/', function (Kailyn\Template\Engine $view) {
    return $view->render('welcome', [
        'name' => 'Kailyn',
        'items' => ['Router', 'Template Engine', 'Container', 'Components'],
    ]);
});

$router->get('/hello/{name}', function (string $name) {
    return 'Hello, ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '!';
});

$router->get('/json', function () {
    return ['message' => 'Hello, Kailyn!', 'version' => '1.0'];
});

$router->get('/reactive', function (Kailyn\Template\Engine $view) {
    return $view->render('reactive-demo');
});

$router->get('/design', function (Kailyn\Template\Engine $view) {
    return $view->render('design');
});

// ---- Auth Routes (Guest) ----

$router->get('/login', [App\Controllers\AuthController::class, 'showLoginForm']);
$router->middleware(['throttle'])->post('/login', [App\Controllers\AuthController::class, 'login']);
$router->get('/register', [App\Controllers\AuthController::class, 'showRegisterForm']);
$router->middleware(['throttle'])->post('/register', [App\Controllers\AuthController::class, 'register']);

// ---- Auth Routes (Protected) ----

$router->middleware(['auth'])->get('/dashboard', [App\Controllers\DashboardController::class, 'index']);
$router->middleware(['auth'])->post('/logout', [App\Controllers\AuthController::class, 'logout']);

// ---- Route Group Example ----
// $router->middleware(['auth'])->group(function () use ($router) {
//     $router->get('/dashboard', [App\Controllers\DashboardController::class, 'index']);
//     $router->post('/logout', [App\Controllers\AuthController::class, 'logout']);
// });
