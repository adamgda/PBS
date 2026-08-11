<?php

declare(strict_types=1);

use App\Http\Request;
use App\Http\Response;
use App\Middleware\CsrfMiddleware;

describe('CsrfMiddleware', function (): void {
    beforeEach(function (): void {
        $this->allowed = ['http://localhost:4200'];
        $this->next = static fn (): Response => Response::json(200);
    });

    it('allows GET requests without Origin validation', function (): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $middleware = new CsrfMiddleware($this->allowed, 'secret-key');

        $request = new Request([], [], ['Origin' => 'http://evil.com']);
        $response = $middleware->process($request, $this->next);

        expect($response->statusCode())->toBe(200);
    });

    it('rejects POST from disallowed origin', function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $middleware = new CsrfMiddleware($this->allowed, 'secret-key');

        $request = new Request([], [], ['Origin' => 'http://evil.com']);
        $response = $middleware->process($request, $this->next);

        expect($response->statusCode())->toBe(403);
        expect($response->data()['error'])->toBe('Cross-origin request rejected');
    });

    it('allows POST from allowed origin', function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $middleware = new CsrfMiddleware($this->allowed, 'secret-key');

        $request = new Request([], [], ['Origin' => 'http://localhost:4200']);
        $response = $middleware->process($request, $this->next);

        expect($response->statusCode())->toBe(200);
    });

    it('allows POST without Origin header (non-browser clients)', function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $middleware = new CsrfMiddleware($this->allowed, 'secret-key');

        $request = new Request([], [], []);
        $response = $middleware->process($request, $this->next);

        expect($response->statusCode())->toBe(200);
    });

    it('enforces X-CSRF-Token when enabled', function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $middleware = new CsrfMiddleware($this->allowed, 'secret-key', true);

        // Brak tokena → 403
        $noToken = new Request([], [], ['Origin' => 'http://localhost:4200']);
        expect($middleware->process($noToken, $this->next)->statusCode())->toBe(403);

        // Z poprawnym tokenem → 200
        $token = $middleware->issueToken('7');
        $withToken = new Request([], [], ['Origin' => 'http://localhost:4200', 'X-CSRF-Token' => $token]);
        expect($middleware->process($withToken, $this->next)->statusCode())->toBe(200);

        // Ze sfałszowanym tokenem → 403
        $bad = new Request([], [], ['Origin' => 'http://localhost:4200', 'X-CSRF-Token' => 'forged.token']);
        expect($middleware->process($bad, $this->next)->statusCode())->toBe(403);
    });

    it('issues and verifies its own token', function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $middleware = new CsrfMiddleware($this->allowed, 'secret-key', true);

        $token = $middleware->issueToken('1');
        $request = new Request([], [], ['Origin' => 'http://localhost:4200', 'X-CSRF-Token' => $token]);

        expect($middleware->process($request, $this->next)->statusCode())->toBe(200);
    });
});
