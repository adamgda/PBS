<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

/**
 * Bazowy kontroler — dostarcza metody pomocnicze dla odpowiedzi JSON.
 */
abstract class Controller
{
    /**
     * @param array<string, mixed>|null $data
     */
    protected function json(?array $data = null, int $statusCode = 200): Response
    {
        return Response::json($statusCode, $data);
    }

    protected function success(mixed $data = null, int $statusCode = 200): Response
    {
        return Response::success($data, $statusCode);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function created(array $data = []): Response
    {
        return Response::created($data);
    }

    protected function noContent(): Response
    {
        return Response::noContent();
    }

    /**
     * @param array<string, mixed>|null $details
     */
    protected function error(int $statusCode, string $message, ?array $details = null): Response
    {
        return Response::error($statusCode, $message, $details);
    }

    /**
     * @param array<int, string> $requiredKeys
     * @return array<string, mixed>
     */
    protected function validatedBody(Request $request, array $requiredKeys = []): array
    {
        $body = $request->body();
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $body)) {
                throw new \InvalidArgumentException("Missing required field: {$key}");
            }
        }

        return $body;
    }
}