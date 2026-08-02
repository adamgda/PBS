<?php

declare(strict_types=1);

use App\Http\Response;

it('creates json response with correct status and data', function (): void {
    $response = Response::json(200, ['key' => 'value']);

    expect($response->statusCode())->toBe(200);
    expect($response->data())->toBe(['key' => 'value']);
});

it('creates success response wrapping data', function (): void {
    $response = Response::success(['item' => 1]);

    expect($response->statusCode())->toBe(200);
    expect($response->data())->toBe(['data' => ['item' => 1]]);
});

it('creates created response with 201', function (): void {
    $response = Response::created(['id' => 1]);

    expect($response->statusCode())->toBe(201);
    expect($response->data())->toBe(['id' => 1]);
});

it('creates no content response with 204', function (): void {
    $response = Response::noContent();

    expect($response->statusCode())->toBe(204);
    expect($response->data())->toBe([]);
});

it('creates error response with message', function (): void {
    $response = Response::error(404, 'Not Found');

    expect($response->statusCode())->toBe(404);
    expect($response->data())->toBe(['error' => 'Not Found']);
});

it('creates error response with details', function (): void {
    $response = Response::error(422, 'Validation failed', ['field' => 'email']);

    expect($response->statusCode())->toBe(422);
    expect($response->data())->toBe(['error' => 'Validation failed', 'details' => ['field' => 'email']]);
});

it('allows adding headers', function (): void {
    $response = Response::json(200);
    $response->header('X-Custom', 'test');

    expect($response->getHeader('X-Custom'))->toBe('test');
});