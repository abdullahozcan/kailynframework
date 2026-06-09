<?php

namespace App\Controllers;

use Kailyn\Http\Request;
use Kailyn\Http\Response;

class ProductController
{
    public function index(Request $request): Response
    {
        return Response::json(['data' => []]);
    }

    public function show(Request $request, string $id): Response
    {
        return Response::json(['data' => []]);
    }

    public function store(Request $request): Response
    {
        return Response::json(['message' => 'Created'], 201);
    }

    public function update(Request $request, string $id): Response
    {
        return Response::json(['message' => 'Updated']);
    }

    public function destroy(Request $request, string $id): Response
    {
        return Response::noContent();
    }
}
