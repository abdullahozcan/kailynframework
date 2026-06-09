<?php

/** @var Kailyn\Foundation\Application $app */
$router = $app->make(Kailyn\Http\Router::class);

$router->get('/', function (Kailyn\Template\Engine $view) {
    return $view->render('welcome', [
        'name' => 'Kailyn',
        'items' => ['Router', 'Template Engine', 'Container', 'Components'],
    ]);
});

$router->get('/hello/{name}', function (string $name) {
    return "Hello, {$name}!";
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

$router->post('/_kailyn/update', function (Kailyn\Http\Request $request) {
    $manager = app(Kailyn\Component\ComponentManager::class);
    return $manager->handleUpdate($request);
});
